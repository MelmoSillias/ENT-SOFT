export const CARD_TEMPLATES = [
  { value: 'waves', label: 'Vagues océanes', description: 'Vagues superposées bleu marine et vert du logo' },
  { value: 'ribbon', label: 'Rubans diagonaux', description: 'Rubans obliques aux couleurs de l’entreprise' },
  { value: 'contours', label: 'Courbes concentriques', description: 'Arcs verts et bleu marine sur fond clair' },
  { value: 'circuit', label: 'Réseau numérique', description: 'Trame technique et connexions vertes' },
  { value: 'mosaic', label: 'Mosaïque', description: 'Facettes géométriques bleu marine et vert' },
  { value: 'aurora', label: 'Aurore', description: 'Halos doux aux couleurs du logo' },
  { value: 'corporate', label: 'Corporate', description: 'Bandeau bleu marine et accents verts' },
  { value: 'signature', label: 'Signature', description: 'Fond clair, détails verts et portrait arrondi' },
  { value: 'studio', label: 'Studio', description: 'Composition graphique bleu marine et vert' },
  { value: 'ocean', label: 'Horizon', description: 'Bleu marine et accent vert' },
  { value: 'emerald', label: 'Émeraude', description: 'Vert contemporain et lignes épurées' },
  { value: 'minimal', label: 'Essentiel', description: 'Sobre, idéal pour le papier' },
]

export function escapeHtml(value) {
  return String(value ?? '').replace(/[&<>"']/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[char])
}

function imageUrl(value) {
  const url = String(value || '').trim()
  return /^(https?:\/\/|\/(?!\/)|\.\.?\/|data:image\/(png|jpeg|webp);base64,)/i.test(url) ? escapeHtml(url) : ''
}

export function cardDimensions(orientation) {
  return orientation === 'portrait' ? { width: 53.98, height: 85.6 } : { width: 85.6, height: 53.98 }
}

export function cardsPerPage(orientation, medium) {
  return medium === 'pvc' ? 1 : orientation === 'portrait' ? 9 : 8
}

// All variable content is escaped: this HTML is shared by the preview and print document.
export function renderServiceCard(person, options, brand) {
  const template = CARD_TEMPLATES.some((item) => item.value === options.template) ? options.template : 'ocean'
  const orientation = options.orientation === 'portrait' ? 'portrait' : 'landscape'
  const logo = imageUrl(brand.logoUrl)
  const photo = imageUrl(person.photoUrl)
  const label = person.kind === 'employee' ? 'Employé' : 'Prestataire'
  return `<article class="service-card service-card--${template} service-card--${orientation}${options.withPhoto ? '' : ' service-card--no-photo'}">
    <header class="service-card__brand">${logo ? `<img src="${logo}" alt="Logo de ${escapeHtml(brand.name)}" class="service-card__logo">` : ''}<div class="service-card__brand-text"><span class="service-card__brand-name">${escapeHtml(brand.name)}</span>${orientation === 'landscape' ? '<div class="service-card__heading">CARTE DE SERVICE</div>' : ''}</div></header>
    ${orientation === 'portrait' ? '<div class="service-card__heading">CARTE DE SERVICE</div>' : ''}
    <div class="service-card__body">
      ${options.withPhoto ? `<div class="service-card__photo">${photo ? `<img src="${photo}" alt="Photo de ${escapeHtml(person.displayName)}">` : '<span>Photo manquante</span>'}</div>` : ''}
      <div class="service-card__identity"><strong>${escapeHtml(person.displayName)}</strong>${person.mention ? `<span class="service-card__role">${escapeHtml(person.mention)}</span>` : ''}<span class="service-card__type">${label}</span>
      ${options.showEmployeePhone && person.phone ? `<span class="service-card__personal">Tél. personnel : ${escapeHtml(person.phone)}</span>` : ''}
      ${options.showEmployeeEmail && person.email ? `<span class="service-card__personal">Email personnel : ${escapeHtml(person.email)}</span>` : ''}</div>
    </div>
    <footer class="service-card__footer"><strong>${escapeHtml(brand.name)}</strong>
      ${[ [brand.address, brand.city].filter(Boolean).join(', '), [brand.phone, brand.email].filter(Boolean).join(' · '), brand.website ].filter(Boolean).map((line) => `<span>${escapeHtml(line)}</span>`).join('')}
    </footer>
  </article>`
}

export function buildServiceCardsDocument(people, options, brand, css) {
  if (!people.length) throw new Error('Sélectionnez au moins une personne.')
  if (options.withPhoto && people.some((person) => !imageUrl(person.photoUrl))) {
    throw new Error('Certaines personnes sélectionnées n’ont pas de photo exploitable.')
  }
  const { width, height } = cardDimensions(options.orientation)
  const pvc = options.medium === 'pvc'
  const perPage = cardsPerPage(options.orientation, options.medium)
  const pages = []
  for (let index = 0; index < people.length; index += perPage) {
    pages.push(`<section class="card-sheet">${people.slice(index, index + perPage).map((person) => `<div class="card-slot">${renderServiceCard(person, options, brand)}</div>`).join('')}</section>`)
  }
  return `<!doctype html><html lang="fr"><head><meta charset="utf-8"><title>Cartes de service</title><style>${css}
    @page { size: ${pvc ? `${width}mm ${height}mm` : 'A4 portrait'}; margin: 0; }
    * { box-sizing: border-box; } html, body { margin: 0; padding: 0; background: white; }
    .card-sheet { width: ${pvc ? width : 210}mm; height: ${pvc ? height : 297}mm; padding: ${pvc ? 0 : 10}mm; display: grid; grid-template-columns: repeat(${pvc ? 1 : options.orientation === 'portrait' ? 3 : 2}, ${width}mm); grid-auto-rows: ${height}mm; gap: ${pvc ? 0 : 4}mm; align-content: start; justify-content: center; break-after: page; page-break-after: always; }
    .card-sheet:last-child { break-after: auto; page-break-after: auto; }
    .card-slot { width: ${width}mm; height: ${height}mm; ${!pvc && options.cutMarks ? 'outline: 0.15mm dashed #777; outline-offset: 0.6mm;' : ''} }
    .card-sheet .service-card { border-radius: 0; }
    @media print { body { print-color-adjust: exact; -webkit-print-color-adjust: exact; } }
    </style></head><body>${pages.join('')}</body></html>`
}
