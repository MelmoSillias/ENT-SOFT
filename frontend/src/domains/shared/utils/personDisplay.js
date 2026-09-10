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

/**
 * @param {{ photoUrl?: string|null, avatar?: string|null }|null|undefined} person
 * @returns {string|null}
 */
export function personPhotoUrl(person) {
  if (!person) return null
  const url = String(person.photoUrl || person.avatar || '').trim()
  return url || null
}

const USER_ROLE_LABELS = {
  ADMIN: 'Administrateur',
  SUPERVISEUR: 'Superviseur',
  AGENT: 'Agent',
}

/**
 * @param {'employee'|'prestataire'|'user'|'auto'|string|null|undefined} kind
 * @param {object|null|undefined} person
 * @returns {'employee'|'prestataire'|'user'}
 */
export function resolvePersonKind(kind, person) {
  if (kind === 'employee' || kind === 'prestataire' || kind === 'user') {
    return kind
  }
  if (!person) return 'employee'
  if (person.login != null || person.role != null) return 'user'
  if (person.roleCode != null || person.function != null || person.mention != null || person.userId != null) {
    return 'employee'
  }
  return 'prestataire'
}

/**
 * Identity rows for the avatar preview dialog.
 * @param {object|null|undefined} person
 * @param {'employee'|'prestataire'|'user'|'auto'} [kind='auto']
 * @returns {{ key: string, label: string, icon?: string, value?: string|null, full?: boolean }[]}
 */
export function personPreviewItems(person, kind = 'auto') {
  if (!person) return []

  const resolved = resolvePersonKind(kind, person)
  const phone = person.phone ?? person.telephone ?? null
  const items = []

  if (resolved === 'user') {
    items.push(
      { key: 'login', label: 'Identifiant', icon: 'pi pi-at', value: person.login || null },
      { key: 'telephone', label: 'Téléphone', icon: 'pi pi-phone', value: phone },
      {
        key: 'role',
        label: 'Rôle',
        icon: 'pi pi-shield',
        value: USER_ROLE_LABELS[person.role] || person.role || null,
      },
    )
    if (person.isActive != null || person.isEnabled != null) {
      const active = person.isActive != null ? person.isActive !== false : person.isEnabled !== false
      items.push({
        key: 'status',
        label: 'Statut',
        icon: 'pi pi-info-circle',
        value: active ? 'Actif' : 'Inactif',
      })
    }
    return items.filter((item) => item.value != null && String(item.value).trim() !== '')
  }

  if (resolved === 'employee') {
    items.push(
      { key: 'email', label: 'Email', icon: 'pi pi-envelope', value: person.email || null },
      { key: 'phone', label: 'Téléphone', icon: 'pi pi-phone', value: phone },
      {
        key: 'role',
        label: 'Fonction',
        icon: 'pi pi-id-card',
        value: person.roleCode || person.function || null,
      },
      { key: 'mention', label: 'Mention', icon: 'pi pi-briefcase', value: person.mention || null },
    )
  } else {
    items.push(
      { key: 'email', label: 'Email', icon: 'pi pi-envelope', value: person.email || null },
      { key: 'phone', label: 'Téléphone', icon: 'pi pi-phone', value: phone },
    )
  }

  if (person.address) {
    items.push({ key: 'address', label: 'Adresse', icon: 'pi pi-map-marker', value: person.address, full: true })
  }

  if (person.isEnabled != null) {
    items.push({
      key: 'status',
      label: 'Statut',
      icon: 'pi pi-info-circle',
      value: person.isEnabled ? 'Actif' : 'Inactif',
    })
  }

  return items.filter((item) => item.value != null && String(item.value).trim() !== '')
}
