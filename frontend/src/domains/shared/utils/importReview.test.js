import assert from 'node:assert/strict'
import { test } from 'node:test'
import { getImportConflicts } from './importReview.js'

function row(idImport, fileName, line, data = {}, status = 'ready') {
  return { idImport, fileName, line, data, status }
}

test('returns no conflicts for empty input or distinct keys', () => {
  assert.deepEqual(getImportConflicts('sites', []), [])
  assert.deepEqual(getImportConflicts('sites', [
    row('a', 'a.csv', 2, { code: 'SITE-A' }),
    row('b', 'b.csv', 2, { code: 'SITE-B' }),
  ]), [])
})

test('groups repeated idImport across all statuses with readable file and line locations', () => {
  const rows = [
    row('same', 'a.csv', 2, {}, 'skipped'),
    row('same', 'b.csv', 4, {}, 'error'),
    row('same', 'c.csv', 7, {}, 'created'),
    row('same', 'd.csv', 9),
  ]
  assert.deepEqual(getImportConflicts('clients', rows), [{
    field: 'idImport',
    value: 'same',
    rows: [
      { fileName: 'a.csv', line: 2 },
      { fileName: 'b.csv', line: 4 },
      { fileName: 'c.csv', line: 7 },
      { fileName: 'd.csv', line: 9 },
    ],
    message: 'idImport « same » répété : a.csv, ligne 2 ; b.csv, ligne 4 ; c.csv, ligne 7 ; d.csv, ligne 9.',
  }])
})

test('detects repeated ready site codes across files', () => {
  assert.deepEqual(getImportConflicts('sites', [
    row('a', 'first.csv', 2, { code: 'SITE-1' }),
    row('b', 'second.csv', 5, { code: 'SITE-1' }),
  ]), [{
    field: 'code',
    value: 'SITE-1',
    rows: [{ fileName: 'first.csv', line: 2 }, { fileName: 'second.csv', line: 5 }],
    message: 'code « SITE-1 » répété : first.csv, ligne 2 ; second.csv, ligne 5.',
  }])
})

test('normalizes prestataire emails to lowercase', () => {
  assert.deepEqual(getImportConflicts('prestataires', [
    row('a', 'first.csv', 2, { email: 'Alice@Example.COM' }),
    row('b', 'second.csv', 3, { email: 'alice@example.com' }),
  ]), [{
    field: 'email',
    value: 'alice@example.com',
    rows: [{ fileName: 'first.csv', line: 2 }, { fileName: 'second.csv', line: 3 }],
    message: 'email « alice@example.com » répété : first.csv, ligne 2 ; second.csv, ligne 3.',
  }])
})

test('ignores natural keys on skipped, error and created rows', () => {
  for (const [type, data] of [
    ['sites', { code: 'SITE-1' }],
    ['prestataires', { email: 'alice@example.com' }],
  ]) {
    assert.deepEqual(getImportConflicts(type, [
      row('a', 'ready.csv', 2, data),
      row('b', 'skipped.csv', 2, data, 'skipped'),
      row('c', 'error.csv', 2, data, 'error'),
      row('d', 'created.csv', 2, data, 'created'),
    ]), [])
  }
})

test('keeps site codes case-sensitive and only checks natural keys for their type', () => {
  assert.deepEqual(getImportConflicts('sites', [
    row('a', 'a.csv', 2, { code: 'SITE-A' }),
    row('b', 'b.csv', 2, { code: 'site-a' }),
  ]), [])
  assert.deepEqual(getImportConflicts('clients', [
    row('a', 'a.csv', 2, { code: 'same', email: 'same@example.com' }),
    row('b', 'b.csv', 2, { code: 'same', email: 'same@example.com' }),
  ]), [])
})

test('ignores missing and empty keys', () => {
  for (const type of ['sites', 'prestataires']) {
    assert.deepEqual(getImportConflicts(type, [
      row(undefined, 'a.csv', 2),
      row(undefined, 'b.csv', 2),
      row('', 'c.csv', 2, { code: '', email: '' }),
      row('', 'd.csv', 2, { code: '', email: '' }),
    ]), [])
  }
})

test('reports both conflicts within one file without mutating input', () => {
  const rows = [
    row('same', 'a.csv', 2, { code: 'SITE-1' }),
    row('same', 'a.csv', 3, { code: 'SITE-1' }),
  ]
  const before = structuredClone(rows)
  assert.deepEqual(getImportConflicts('sites', rows).map(({ field }) => field), ['idImport', 'code'])
  assert.deepEqual(rows, before)
})
