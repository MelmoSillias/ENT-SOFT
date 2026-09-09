/**
 * Initials from a person display name (max 2 letters).
 * @param {string|null|undefined} name
 * @returns {string}
 */
export function personInitials(name) {
  const parts = String(name || '')
    .trim()
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
  if (!parts.length) {
    return '?'
  }
  return parts.map((part) => part[0]?.toUpperCase() || '').join('')
}

/**
 * Build display name from person-like object.
 * @param {{ prenom?: string, nom?: string, name?: string, login?: string }|null|undefined} person
 * @param {string} [fallback='—']
 */
export function personDisplayName(person, fallback = '—') {
  if (!person) {
    return fallback
  }
  if (person.name?.trim()) {
    return person.name.trim()
  }
  const full = [person.prenom, person.nom].filter(Boolean).join(' ').trim()
  if (full) {
    return full
  }
  if (person.login?.trim()) {
    return person.login.trim()
  }
  return fallback
}
