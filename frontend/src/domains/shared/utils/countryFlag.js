/**
 * Drapeaux pays — emoji Unicode + CDN Flagpedia (https://flagcdn.com).
 * Inclut les codes spéciaux (ex. EU pour l'euro) absents de REST Countries.
 */

const DEVISE_PAYS = {
  XOF: 'SN',
  XAF: 'CM',
  EUR: 'EU',
  USD: 'US',
  GBP: 'GB',
  JPY: 'JP',
  CNY: 'CN',
  AED: 'AE',
  CHF: 'CH',
  CAD: 'CA',
  AUD: 'AU',
  INR: 'IN',
  BRL: 'BR',
  ZAR: 'ZA',
  TRY: 'TR',
  KRW: 'KR',
  SGD: 'SG',
  HKD: 'HK',
  NZD: 'NZ',
  SEK: 'SE',
  NOK: 'NO',
  DKK: 'DK',
  MXN: 'MX',
}

/** @param {string} countryCode ISO 3166-1 alpha-2 (ou EU pour l'Union européenne) */
export function countryFlagEmoji(countryCode) {
  if (!countryCode || String(countryCode).length !== 2) {
    return '🏳️'
  }
  const code = String(countryCode).toUpperCase()
  const base = 0x1f1e6
  return [...code].map((char) => String.fromCodePoint(base + char.charCodeAt(0) - 65)).join('')
}

/** Tailles PNG disponibles sur flagcdn.com (format largeur×hauteur, ratio 4:3). */
const FLAGCDN_WIDTHS = [16, 20, 24, 28, 32, 36, 40, 48, 56, 60, 64, 72, 80, 84, 96, 108, 112, 120, 128, 144, 160, 192, 224, 256]

function flagcdnDimensions(width) {
  const w = Math.max(16, Math.round(Number(width) || 40))
  const bucket = FLAGCDN_WIDTHS.find((size) => size >= w) ?? 256
  const h = Math.round((bucket * 3) / 4)
  return `${bucket}x${h}`
}

/** @param {string} countryCode @param {number} [width=40] */
export function countryFlagUrl(countryCode, width = 40) {
  if (!countryCode || String(countryCode).length !== 2) {
    return ''
  }
  const code = String(countryCode).toLowerCase()
  return `https://flagcdn.com/${flagcdnDimensions(width)}/${code}.png`
}

export function deviseFlagCode(deviseCode) {
  return DEVISE_PAYS[String(deviseCode || '').toUpperCase()] || null
}

export function deviseFlagEmoji(deviseCode) {
  const code = deviseFlagCode(deviseCode)
  return code ? countryFlagEmoji(code) : '💱'
}
