<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/domains/auth/stores/auth'
import AuthPageLayout from '@/domains/auth/components/AuthPageLayout.vue'
import { useAppToast } from '@/domains/shared/composables/useAppToast'
import { useAsyncAction } from '@/domains/shared/composables/useAsyncAction'
import { requiredMessage } from '@/domains/shared/utils/formValidation'
import AppFieldError from '@/domains/shared/components/AppFieldError.vue'
import InputText from 'primevue/inputtext'
import Password from 'primevue/password'
import Button from 'primevue/button'
import Message from 'primevue/message'
import '@/domains/auth/assets/auth-light-surface.css'

const auth = useAuthStore()
const router = useRouter()
const toast = useAppToast()

const login = ref('')
const password = ref('')
const fieldErrors = ref({ login: '', password: '' })
const generalError = ref('')

function validateFields() {
  const next = { login: '', password: '' }
  if (!login.value.trim()) {
    next.login = requiredMessage('Identifiant')
  }
  if (!password.value) {
    next.password = requiredMessage('Mot de passe')
  }
  fieldErrors.value = next
  return !next.login && !next.password
}

async function submit() {
  generalError.value = ''
  if (!validateFields()) {
    return
  }

  await runLogin()
}

const { pending: loading, run: runLogin } = useAsyncAction(async () => {
  try {
    await auth.login(login.value.trim(), password.value)
    toast.add({
      severity: 'success',
      summary: 'Connexion réussie',
      detail: 'Bienvenue sur ENT-SOFT.',
    })
    await router.replace({ name: 'dashboard' })
  } catch (e) {
    generalError.value = e.response?.data?.error || 'Connexion impossible. Vérifiez vos identifiants.'
  }
})
</script>

<template>
  <AuthPageLayout
    title="Connexion"
    subtitle="Accédez à votre espace ENT-SOFT."
  >
    <section class="auth-light-surface">
      <form class="login-form" @submit.prevent="submit">
        <div class="login-form__field">
          <label for="login">Identifiant</label>
          <InputText
            id="login"
            v-model="login"
            placeholder="admin"
            autocomplete="username"
            :invalid="Boolean(fieldErrors.login)"
            fluid
          />
          <AppFieldError :message="fieldErrors.login" />
        </div>

        <div class="login-form__field">
          <label for="password">Mot de passe</label>
          <Password
            id="password"
            v-model="password"
            placeholder="••••••••"
            fluid
            toggle-mask
            :feedback="false"
            :invalid="Boolean(fieldErrors.password)"
            input-class="w-full"
          />
          <AppFieldError :message="fieldErrors.password" />
        </div>

        <div v-if="generalError" class="login-form__message">
          <Message severity="error" size="small" variant="simple">
            {{ generalError }}
          </Message>
        </div>

        <Button
          type="submit"
          label="Se connecter"
          icon="pi pi-sign-in"
          :loading="loading"
          :disabled="loading"
          fluid
        />
      </form>
    </section>
  </AuthPageLayout>
</template>

<style scoped>
.login-form {
  display: grid;
  gap: 1rem;
}

.login-form__field {
  display: grid;
  gap: 0.4rem;
}

.login-form__field label {
  font-size: 0.875rem;
  font-weight: 600;
  color: var(--layout-text-color, #1a2744);
}

.login-form__message {
  margin-top: -0.25rem;
}
</style>
