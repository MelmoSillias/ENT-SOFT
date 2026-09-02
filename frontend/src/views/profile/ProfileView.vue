<script setup>
import { computed, reactive } from 'vue'
import { storeToRefs } from 'pinia'
import Avatar from 'primevue/avatar'
import Button from 'primevue/button'
import Card from 'primevue/card'
import Message from 'primevue/message'
import Password from 'primevue/password'
import Tag from 'primevue/tag'

import { useAuthStore } from '@/domains/auth/stores/auth'
import AppFieldError from '@/domains/shared/components/AppFieldError.vue'
import { useAppToast } from '@/domains/shared/composables/useAppToast'
import { useAsyncAction } from '@/domains/shared/composables/useAsyncAction'
import { useFormFieldErrors } from '@/domains/shared/composables/useFormFieldErrors'
import { getChangePasswordFormErrors } from '@/domains/shared/utils/formValidation'

const auth = useAuthStore()
const { user } = storeToRefs(auth)
const toast = useAppToast()

const ROLE_LABELS = {
  ADMIN: 'Administrateur',
  SUPERVISEUR: 'Superviseur',
  AGENT: 'Agent',
}

const passwordForm = reactive({
  currentPassword: '',
  newPassword: '',
  confirmPassword: '',
})

const generalError = reactive({ message: '' })

const { errors: fieldErrors, validate, resetErrors } = useFormFieldErrors(() =>
  getChangePasswordFormErrors(passwordForm),
)

const displayName = computed(() => {
  const prenom = user.value?.prenom?.trim() ?? ''
  const nom = user.value?.nom?.trim() ?? ''
  const full = `${prenom} ${nom}`.trim()
  return full || user.value?.login || 'Utilisateur'
})

const userInitials = computed(() =>
  displayName.value
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase())
    .join(''),
)

const roleLabel = computed(() => ROLE_LABELS[user.value?.role] || user.value?.role || '—')

const roleSeverity = computed(() => {
  switch (user.value?.role) {
    case 'ADMIN':
      return 'danger'
    case 'SUPERVISEUR':
      return 'warn'
    default:
      return 'info'
  }
})

function resetPasswordForm() {
  passwordForm.currentPassword = ''
  passwordForm.newPassword = ''
  passwordForm.confirmPassword = ''
  generalError.message = ''
  resetErrors()
}

const { pending: saving, run: submitPasswordChange } = useAsyncAction(async () => {
  generalError.message = ''
  if (!validate()) {
    return
  }

  try {
    await auth.changePassword(passwordForm.currentPassword, passwordForm.newPassword)
    resetPasswordForm()
    toast.add({
      severity: 'success',
      summary: 'Mot de passe mis à jour',
      detail: 'Votre nouveau mot de passe est actif.',
    })
  } catch (e) {
    generalError.message = e.response?.data?.error || 'Impossible de changer le mot de passe.'
  }
})
</script>

<template>
  <section class="dashboard-page profile-page">
    <div class="profile-page__grid">
      <Card class="dashboard-panel profile-page__info-card">
        <template #title>Mon profil</template>
        <template #content>
          <div class="profile-page__hero">
            <Avatar
              :label="userInitials"
              shape="circle"
              size="large"
              class="profile-page__avatar"
            />
            <div class="profile-page__hero-copy">
              <h2 class="profile-page__name">{{ displayName }}</h2>
              <p class="profile-page__login">@{{ user?.login || '—' }}</p>
              <div class="profile-page__tags">
                <Tag :value="roleLabel" :severity="roleSeverity" />
                <Tag
                  :value="user?.isActive === false ? 'Inactif' : 'Actif'"
                  :severity="user?.isActive === false ? 'danger' : 'success'"
                />
              </div>
            </div>
          </div>

          <dl class="profile-page__details">
            <div class="profile-page__detail">
              <dt>Prénom</dt>
              <dd>{{ user?.prenom || '—' }}</dd>
            </div>
            <div class="profile-page__detail">
              <dt>Nom</dt>
              <dd>{{ user?.nom || '—' }}</dd>
            </div>
            <div class="profile-page__detail">
              <dt>Téléphone</dt>
              <dd>{{ user?.telephone || '—' }}</dd>
            </div>
            <div class="profile-page__detail">
              <dt>Identifiant</dt>
              <dd>{{ user?.login || '—' }}</dd>
            </div>
            <div class="profile-page__detail">
              <dt>Rôle</dt>
              <dd>{{ roleLabel }}</dd>
            </div>
          </dl>
        </template>
      </Card>

      <Card class="dashboard-panel profile-page__password-card">
        <template #title>Changer le mot de passe</template>
        <template #subtitle>
          Saisissez votre mot de passe actuel, puis choisissez-en un nouveau.
        </template>
        <template #content>
          <form class="profile-page__password-form" @submit.prevent="submitPasswordChange">
            <div class="field">
              <label for="profile-current-password">Mot de passe actuel</label>
              <Password
                id="profile-current-password"
                v-model="passwordForm.currentPassword"
                fluid
                toggle-mask
                :feedback="false"
                autocomplete="current-password"
                :invalid="Boolean(fieldErrors.currentPassword)"
                input-class="w-full"
              />
              <AppFieldError :message="fieldErrors.currentPassword" />
            </div>

            <div class="field">
              <label for="profile-new-password">Nouveau mot de passe</label>
              <Password
                id="profile-new-password"
                v-model="passwordForm.newPassword"
                fluid
                toggle-mask
                :feedback="false"
                autocomplete="new-password"
                :invalid="Boolean(fieldErrors.newPassword)"
                input-class="w-full"
              />
              <AppFieldError :message="fieldErrors.newPassword" />
              <small v-if="!fieldErrors.newPassword" class="field-hint">Minimum 6 caractères</small>
            </div>

            <div class="field">
              <label for="profile-confirm-password">Confirmer le nouveau mot de passe</label>
              <Password
                id="profile-confirm-password"
                v-model="passwordForm.confirmPassword"
                fluid
                toggle-mask
                :feedback="false"
                autocomplete="new-password"
                :invalid="Boolean(fieldErrors.confirmPassword)"
                input-class="w-full"
              />
              <AppFieldError :message="fieldErrors.confirmPassword" />
            </div>

            <Message v-if="generalError.message" severity="error" :closable="false" class="profile-page__error">
              {{ generalError.message }}
            </Message>

            <div class="profile-page__actions">
              <Button
                type="button"
                label="Réinitialiser"
                icon="pi pi-times"
                severity="secondary"
                outlined
                :disabled="saving"
                @click="resetPasswordForm"
              />
              <Button
                type="submit"
                label="Enregistrer"
                icon="pi pi-check"
                :loading="saving"
                :disabled="saving"
              />
            </div>
          </form>
        </template>
      </Card>
    </div>
  </section>
</template>

<style scoped>
.profile-page__grid {
  display: grid;
  gap: 1rem;
  grid-template-columns: minmax(0, 1.05fr) minmax(0, 0.95fr);
  align-items: start;
}

.profile-page__hero {
  display: flex;
  gap: 1rem;
  align-items: center;
  margin-bottom: 1.25rem;
}

.profile-page__avatar {
  flex-shrink: 0;
  background: color-mix(in srgb, var(--p-primary-color, #0ea5e9) 18%, transparent);
  color: var(--p-primary-color, #0284c7);
  font-weight: 700;
}

.profile-page__name {
  margin: 0;
  font-size: 1.25rem;
  font-weight: 700;
  color: var(--layout-text, inherit);
}

.profile-page__login {
  margin: 0.15rem 0 0.55rem;
  color: var(--layout-text-muted, #64748b);
  font-size: 0.9rem;
}

.profile-page__tags {
  display: flex;
  flex-wrap: wrap;
  gap: 0.4rem;
}

.profile-page__details {
  display: grid;
  gap: 0.75rem;
  margin: 0;
}

.profile-page__detail {
  display: grid;
  gap: 0.2rem;
  padding: 0.7rem 0.85rem;
  border-radius: 0.65rem;
  background: color-mix(in srgb, var(--layout-surface-muted, #f8fafc) 88%, transparent);
}

.profile-page__detail dt {
  font-size: 0.75rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.03em;
  color: var(--layout-text-muted, #64748b);
}

.profile-page__detail dd {
  margin: 0;
  font-size: 0.95rem;
  font-weight: 600;
}

.profile-page__password-form {
  display: grid;
  gap: 0.85rem;
}

.field label {
  display: block;
  margin-bottom: 0.35rem;
  font-size: 0.85rem;
  font-weight: 600;
}

.field-hint {
  display: block;
  margin-top: 0.3rem;
  color: var(--layout-text-muted, #64748b);
  font-size: 0.8rem;
}

.profile-page__error {
  margin: 0;
}

.profile-page__actions {
  display: flex;
  flex-wrap: wrap;
  justify-content: flex-end;
  gap: 0.65rem;
  margin-top: 0.25rem;
}

@media (max-width: 900px) {
  .profile-page__grid {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 560px) {
  .profile-page__hero {
    flex-direction: column;
    align-items: flex-start;
  }

  .profile-page__actions {
    flex-direction: column-reverse;
  }

  .profile-page__actions :deep(.p-button) {
    width: 100%;
  }
}
</style>
