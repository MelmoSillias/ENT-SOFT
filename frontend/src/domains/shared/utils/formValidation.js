export function hasRequiredText(value) {
  return typeof value === 'string' && value.trim().length > 0
}

/** Digits only (for comparison / length checks). */
export function normalizePhoneDigits(value) {
  return String(value ?? '').replace(/\D/g, '')
}

/**
 * Keep optional leading '+', digits, and single spaces.
 * No space at start, after '+', or after another space.
 */
export function sanitizePhoneInput(value) {
  let out = ''
  for (const ch of String(value ?? '')) {
    if (ch >= '0' && ch <= '9') {
      out += ch
      continue
    }
    if (ch === '+' && out.length === 0) {
      out += ch
      continue
    }
    if (ch === ' ') {
      if (out.length === 0) continue
      const last = out[out.length - 1]
      if (last === '+' || last === ' ') continue
      out += ' '
    }
  }
  return out.slice(0, 20)
}

/** Format: optional leading '+', digits, spaces (never leading / after + / doubled). */
export function hasValidPhone(value) {
  if (!hasRequiredText(value)) {
    return false
  }

  const trimmed = value.trimEnd()
  if (!/^(?:\+[0-9]+|[0-9]+)(?: [0-9]+)*$/.test(trimmed)) {
    return false
  }

  if (trimmed.length > 20) {
    return false
  }

  return normalizePhoneDigits(trimmed).length >= 6
}

export function findPartyByPhone(options, telephone, excludeId = null) {
  const digits = normalizePhoneDigits(telephone)
  if (!digits) return null
  return (
    options.find(
      (item) =>
        item.id !== excludeId && normalizePhoneDigits(item.telephone) === digits,
    ) ?? null
  )
}

export function isPartyDirty(party, options = []) {
  if (party?.id) {
    const found = options.find((item) => item.id === party.id)
    if (!found) return true
    return (
      (party.nom ?? '') !== (found.nom ?? '')
      || (party.prenom ?? '') !== (found.prenom ?? '')
      || (party.telephone ?? '') !== (found.telephone ?? '')
    )
  }

  return Boolean(
    party?.nom?.trim()
    || party?.prenom?.trim()
    || party?.telephone?.trim(),
  )
}

export function getPartySaveErrors(party) {
  const errors = {}

  if (!hasRequiredText(party?.nom)) {
    errors.nom = requiredMessage('Nom')
  }

  if (!hasRequiredText(party?.telephone)) {
    errors.telephone = requiredMessage('Téléphone')
  } else if (!hasValidPhone(party.telephone)) {
    errors.telephone = invalidPhoneMessage()
  }

  return errors
}

export function requiredMessage(label) {
  return `Le champ « ${label} » est obligatoire.`
}

export function invalidPhoneMessage(label = 'Téléphone') {
  return `Le ${label.toLowerCase()} est invalide (« + » optionnel en début, chiffres et espaces simples, 6 chiffres minimum).`
}

export function minLengthMessage(label, min) {
  return `Le champ « ${label} » doit contenir au moins ${min} caractères.`
}

export function getClientFormErrors(form) {
  const errors = {}

  if (!hasRequiredText(form?.nom)) {
    errors.nom = requiredMessage('Nom')
  }

  if (!hasRequiredText(form?.telephone)) {
    errors.telephone = requiredMessage('Téléphone')
  } else if (!hasValidPhone(form.telephone)) {
    errors.telephone = invalidPhoneMessage()
  }

  return errors
}

export function isValidClientForm(form) {
  return Object.keys(getClientFormErrors(form)).length === 0
}

export function getUserFormErrors(form, { editing = false } = {}) {
  const errors = {}

  if (!hasRequiredText(form?.prenom)) {
    errors.prenom = requiredMessage('Prénom')
  }
  if (!hasRequiredText(form?.nom)) {
    errors.nom = requiredMessage('Nom')
  }
  if (!hasRequiredText(form?.telephone)) {
    errors.telephone = requiredMessage('Téléphone')
  } else if (!hasValidPhone(form.telephone)) {
    errors.telephone = invalidPhoneMessage()
  }
  if (!hasRequiredText(form?.login)) {
    errors.login = requiredMessage('Login')
  }
  if (!hasRequiredText(form?.role)) {
    errors.role = requiredMessage('Rôle')
  }

  const password = form?.password?.trim() ?? ''
  if (editing) {
    if (password && password.length < 6) {
      errors.password = minLengthMessage('Mot de passe', 6)
    }
  } else if (!password) {
    errors.password = requiredMessage('Mot de passe')
  } else if (password.length < 6) {
    errors.password = minLengthMessage('Mot de passe', 6)
  }

  return errors
}

export function isValidUserForm(form, options = {}) {
  return Object.keys(getUserFormErrors(form, options)).length === 0
}

export function getChangePasswordFormErrors(form) {
  const errors = {}
  const currentPassword = form?.currentPassword ?? ''
  const newPassword = form?.newPassword ?? ''
  const confirmPassword = form?.confirmPassword ?? ''

  if (!currentPassword) {
    errors.currentPassword = requiredMessage('Mot de passe actuel')
  }

  if (!newPassword) {
    errors.newPassword = requiredMessage('Nouveau mot de passe')
  } else if (newPassword.length < 6) {
    errors.newPassword = minLengthMessage('Nouveau mot de passe', 6)
  } else if (currentPassword && newPassword === currentPassword) {
    errors.newPassword = 'Le nouveau mot de passe doit être différent de l\'ancien.'
  }

  if (!confirmPassword) {
    errors.confirmPassword = requiredMessage('Confirmation')
  } else if (newPassword && confirmPassword !== newPassword) {
    errors.confirmPassword = 'La confirmation ne correspond pas au nouveau mot de passe.'
  }

  return errors
}

export function getBeneficiaireFormErrors(form) {
  const errors = {}

  if (!hasRequiredText(form?.nom)) {
    errors.nom = requiredMessage('Nom')
  }

  if (!hasRequiredText(form?.telephone)) {
    errors.telephone = requiredMessage('Téléphone')
  } else if (!hasValidPhone(form.telephone)) {
    errors.telephone = invalidPhoneMessage()
  }

  return errors
}

export function isValidBeneficiaireForm(form) {
  return Object.keys(getBeneficiaireFormErrors(form)).length === 0
}

export function getPartyErrors(
  party,
  {
    selectionLabel = 'Sélection',
    options = [],
    unsavedMessage = 'Enregistrez les informations avant de continuer.',
  } = {},
) {
  const errors = {}

  if (!party?.id) {
    errors.selection = requiredMessage(selectionLabel)
    return errors
  }

  if (isPartyDirty(party, options)) {
    errors.selection = unsavedMessage
  }

  return errors
}

export function getTransfertFormErrors(
  form,
  {
    clientParty,
    beneficiaireParty,
    clientOptions = [],
    beneficiaireOptions = [],
    clientSelectionLabel = "Sélection de l'expéditeur",
    beneficiaireSelectionLabel = 'Sélection du bénéficiaire',
  } = {},
) {
  const errors = {
    client: {},
    beneficiaire: {},
  }

  if (!form?.liaisonId) {
    errors.liaisonId = requiredMessage('Pays de destination')
  }

  if (!(Number(form?.montantSource) > 0)) {
    errors.montantSource = 'Le montant source doit être supérieur à 0.'
  }

  if (Number(form?.montantFrais) < 0) {
    errors.montantFrais = 'Les frais ne peuvent pas être négatifs.'
  } else if (Number(form?.montantFrais) > Number(form?.montantSource)) {
    errors.montantFrais = 'Les frais ne peuvent pas dépasser le montant source.'
  }

  if (!(Number(form?.tauxApplique) > 0)) {
    errors.tauxApplique = 'Le taux appliqué doit être supérieur à 0.'
  }

  errors.client = getPartyErrors(clientParty, {
    selectionLabel: clientSelectionLabel,
    options: clientOptions,
    unsavedMessage: 'Enregistrez le client avant de continuer.',
  })
  errors.beneficiaire = getPartyErrors(beneficiaireParty, {
    selectionLabel: beneficiaireSelectionLabel,
    options: beneficiaireOptions,
    unsavedMessage: 'Enregistrez le bénéficiaire avant de continuer.',
  })

  return errors
}

export function hasFormErrors(errors) {
  if (!errors || typeof errors !== 'object') {
    return false
  }

  return Object.values(errors).some((value) => {
    if (!value) return false
    if (typeof value === 'string') return value.length > 0
    if (Array.isArray(value)) return value.length > 0
    if (typeof value === 'object') return hasFormErrors(value)
    return false
  })
}

export function isValidTransfertForm(form, parties = {}) {
  return !hasFormErrors(getTransfertFormErrors(form, parties))
}

export function fieldErrorText(error) {
  if (Array.isArray(error)) {
    return error.filter(Boolean).join(' ')
  }
  return typeof error === 'string' ? error : ''
}
