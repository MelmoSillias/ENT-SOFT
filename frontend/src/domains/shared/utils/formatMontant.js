/**
 * Formate un montant selon les métadonnées devise.
 * @param {number|string} value
 * @param {object} deviseMeta - { code, nom, symbole, decimales, mode_arrondi, unite_arrondi, compact }
 */

const KILO_CODES = new Set([
  'XOF', 'XAF', 'GNF', 'BIF', 'DJF', 'RWF', 'UGX', 'VND', 'JPY', 'KRW', 'CLP', 'ISK', 'PYG', 'MGA', 'KMF',
])

function normalizeMeta(deviseMeta = {}) {
  const code = String(deviseMeta.code || deviseMeta.deviseCode || deviseMeta.devise_code || '').toUpperCase()
  const isCfa = code === 'XOF' || code === 'XAF'
  const decimales = deviseMeta.decimales ?? (isCfa || KILO_CODES.has(code) ? 0 : 2)
  const modeArrondi = deviseMeta.mode_arrondi || deviseMeta.modeArrondi || (isCfa ? 'UNITE' : 'HALF_UP')
  const uniteArrondi = deviseMeta.unite_arrondi ?? deviseMeta.uniteArrondi ?? (isCfa ? '5' : null)

  return {
    code,
    nom: deviseMeta.nom || deviseMeta.deviseNom || deviseMeta.devise_nom || code,
    symbole: deviseMeta.symbole || deviseMeta.deviseSymbole || deviseMeta.devise_symbole || code,
    decimales,
    mode_arrondi: modeArrondi,
    unite_arrondi: uniteArrondi,
    compact: Boolean(deviseMeta.compact),
  }
}

function roundMontant(num, meta) {
  if (meta.mode_arrondi === 'UNITE') {
    const unite = Number(meta.unite_arrondi || 1) || 1
    return Math.round(num / unite) * unite
  }
  const factor = 10 ** (meta.decimales ?? 2)
  return Math.round(num * factor) / factor
}

function currencySuffix(meta) {
  const { code, nom, symbole } = meta
  if (code === 'XOF' || code === 'XAF') {
    return !nom || nom === code || nom.includes('Franc') ? 'Francs' : nom
  }
  if (['EUR', 'USD', 'GBP', 'AED'].includes(code)) {
    return symbole || code
  }
  return symbole || nom || code
}

function usesKiloAbbreviation(meta) {
  return meta.decimales === 0 || meta.mode_arrondi === 'UNITE' || KILO_CODES.has(meta.code)
}

/**
 * Abrège un nombre : K (milliers, devises sans décimales), M, Md.
 * @returns {string|null} null si le montant reste en format complet
 */
function abbreviateNumber(num, meta) {
  const abs = Math.abs(num)
  const sign = num < 0 ? '−' : ''
  let divisor = 0
  let suffix = ''

  if (abs >= 1_000_000_000) {
    divisor = 1_000_000_000
    suffix = 'Md'
  } else if (abs >= 1_000_000) {
    divisor = 1_000_000
    suffix = 'M'
  } else if (usesKiloAbbreviation(meta) && abs >= 1_000) {
    divisor = 1_000
    suffix = 'K'
  } else {
    return null
  }

  const compact = abs / divisor
  const formatted = new Intl.NumberFormat('fr-FR', {
    minimumFractionDigits: 0,
    maximumFractionDigits: compact >= 10 ? 0 : 1,
  }).format(compact)

  return `${sign}${formatted} ${suffix}`
}

function formatFullNumber(rounded, meta) {
  if (meta.mode_arrondi === 'UNITE') {
    return new Intl.NumberFormat('fr-FR', {
      maximumFractionDigits: 0,
      useGrouping: true,
    }).format(rounded).replace(/\s/g, '.')
  }

  return new Intl.NumberFormat('fr-FR', {
    minimumFractionDigits: meta.decimales,
    maximumFractionDigits: meta.decimales,
  }).format(rounded)
}

export function formatMontant(value, deviseMeta = {}) {
  const num = Number(value)
  if (Number.isNaN(num)) return String(value ?? '')

  const meta = normalizeMeta(deviseMeta)
  const rounded = roundMontant(num, meta)
  const suffix = currencySuffix(meta)

  if (meta.compact) {
    const abbreviated = abbreviateNumber(rounded, meta)
    if (abbreviated) return `${abbreviated} ${suffix}`.trim()
  }

  return `${formatFullNumber(rounded, meta)} ${suffix}`.trim()
}

/**
 * Format compact (K / M / Md) selon les règles d'abréviation de la devise.
 */
export function formatMontantAbrege(value, deviseMeta = {}) {
  return formatMontant(value, { ...deviseMeta, compact: true })
}

/**
 * Formate à partir d'un label API pré-calculé ou fallback local.
 */
export function formatMontantLabel(item, field = 'montantSource') {
  const labelField = field === 'montantSource' ? 'montantSourceLabel' : 'montantDestinationLabel'
  if (item?.[labelField] && !item?.compact) return item[labelField]

  const codeField = field === 'montantSource' ? 'deviseSourceCode' : 'deviseDestinationCode'
  const amountField = field === 'montantSource' ? 'montantSource' : 'montantDestination'
  const symboleField = field === 'montantSource' ? 'deviseSourceSymbole' : 'deviseDestinationSymbole'
  return formatMontant(item?.[amountField], {
    code: item?.[codeField],
    symbole: item?.[symboleField],
    compact: item?.compact,
  })
}

export function formatMontantLabelAbrege(item, field = 'montantSource') {
  return formatMontantLabel({ ...item, compact: true }, field)
}

/**
 * Index des devises par code pour lookup rapide.
 * @param {Array} devises
 */
export function devisesByCode(devises = []) {
  return Object.fromEntries(
    devises.map((d) => [String(d.code).toUpperCase(), d]),
  )
}
