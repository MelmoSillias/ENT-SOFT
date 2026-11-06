import assert from 'node:assert/strict'
import { test } from 'node:test'
import { buildServiceCardsDocument, renderServiceCard, CARD_TEMPLATES } from './serviceCards.js'

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

test('cards show the mention instead of the role and company coordinates by default', () => {
  const person = { ...people[1], mention: 'Responsable terrain', email: 'personnel@example.com' }
  const company = { ...brand, phone: '+224 111 222', email: 'agence@example.com', address: '10 rue du Siège', city: 'Conakry', website: 'https://entreprise.example.com' }
  const html = renderServiceCard(person, options, company)
  assert.match(html, /Responsable terrain/)
  assert.ok(!html.includes('Technicien'))
  assert.ok(!html.includes(person.phone))
  assert.ok(!html.includes(person.email))
  for (const value of [company.phone, company.email, company.address, company.city, company.website]) assert.ok(html.includes(value))
  assert.ok(!renderServiceCard({ ...person, mention: '' }, options, company).includes('Technicien'))
})

test('personal phone and email are independently optional in previews and print', () => {
  const person = { ...people[1], email: 'personnel@example.com' }
  for (const showEmployeePhone of [true, false]) {
    for (const showEmployeeEmail of [true, false]) {
      const settings = { ...options, showEmployeePhone, showEmployeeEmail }
      for (const html of [renderServiceCard(person, settings, brand), buildServiceCardsDocument([person], settings, brand, '')]) {
        assert.equal(html.includes(person.phone), showEmployeePhone)
        assert.equal(html.includes(person.email), showEmployeeEmail)
      }
    }
  }
})

test('mention and all contact fields are escaped', () => {
  const payload = '<img src=x onerror=alert(1)>'
  const html = renderServiceCard({ ...people[1], mention: payload, email: payload, phone: payload }, { ...options, showEmployeePhone: true, showEmployeeEmail: true }, { ...brand, address: payload, city: payload, phone: payload, email: payload, website: payload })
  assert.ok(!html.includes(payload))
  assert.match(html, /&lt;img src=x onerror=alert\(1\)&gt;/)
})

test('all A4 templates print with square outer corners', () => {
  for (const template of CARD_TEMPLATES) {
    for (const orientation of ['landscape', 'portrait']) {
      const html = buildServiceCardsDocument([people[1]], { ...options, template: template.value, orientation }, brand, '.service-card { border-radius: 3mm; }')
      assert.match(html, /\.card-sheet \.service-card \{ border-radius: 0; \}/)
    }
  }
})


test('employee Social ID is escaped and appears in preview and print for every layout', () => {
  const person = { ...people[1], socialId: '00123<&>' }
  for (const template of CARD_TEMPLATES) {
    for (const orientation of ['landscape', 'portrait']) {
      const settings = { ...options, template: template.value, orientation }
      for (const html of [renderServiceCard(person, settings, brand), buildServiceCardsDocument([person], settings, brand, '')]) {
        assert.match(html, /Social ID : 00123&lt;&amp;&gt;/)
        assert.ok(!html.includes(person.socialId))
      }
    }
  }
  for (const socialId of [null, undefined, '']) {
    assert.ok(!renderServiceCard({ ...person, socialId }, options, brand).includes('Social ID'))
  }
  assert.ok(!renderServiceCard({ ...person, kind: 'prestataire' }, options, brand).includes('Social ID'))
})
