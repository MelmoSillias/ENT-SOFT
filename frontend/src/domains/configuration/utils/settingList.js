/**
 * Serialize / parse listes stockées dans settings (JSON array de chaînes).
 */

export function parseSettingList(raw) {
  if (Array.isArray(raw)) {
    return normalizeList(raw)
  }
  const text = String(raw ?? '').trim()
  if (!text) return []
  try {
    const parsed = JSON.parse(text)
    if (Array.isArray(parsed)) return normalizeList(parsed)
  } catch {
    // fallback: une entrée par ligne ou séparée par |
  }
  return normalizeList(
    text.includes('\n') ? text.split(/\r?\n/) : text.split('|'),
  )
}

export function serializeSettingList(items) {
  return JSON.stringify(normalizeList(items))
}

function normalizeList(items) {
  const seen = new Set()
  const out = []
  for (const item of items ?? []) {
    const value = String(item ?? '')
      .replace(/[\u0000-\u001F\u007F]/g, '')
      .trim()
    if (!value) continue
    const key = value.toLowerCase()
    if (seen.has(key)) continue
    seen.add(key)
    out.push(value)
  }
  return out
}
