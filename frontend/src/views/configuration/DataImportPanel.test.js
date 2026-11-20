import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { test } from 'node:test'
import vm from 'node:vm'
import { getImportConflicts } from '../../domains/shared/utils/importReview.js'
import { getImportDefinition } from '../../domains/shared/utils/dataImport.js'

const source = readFileSync(new URL('./DataImportPanel.vue', import.meta.url), 'utf8')
const setup = source.match(/<script setup>([\s\S]*?)<\/script>/)?.[1]
assert.ok(setup, 'DataImportPanel must expose a script setup block')
// Execute the actual wizard logic, not a copy. Only imports and Vue/browser
// integration are replaced; this harness does not validate rendering or reactivity.
const script = new vm.Script(setup.replace(/^import .*$/gm, '') + `
  globalThis.wizard = {
    batches, formats, type, step, error, busy, attempted, confirmed,
    rows, conflicts, valid, canExecute, definition, fileInput, changeType, selectFiles, analyze, execute,
  }
`, { filename: 'DataImportPanel.vue:script-setup' })

function report(id, status = 'ready') {
  return {
    digest: `digest-${id}`, valid: true,
    created: status === 'created' ? 2 : 0,
    rows: [2, 3].map((line) => ({
      line, idImport: `${id}-${line}`, status,
      data: { code: `${id}-${line}`, email: `${id}-${line}@example.com` },
    })),
  }
}

async function createWizard({ type = 'sites', allowedTypes = [], projectId = '', formatTypes = [type], post } = {}) {
  const calls = []
  const events = []
  const mounted = []
  const watchers = new Map()
  const context = vm.createContext({
    ref(initial) {
      let value = initial
      return {
        get value() { return value },
        set value(next) {
          const previous = value
          value = next
          if (next !== previous) watchers.get(this)?.(next)
        },
      }
    },
    computed: (get) => ({ get value() { return get() } }),
    watch: (reference, callback) => watchers.set(reference, callback),
    onMounted: (callback) => mounted.push(callback),
    onBeforeUnmount: () => {},
    onBeforeRouteLeave: () => {},
    onBeforeRouteUpdate: () => {},
    useId: () => 'wizard-test',
    defineProps: () => ({ importType: type, allowedTypes, projectId }),
    defineEmits: () => (...args) => events.push(args),
    getImportConflicts,
    getImportDefinition,
    FormData, Blob, URL, setTimeout,
    api: {
      async get(url) {
        assert.equal(url, '/data-import/formats')
        return { data: { items: formatTypes.map((type) => ({ type, required: ['id_import'], optional: [] })) } }
      },
      async post(url, body, config) {
        calls.push({ url, body, config })
        const data = post
          ? await post(url, body, config)
          : report(body.get('file').name, url.endsWith('/execute') ? 'created' : 'ready')
        return { data }
      },
    },
  })
  script.runInContext(context, { timeout: 1000 })
  for (const callback of mounted) await callback()
  const wizard = context.wizard
  events.length = 0
  return {
    wizard, calls, events,
    select(names = ['a.csv']) {
      wizard.selectFiles({ target: { files: names.map((name) => new File(['csv'], name)), value: 'selected' } })
    },
    confirmAll() {
      wizard.confirmed.value = Array.from(wizard.rows.value.filter((row) => row.status === 'ready'), (row) => row.key)
    },
  }
}

function importedEvents(events) {
  return events.filter(([name]) => name === 'imported')
}

test('a single allowed document type still requires an explicit choice before analysis', async () => {
  const { wizard, select, calls } = await createWizard({
    type: '', allowedTypes: ['bons_livraison'], projectId: 'project-42',
    formatTypes: ['bons_livraison'],
  })
  assert.deepEqual(Array.from(wizard.formats.value, (item) => item.type), ['bons_livraison'])
  assert.equal(wizard.type.value, null)
  assert.equal(wizard.definition.value, undefined)
  select()
  await wizard.analyze()
  assert.equal(calls.length, 0)
  assert.equal(wizard.step.value, 1)
  assert.equal(wizard.batches.value[0].report, null)
  assert.equal(wizard.valid.value, false)
  assert.equal(wizard.canExecute.value, false)

  wizard.type.value = 'bons_livraison'
  await wizard.analyze()
  assert.equal(calls.length, 1)
  assert.equal(calls[0].url, '/data-import/bons_livraison/preview')
  assert.equal(wizard.step.value, 2)
})

test('allowedTypes filters other formats and rejects a preselected disallowed type', async (t) => {
  for (const type of ['', 'sites']) {
    await t.test(type || 'no preselected type', async () => {
      const { wizard, select, calls } = await createWizard({
        type, allowedTypes: ['bons_livraison'], projectId: 'project-42',
        formatTypes: ['sites', 'bons_livraison', 'clients', 'factures'],
      })
      assert.deepEqual(Array.from(wizard.formats.value, (item) => item.type), ['bons_livraison'])
      wizard.type.value = 'sites'
      assert.equal(wizard.definition.value, undefined)
      if (type) assert.match(wizard.error.value, /pas disponible/)
      select()
      await wizard.analyze()
      assert.equal(calls.length, 0)
      assert.equal(wizard.step.value, 1)
    })
  }
})

test('delivery notes require project context for analysis and execution', async () => {
  const { wizard, select, confirmAll, calls } = await createWizard({
    type: 'bons_livraison', allowedTypes: ['bons_livraison'],
  })
  assert.equal(wizard.formats.value.length, 0)
  assert.equal(wizard.definition.value, undefined)
  assert.match(wizard.error.value, /pas disponible/)
  select()
  await wizard.analyze()
  assert.equal(calls.length, 0)
  assert.equal(wizard.step.value, 1)

  // Bypass format filtering to verify the operation guards independently.
  wizard.formats.value = [{ type: 'bons_livraison', required: ['id_import'], optional: [] }]
  await wizard.analyze()
  assert.equal(calls.length, 0)
  assert.equal(wizard.step.value, 1)
  wizard.batches.value[0].report = report('a.csv')
  confirmAll()
  assert.equal(wizard.valid.value, false)
  assert.equal(wizard.canExecute.value, false)
  await wizard.execute()
  assert.equal(calls.length, 0)
})

test('the general panel excludes project-only types without projectId', async () => {
  const { wizard } = await createWizard({
    type: '', formatTypes: ['bons_livraison', 'sites', 'clients', 'factures'],
  })
  assert.deepEqual(Array.from(wizard.formats.value, (item) => item.type), ['sites', 'clients', 'factures'])
  assert.equal(wizard.type.value, 'sites')
  assert.equal(wizard.error.value, '')
})

test('delivery note preview and execute send the same projectId and reuse each preview digest', async () => {
  const { wizard, select, confirmAll, calls, events } = await createWizard({
    type: 'bons_livraison', allowedTypes: ['bons_livraison'], projectId: 'project-42',
  })
  select(['a.csv', 'b.csv'])
  await wizard.analyze()
  const previews = calls.slice()
  assert.equal(previews.length, 2)
  assert.equal(importedEvents(events).length, 0)
  confirmAll()
  assert.equal(wizard.canExecute.value, true)
  await wizard.execute()
  const executions = calls.filter(({ url }) => url.endsWith('/execute'))
  assert.equal(executions.length, 2)
  for (const [index, execution] of executions.entries()) {
    const preview = previews[index]
    assert.equal(preview.url, '/data-import/bons_livraison/preview')
    assert.equal(execution.url, '/data-import/bons_livraison/execute')
    for (const { body, config } of [preview, execution]) {
      assert.ok(body instanceof FormData)
      assert.equal(body.get('projectId'), 'project-42')
      assert.equal(body.get('file'), wizard.batches.value[index].file)
      assert.equal(config.headers['Content-Type'], 'multipart/form-data')
    }
    assert.equal(preview.body.has('digest'), false)
    assert.equal(execution.body.get('projectId'), preview.body.get('projectId'))
    assert.equal(execution.body.get('digest'), report(preview.body.get('file').name).digest)
  }
  assert.deepEqual(structuredClone(importedEvents(events)), [['imported', { type: 'bons_livraison', created: 4 }]])
})

test('changeType clears batches, confirmations and stale review state', async () => {
  const { wizard, select, confirmAll, calls } = await createWizard({
    type: '', allowedTypes: ['sites', 'clients'], formatTypes: ['sites', 'clients'],
  })
  wizard.type.value = 'sites'
  select(['a.csv', 'b.csv'])
  await wizard.analyze()
  confirmAll()
  assert.equal(wizard.batches.value.length, 2)
  assert.ok(wizard.confirmed.value.length)
  assert.equal(wizard.canExecute.value, true)
  wizard.attempted.value = true
  wizard.error.value = 'stale error'
  wizard.fileInput.value = { value: 'selected' }

  wizard.type.value = 'clients'
  wizard.changeType()
  assert.equal(wizard.type.value, 'clients')
  assert.equal(wizard.batches.value.length, 0)
  assert.equal(wizard.confirmed.value.length, 0)
  assert.equal(wizard.rows.value.length, 0)
  assert.equal(wizard.attempted.value, false)
  assert.equal(wizard.error.value, '')
  assert.equal(wizard.fileInput.value.value, '')
  assert.equal(wizard.valid.value, false)
  assert.equal(wizard.canExecute.value, false)
  await wizard.execute()
  assert.equal(calls.length, 2, 'changing type must not execute previously reviewed files')
})

test('requires confirmation of every ready row across all files, but not skipped rows', async () => {
  const { wizard, select, confirmAll, calls } = await createWizard()
  select(['a.csv', 'b.csv'])
  await wizard.analyze()
  wizard.batches.value[1].report.rows[1].status = 'skipped'
  const keys = Array.from(wizard.rows.value.filter((row) => row.status === 'ready'), (row) => row.key)
  for (let omitted = 0; omitted < keys.length; omitted++) {
    wizard.confirmed.value = keys.filter((_, index) => index !== omitted)
    assert.equal(wizard.canExecute.value, false)
    await wizard.execute()
  }
  assert.equal(calls.length, 2, 'incomplete confirmations must not send execute requests')
  confirmAll()
  assert.equal(wizard.canExecute.value, true)
  wizard.busy.value = true
  assert.equal(wizard.canExecute.value, false)
  wizard.busy.value = false
  wizard.attempted.value = true
  assert.equal(wizard.canExecute.value, false)
})

test('file request errors and invalid reports block execution even with confirmations', async (t) => {
  for (const mode of ['request error', 'invalid report']) {
    await t.test(mode, async () => {
      const { wizard, select, confirmAll, calls } = await createWizard({
        post(url, body) {
          const name = body.get('file').name
          if (name === 'b.csv') {
            if (mode === 'request error') throw { response: { status: 413 } }
            return { ...report(name, 'error'), valid: false }
          }
          return report(name)
        },
      })
      select(['a.csv', 'b.csv'])
      await wizard.analyze()
      confirmAll()
      assert.equal(wizard.valid.value, false)
      assert.equal(wizard.canExecute.value, false)
      if (mode === 'request error') assert.match(wizard.batches.value[1].error, /taille de fichier/)
      await wizard.execute()
      assert.equal(calls.length, 2)
    })
  }
})

test('idImport and natural-key conflicts block execution', async (t) => {
  for (const [type, field] of [['clients', 'idImport'], ['sites', 'code'], ['prestataires', 'email']]) {
    await t.test(field, async () => {
      const { wizard, select, confirmAll, calls } = await createWizard({
        type,
        post(url, body) {
          const result = report(body.get('file').name)
          const row = result.rows[0]
          if (field === 'idImport') row.idImport = 'same'
          else row.data[field] = field === 'email'
            ? (body.get('file').name === 'a.csv' ? 'Alice@Example.COM' : 'alice@example.com')
            : 'SITE-SAME'
          return result
        },
      })
      select(['a.csv', 'b.csv'])
      await wizard.analyze()
      confirmAll()
      assert.equal(wizard.conflicts.value[0].field, field)
      assert.equal(wizard.valid.value, false)
      assert.equal(wizard.canExecute.value, false)
      await wizard.execute()
      assert.equal(calls.length, 2)
    })
  }
})

test('analyze sends preview only, with the file but no digest or imported event', async () => {
  const { wizard, select, calls, events } = await createWizard()
  select(['a.csv', 'b.csv'])
  await wizard.analyze()
  assert.deepEqual(calls.map(({ url }) => url), ['/data-import/sites/preview', '/data-import/sites/preview'])
  for (const { body, config } of calls) {
    assert.ok(body instanceof FormData)
    assert.equal(body.has('digest'), false)
    assert.equal(config.headers['Content-Type'], 'multipart/form-data')
  }
  assert.deepEqual(calls.map(({ body }) => body.get('file').name), ['a.csv', 'b.csv'])
  assert.equal(wizard.step.value, 2)
  assert.equal(wizard.busy.value, false)
  assert.equal(wizard.attempted.value, false)
  assert.equal(importedEvents(events).length, 0)
})

test('execute sends each preview digest and original file as multipart, then refreshes once', async () => {
  const { wizard, select, confirmAll, calls, events } = await createWizard()
  select(['a.csv', 'b.csv'])
  await wizard.analyze()
  confirmAll()
  await wizard.execute()
  const execution = calls.filter(({ url }) => url.endsWith('/execute'))
  assert.equal(execution.length, 2)
  for (const [index, { url, body, config }] of execution.entries()) {
    assert.equal(url, '/data-import/sites/execute')
    assert.ok(body instanceof FormData)
    assert.equal(body.get('file'), wizard.batches.value[index].file)
    assert.equal(body.get('digest'), `digest-${body.get('file').name}`)
    assert.equal(config.headers['Content-Type'], 'multipart/form-data')
  }
  assert.ok(wizard.batches.value.every((batch) => batch.done))
  assert.equal(wizard.attempted.value, true)
  assert.equal(wizard.busy.value, false)
  assert.equal(wizard.canExecute.value, false)
  assert.deepEqual(structuredClone(importedEvents(events)), [['imported', { type: 'sites', created: 4 }]])
  await wizard.execute()
  assert.equal(calls.length, 4, 'cannot execute a second time without a new analysis')
})

test('execute stops at the first 422, retains earlier success and refreshes the table', async () => {
  const rejected = { ...report('b.csv', 'error'), valid: false }
  const { wizard, select, confirmAll, calls, events } = await createWizard({
    post(url, body) {
      const name = body.get('file').name
      if (url.endsWith('/preview')) return report(name)
      if (name === 'b.csv') throw { response: { status: 422, data: rejected } }
      return report(name, 'created')
    },
  })
  select(['a.csv', 'b.csv', 'c.csv'])
  await wizard.analyze()
  confirmAll()
  await wizard.execute()
  assert.deepEqual(calls.filter(({ url }) => url.endsWith('/execute')).map(({ body }) => body.get('file').name), ['a.csv', 'b.csv'])
  assert.equal(wizard.batches.value[0].done, true)
  assert.equal(wizard.batches.value[1].report, rejected)
  assert.ok(wizard.batches.value[1].error)
  assert.equal(wizard.batches.value[2].done, false)
  assert.match(wizard.error.value, /interrompu/)
  assert.equal(wizard.busy.value, false)
  assert.deepEqual(structuredClone(importedEvents(events)), [['imported', { type: 'sites', created: 2 }]])
})

test('a lost first execute response still triggers refresh and prevents subsequent requests', async () => {
  const { wizard, select, confirmAll, calls, events } = await createWizard({
    post(url, body) {
      if (url.endsWith('/execute')) throw new Error('Network Error')
      return report(body.get('file').name)
    },
  })
  select(['a.csv', 'b.csv'])
  await wizard.analyze()
  confirmAll()
  await wizard.execute()
  assert.equal(calls.filter(({ url }) => url.endsWith('/execute')).length, 1)
  assert.match(wizard.batches.value[0].error, /non confirmée/)
  assert.equal(wizard.attempted.value, true)
  assert.equal(wizard.busy.value, false)
  assert.deepEqual(structuredClone(importedEvents(events)), [['imported', { type: 'sites', created: 0 }]])
})

test('new analysis clears confirmations, attempted and stale reports before awaiting preview', async () => {
  let resolvePreview
  let pending = false
  const { wizard, select, confirmAll, events } = await createWizard({
    post(url, body) {
      if (pending) return new Promise((resolve) => { resolvePreview = resolve })
      return report(body.get('file').name, url.endsWith('/execute') ? 'created' : 'ready')
    },
  })
  select()
  await wizard.analyze()
  confirmAll()
  await wizard.execute()
  assert.equal(wizard.attempted.value, true)
  assert.ok(wizard.confirmed.value.length)
  wizard.error.value = 'stale error'
  wizard.batches.value[0].error = 'stale batch error'
  pending = true
  const analysis = wizard.analyze()
  assert.equal(wizard.confirmed.value.length, 0)
  assert.equal(wizard.attempted.value, false)
  assert.equal(wizard.error.value, '')
  assert.equal(wizard.batches.value[0].report, null)
  assert.equal(wizard.batches.value[0].error, '')
  assert.equal(wizard.batches.value[0].done, false)
  assert.equal(wizard.busy.value, true)
  assert.equal(wizard.canExecute.value, false)
  resolvePreview(report('a.csv'))
  await analysis
  assert.equal(wizard.busy.value, false)
  assert.equal(wizard.valid.value, true)
  assert.equal(wizard.canExecute.value, false, 'fresh reports require fresh confirmations')
  assert.equal(importedEvents(events).length, 1, 'analysis must not emit another refresh')
})
