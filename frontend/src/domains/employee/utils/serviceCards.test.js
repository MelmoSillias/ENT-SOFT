import assert from 'node:assert/strict'
import { test } from 'node:test'
import { buildServiceCardsDocument, CARD_TEMPLATES } from './serviceCards.js'

const brand = { name: 'Entreprise & associés', shortName: 'ENT', logoUrl: '/branding/logo.png' }
const people = Array.from({ length: 19 }, (_, index) => ({
  id: index, kind: index % 2 ? 'employee' : 'prestataire', displayName: `Personne ${index}`,
  roleLabel: 'Technicien', photoUrl: '/uploads/photo.png', phone: '+224 000 000',
}))
const options = { template: 'ocean', orientation: 'landscape', medium: 'paper', withPhoto: true, cutMarks: true }

test('all templates/orientations paginate complete mixed groups without losing or duplicating cards', () => {
  for (const template of CARD_TEMPLATES) {
    for (const orientation of ['landscape', 'portrait']) {
      for (const medium of ['paper', 'pvc']) {
        for (const withPhoto of [true, false]) {
          const html = buildServiceCardsDocument(people, { ...options, template: template.value, orientation, medium, withPhoto }, brand, '')
          assert.equal((html.match(/<article /g) || []).length, 19)
          assert.equal((html.match(/<section class="card-sheet">/g) || []).length, medium === 'pvc' ? 19 : 3)
          for (const person of people) assert.equal(html.split(`<strong>${person.displayName}</strong>`).length - 1, 1)
          assert.equal((html.match(/alt="Photo de /g) || []).length, withPhoto ? 19 : 0)
          assert.match(html, /Employé/)
          assert.match(html, /Prestataire/)
          if (medium === 'pvc') {
            assert.ok(html.includes(`@page { size: ${orientation === 'portrait' ? '53.98mm 85.6mm' : '85.6mm 53.98mm'}; margin: 0; }`))
            assert.ok(!html.includes('outline: 0.15mm dashed'))
          } else assert.match(html, /@page \{ size: A4 portrait/)
        }
      }
    }
  }
})

test('missing or unusable photos block photo printing but allow cards without photos', () => {
  for (const photoUrl of [null, '', '   ', 'javascript:alert(1)']) {
    const group = [people[0], { ...people[1], photoUrl }]
    assert.throws(() => buildServiceCardsDocument(group, options, brand, ''), /photo exploitable/)
    assert.doesNotThrow(() => buildServiceCardsDocument(group, { ...options, withPhoto: false }, brand, ''))
  }
  assert.throws(() => buildServiceCardsDocument([], options, brand, ''), /Sélectionnez/)
})

test('names, organization, roles and image attributes cannot inject markup into preview/print', () => {
  const malicious = { ...people[0], displayName: '<script>alert(1)</script>', roleLabel: '<img onerror="bad">', photoUrl: '/uploads/photo.png" onerror="bad' }
  const html = buildServiceCardsDocument([malicious], options, brand, '')
  assert.ok(!html.includes('<script>'))
  assert.ok(!html.includes('<img onerror='))
  assert.ok(!html.includes('src="/uploads/photo.png" onerror='))
  assert.match(html, /&lt;script&gt;/)
  assert.match(html, /Entreprise &amp; associés/)
})

test('paper crop guides can be disabled', () => {
  assert.match(buildServiceCardsDocument([people[0]], options, brand, ''), /outline: 0.15mm dashed/)
  assert.ok(!buildServiceCardsDocument([people[0]], { ...options, cutMarks: false }, brand, '').includes('outline: 0.15mm dashed'))
})
