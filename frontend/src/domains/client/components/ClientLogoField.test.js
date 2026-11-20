import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { test } from 'node:test'
import vm from 'node:vm'

const source = readFileSync(new URL('./ClientLogoField.vue', import.meta.url), 'utf8')
const setup = source.match(/<script setup>([\s\S]*?)<\/script>/)[1]
// Exercise the actual setup logic without adding a browser test dependency.
const script = new vm.Script(setup.replace(/^import .*$/gm, '') + '\nglobalThis.field = { changeLogo, selectFile, busy, error }')

function field({ editable = true, upload, remove } = {}) {
  const calls = []
  const events = []
  const context = vm.createContext({
    ref: (value) => ({ value }),
    useId: () => 'client-logo-test',
    defineProps: () => ({ client: { id: 'client-1', title: 'Client', logoUrl: '/old.png' }, editable }),
    defineEmits: () => (...args) => events.push(args),
    uploadClientLogo: async (id, file) => {
      calls.push(['upload', id, file])
      return upload ? upload() : { id, logoUrl: '/new.png' }
    },
    deleteClientLogo: async (id) => {
      calls.push(['delete', id])
      return remove ? remove() : { id, logoUrl: null }
    },
  })
  script.runInContext(context, { timeout: 1000 })
  return { ...context.field, calls, events }
}

const png = { type: 'image/png', size: 128 }

test('upload, replace and delete emit the persisted client only', async () => {
  const logo = field()
  const event = { target: { files: [png], value: 'selected' } }
  await logo.selectFile(event)
  assert.equal(event.target.value, '')
  assert.equal(logo.calls[0][0], 'upload')
  assert.equal(logo.calls[0][1], 'client-1')
  assert.equal(logo.events[0][0], 'updated')
  assert.equal(logo.events[0][1].logoUrl, '/new.png')
  await logo.changeLogo()
  assert.equal(logo.calls[1][0], 'delete')
  assert.equal(logo.events[1][1].logoUrl, null)
  assert.equal(logo.busy.value, false)
})

test('rejects unsupported and oversized images before contacting the API', async () => {
  const logo = field()
  for (const file of [{ type: 'image/svg+xml', size: 10 }, { type: 'image/png', size: 2097153 }]) {
    await logo.changeLogo(file)
    assert.match(logo.error.value, /2 Mo/)
  }
  assert.equal(logo.calls.length, 0)
})

test('read-only clients cannot upload or delete', async () => {
  const logo = field({ editable: false })
  await logo.changeLogo(png)
  await logo.changeLogo()
  assert.equal(logo.calls.length, 0)
  assert.equal(logo.events.length, 0)
})

test('failed upload preserves displayed logo and exposes the API error', async () => {
  const logo = field({ upload: () => { throw { response: { data: { error: 'Image invalide' } } } } })
  await logo.changeLogo(png)
  assert.equal(logo.error.value, 'Image invalide')
  assert.equal(logo.events.length, 0)
  assert.equal(logo.busy.value, false)
})

test('prevents overlapping uploads and deletions', async () => {
  let resolve
  const logo = field({ upload: () => new Promise((done) => { resolve = done }) })
  const pending = logo.changeLogo(png)
  assert.equal(logo.busy.value, true)
  await logo.changeLogo()
  await logo.changeLogo(png)
  assert.equal(logo.calls.length, 1)
  resolve({ logoUrl: '/new.png' })
  await pending
  assert.equal(logo.busy.value, false)
})
