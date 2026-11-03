export const CARD_TEMPLATES = [
  { value: 'ocean', label: 'Horizon', description: 'Bleu profond et accent turquoise' },
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
    <header class="service-card__brand">${logo ? `<img src="${logo}" alt="" class="service-card__logo">` : ''}<span>${escapeHtml(brand.name)}</span></header>
    <div class="service-card__heading">CARTE DE SERVICE</div>
    <div class="service-card__body">
      ${options.withPhoto ? `<div class="service-card__photo">${photo ? `<img src="${photo}" alt="Photo de ${escapeHtml(person.displayName)}">` : '<span>Photo manquante</span>'}</div>` : ''}
      <div class="service-card__identity"><strong>${escapeHtml(person.displayName)}</strong><span class="service-card__role">${escapeHtml(person.roleLabel || label)}</span><span class="service-card__type">${label}</span></div>
    </div>
    <footer class="service-card__footer"><span>${escapeHtml(person.phone || '')}</span><span>${escapeHtml(brand.shortName || brand.name)}</span></footer>
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
    .service-card { border-radius: ${pvc ? 0 : 3}mm; }
    @media print { body { print-color-adjust: exact; -webkit-print-color-adjust: exact; } }
    </style></head><body>${pages.join('')}</body></html>`
}
