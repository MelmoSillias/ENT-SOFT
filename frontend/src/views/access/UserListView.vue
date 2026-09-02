<script setup>
import { computed, onMounted, ref } from 'vue'
import api from '@/services/api'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Button from 'primevue/button'
import Card from 'primevue/card'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import Accordion from 'primevue/accordion'
import AccordionPanel from 'primevue/accordionpanel'
import AccordionHeader from 'primevue/accordionheader'
import AccordionContent from 'primevue/accordioncontent'
import ToggleSwitch from 'primevue/toggleswitch'
import Password from 'primevue/password'
import { useConfirm } from 'primevue/useconfirm'
import AppTablePanelHeader from '@/domains/shared/components/AppTablePanelHeader.vue'
import { useAppToast } from '@/domains/shared/composables/useAppToast'
import AppTableState from '@/domains/shared/components/AppTableState.vue'
import { useAsyncAction } from '@/domains/shared/composables/useAsyncAction'
import { getUserFormErrors, sanitizePhoneInput } from '@/domains/shared/utils/formValidation'
import { useFormFieldErrors } from '@/domains/shared/composables/useFormFieldErrors'
import AppFieldError from '@/domains/shared/components/AppFieldError.vue'
import AppPhoneInput from '@/domains/shared/components/AppPhoneInput.vue'

const toast = useAppToast()
const confirm = useConfirm()

const items = ref([])
const permissionsCatalog = ref([])
const rolePermissions = ref({})
const loading = ref(true)
const dialog = ref(false)
const permDialog = ref(false)
const resetDialog = ref(false)
const editingId = ref(null)
const selectedUser = ref(null)
const resetUser = ref(null)
const resetPasswordValue = ref('')
const userPermissions = ref([])
const loadingPermissions = ref(false)
const initialOverrides = ref(new Map())
const expandedZones = ref([])

const roleOptions = [
  { label: 'Administrateur', value: 'ADMIN' },
  { label: 'Agent', value: 'AGENT' },
  { label: 'Superviseur', value: 'SUPERVISEUR' },
]

const permAccordeOptions = [
  { label: 'Défaut', value: 'default', tone: 'default' },
  { label: 'Accorder', value: true, tone: 'grant' },
  { label: 'Retirer', value: false, tone: 'deny' },
]

const MODULE_ZONES = {
  dashboard: { label: 'Tableau de bord', icon: 'pi pi-home', order: 1 },
  transfert: { label: 'Transferts', icon: 'pi pi-send', order: 2 },
  client: { label: 'Clients', icon: 'pi pi-users', order: 3 },
  beneficiaire: { label: 'Bénéficiaires', icon: 'pi pi-user', order: 4 },
  finance: { label: 'Finances', icon: 'pi pi-wallet', order: 5 },
  reporting: { label: 'Rapports', icon: 'pi pi-chart-bar', order: 6 },
  configuration: { label: 'Configurations', icon: 'pi pi-cog', order: 7 },
  referentiel: { label: 'Référentiel', icon: 'pi pi-database', order: 8 },
  access: { label: 'Administration', icon: 'pi pi-shield', order: 9 },
  notification: { label: 'Notifications', icon: 'pi pi-bell', order: 10 },
}

const emptyForm = () => ({
  prenom: '',
  nom: '',
  telephone: '',
  login: '',
  password: '',
  role: 'AGENT',
  isActive: true,
})

const form = ref(emptyForm())

async function load() {
  loading.value = true
  try {
    const { data } = await api.get('/users')
    items.value = Array.isArray(data) ? data : (data.items ?? [])
  } finally {
    loading.value = false
  }
}

async function loadPermissionsCatalog() {
  const { data } = await api.get('/permissions')
  permissionsCatalog.value = data.data ?? data
  rolePermissions.value = data.role_permissions ?? {}
}

function roleDefaultGranted(code, role) {
  if (role === 'ADMIN') return true
  return (rolePermissions.value[role] ?? []).includes(code)
}

function resolvePermissionValue(code, role, override) {
  if (override !== undefined) {
    return override
  }
  if (role === 'ADMIN') {
    return 'default'
  }
  return roleDefaultGranted(code, role)
}

function toSavePayload(code, selectedValue, role) {
  const roleDefault = roleDefaultGranted(code, role)

  if (selectedValue === 'default') {
    return { code, accorde: null }
  }

  if (role === 'ADMIN') {
    return selectedValue === true
      ? { code, accorde: null }
      : { code, accorde: false }
  }

  if (selectedValue === roleDefault) {
    return { code, accorde: null }
  }

  return { code, accorde: selectedValue }
}

const groupedUserPermissions = computed(() => {
  const groups = new Map()

  for (const perm of userPermissions.value) {
    const module = perm.module ?? 'other'
    if (!groups.has(module)) {
      const zone = MODULE_ZONES[module] ?? { label: module, icon: 'pi pi-folder', order: 99 }
      groups.set(module, { ...zone, module, permissions: [] })
    }
    groups.get(module).permissions.push(perm)
  }

  return [...groups.values()].sort((a, b) => a.order - b.order)
})

onMounted(async () => {
  await Promise.all([load(), loadPermissionsCatalog()])
})

const dialogTitle = computed(() => (editingId.value ? 'Modifier utilisateur' : 'Nouvel utilisateur'))
const { errors: fieldErrors, validate, resetErrors } = useFormFieldErrors(() =>
  getUserFormErrors(form.value, { editing: Boolean(editingId.value) }),
)

function openCreate() {
  editingId.value = null
  form.value = emptyForm()
  resetErrors()
  dialog.value = true
}

function openEdit(user) {
  editingId.value = user.id
  form.value = {
    prenom: user.prenom,
    nom: user.nom,
    telephone: sanitizePhoneInput(user.telephone),
    login: user.login,
    password: '',
    role: user.role,
    isActive: user.isActive !== false,
  }
  resetErrors()
  dialog.value = true
}

const { pending: saving, run: saveUser } = useAsyncAction(async () => {
  if (!validate()) return

  const payload = {
    ...form.value,
    telephone: sanitizePhoneInput(form.value.telephone),
  }
  if (editingId.value) {
    if (!payload.password) delete payload.password
    await api.put(`/users/${editingId.value}`, payload)
  } else {
    await api.post('/users', payload)
  }
  dialog.value = false
  resetErrors()
  await load()
})

function openResetPassword(user) {
  resetUser.value = user
  resetPasswordValue.value = ''
  resetDialog.value = true
}

const { pending: resetting, run: confirmResetPassword } = useAsyncAction(async () => {
  if (!resetPasswordValue.value.trim()) return
  await api.put(`/users/${resetUser.value.id}`, { password: resetPasswordValue.value })
  resetDialog.value = false
  toast.add({ severity: 'success', summary: 'Mot de passe réinitialisé' })
})

async function openPermissions(user) {
  selectedUser.value = user
  loadingPermissions.value = true
  permDialog.value = true
  try {
    const { data } = await api.get(`/users/${user.id}/permissions`)
    const role = data.role ?? user.role
    const overrides = new Map((data.permissions ?? []).map((p) => [p.code, p.accorde]))
    initialOverrides.value = overrides
    userPermissions.value = permissionsCatalog.value.map((p) => ({
      code: p.code,
      libelle: p.libelle,
      module: p.module,
      accorde: resolvePermissionValue(p.code, role, overrides.get(p.code)),
    }))
    expandedZones.value = groupedUserPermissions.value.length
      ? [groupedUserPermissions.value[0].module]
      : []
  } finally {
    loadingPermissions.value = false
  }
}

const { pending: savingPerms, run: savePermissions } = useAsyncAction(async () => {
  const role = selectedUser.value.role
  const permissions = []

  for (const perm of userPermissions.value) {
    const payload = toSavePayload(perm.code, perm.accorde, role)
    const hadOverride = initialOverrides.value.has(perm.code)
    const needsOverride = payload.accorde !== null

    if (needsOverride || (hadOverride && !needsOverride)) {
      permissions.push(payload)
    }
  }

  if (permissions.length > 0) {
    await api.put(`/users/${selectedUser.value.id}/permissions`, { permissions })
  }
  permDialog.value = false
})

function suspendUser(user) {
  confirm.require({
    header: 'Suspendre l\'utilisateur',
    message: `Suspendre ${user.login} ?`,
    icon: 'pi pi-exclamation-triangle',
    rejectProps: { label: 'Annuler', severity: 'secondary', outlined: true },
    acceptProps: { label: 'Suspendre', severity: 'danger' },
    accept: async () => {
      await api.post(`/users/${user.id}/suspend`)
      await load()
    },
  })
}
</script>

<template>
  <section class="dashboard-page">
    <Card class="dashboard-panel">
      <template #title>
        <AppTablePanelHeader
          title="Utilisateurs"
          :count-label="`${items.length}`"
          create-label="Nouvel utilisateur"
          @create="openCreate"
          @reload="load"
        />
      </template>
      <template #content>
        <AppTableState
          :loading="loading"
          :is-empty="!loading && items.length === 0"
          empty-title="Aucun utilisateur"
          empty-text="Créez un utilisateur pour commencer."
          @retry="load"
        >
          <DataTable :value="items" paginator :rows="10" striped-rows>
            <Column field="login" header="Login" />
            <Column field="nom" header="Nom" />
            <Column field="prenom" header="Prénom" />
            <Column field="role" header="Rôle" />
            <Column field="isActive" header="Actif">
              <template #body="{ data }">{{ data.isActive ? 'Oui' : 'Non' }}</template>
            </Column>
            <Column header="Actions" style="width: 14rem">
              <template #body="{ data }">
                <Button icon="pi pi-pencil" text rounded aria-label="Modifier" v-tooltip.top="'Modifier'" @click="openEdit(data)" />
                <Button icon="pi pi-key" text rounded aria-label="Réinitialiser MDP" v-tooltip.top="'Réinitialiser MDP'" @click="openResetPassword(data)" />
                <Button icon="pi pi-shield" text rounded aria-label="Permissions" v-tooltip.top="'Permissions'" @click="openPermissions(data)" />
                <Button icon="pi pi-ban" text rounded severity="danger" aria-label="Suspendre" v-tooltip.top="'Suspendre'" @click="suspendUser(data)" />
              </template>
            </Column>
          </DataTable>
        </AppTableState>
      </template>
    </Card>

    <Dialog v-model:visible="dialog" :header="dialogTitle" modal style="width: min(480px, 95vw)">
      <div class="field">
        <label>Prénom <span class="required">*</span></label>
        <InputText v-model="form.prenom" :invalid="Boolean(fieldErrors.prenom)" fluid />
        <AppFieldError :message="fieldErrors.prenom" />
      </div>
      <div class="field">
        <label>Nom <span class="required">*</span></label>
        <InputText v-model="form.nom" :invalid="Boolean(fieldErrors.nom)" fluid />
        <AppFieldError :message="fieldErrors.nom" />
      </div>
      <div class="field">
        <label>Téléphone <span class="required">*</span></label>
        <AppPhoneInput v-model="form.telephone" :invalid="Boolean(fieldErrors.telephone)" fluid />
        <AppFieldError :message="fieldErrors.telephone" />
      </div>
      <div class="field">
        <label>Login <span class="required">*</span></label>
        <InputText v-model="form.login" :invalid="Boolean(fieldErrors.login)" fluid />
        <AppFieldError :message="fieldErrors.login" />
      </div>
      <div class="field">
        <label>
          {{ editingId ? 'Nouveau mot de passe (optionnel)' : 'Mot de passe' }}
          <span v-if="!editingId" class="required">*</span>
        </label>
        <InputText v-model="form.password" type="password" :invalid="Boolean(fieldErrors.password)" fluid />
        <AppFieldError :message="fieldErrors.password" />
        <small v-if="!editingId && !fieldErrors.password" class="field-hint">Minimum 6 caractères</small>
      </div>
      <div class="field">
        <label>Rôle <span class="required">*</span></label>
        <Select
          v-model="form.role"
          :options="roleOptions"
          option-label="label"
          option-value="value"
          :invalid="Boolean(fieldErrors.role)"
          fluid
        />
        <AppFieldError :message="fieldErrors.role" />
      </div>
      <div v-if="editingId" class="field field--row">
        <label>Actif</label>
        <ToggleSwitch v-model="form.isActive" />
      </div>
      <template #footer>
        <Button label="Annuler" text @click="dialog = false" />
        <Button label="Enregistrer" icon="pi pi-check" :loading="saving" :disabled="saving" @click="saveUser" />
      </template>
    </Dialog>

    <Dialog v-model:visible="resetDialog" header="Réinitialiser le mot de passe" modal style="width: min(420px, 95vw)">
      <p v-if="resetUser" class="reset-user">Utilisateur : <strong>{{ resetUser.login }}</strong></p>
      <div class="field">
        <label for="reset-password">Nouveau mot de passe</label>
        <Password
          id="reset-password"
          v-model="resetPasswordValue"
          :feedback="false"
          toggle-mask
          fluid
          @keyup.enter="confirmResetPassword"
        />
      </div>
      <template #footer>
        <Button label="Annuler" text @click="resetDialog = false" />
        <Button
          label="Réinitialiser"
          icon="pi pi-key"
          :loading="resetting"
          :disabled="!resetPasswordValue.trim()"
          @click="confirmResetPassword"
        />
      </template>
    </Dialog>

    <Dialog v-model:visible="permDialog" header="Permissions individuelles" modal class="perm-dialog" style="width: min(680px, 95vw)">
      <p v-if="selectedUser" class="perm-user">
        Utilisateur : <strong>{{ selectedUser.login }}</strong>
        <span class="perm-role">({{ selectedUser.role }})</span>
      </p>
      <div v-if="!loadingPermissions" class="perm-legend" aria-hidden="true">
        <span class="perm-legend__chip perm-legend__chip--default">Défaut</span>
        <span class="perm-legend__chip perm-legend__chip--grant">Accorder</span>
        <span class="perm-legend__chip perm-legend__chip--deny">Retirer</span>
      </div>
      <p v-if="loadingPermissions" class="perm-loading">Chargement des permissions…</p>
      <Accordion v-else v-model:value="expandedZones" multiple class="perm-accordion">
        <AccordionPanel v-for="zone in groupedUserPermissions" :key="zone.module" :value="zone.module">
          <AccordionHeader>
            <div class="perm-zone__header-inner">
              <span class="perm-zone__title">
                <i :class="zone.icon" aria-hidden="true" />
                {{ zone.label }}
              </span>
              <span class="perm-zone__count">{{ zone.permissions.length }}</span>
            </div>
          </AccordionHeader>
          <AccordionContent>
            <div class="perm-zone__list">
              <div v-for="perm in zone.permissions" :key="perm.code" class="perm-row">
                <span class="perm-row__label">{{ perm.libelle }}</span>
                <div class="perm-segment" role="group" :aria-label="perm.libelle">
                  <button
                    v-for="opt in permAccordeOptions"
                    :key="String(opt.value)"
                    type="button"
                    class="perm-segment__btn"
                    :class="[
                      `perm-segment__btn--${opt.tone}`,
                      { 'perm-segment__btn--active': perm.accorde === opt.value },
                    ]"
                    @click="perm.accorde = opt.value"
                  >
                    {{ opt.label }}
                  </button>
                </div>
              </div>
            </div>
          </AccordionContent>
        </AccordionPanel>
      </Accordion>
      <template #footer>
        <Button label="Annuler" text @click="permDialog = false" />
        <Button label="Enregistrer" icon="pi pi-check" :loading="savingPerms" :disabled="loadingPermissions" @click="savePermissions" />
      </template>
    </Dialog>
  </section>
</template>

<style scoped>
.field {
  margin-bottom: 0.85rem;
}

.field--row {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.required {
  color: var(--p-red-500, #ef4444);
}

.field-hint {
  color: var(--p-text-muted-color);
  font-size: 0.75rem;
}

.reset-user,
.perm-user {
  margin: 0 0 1rem;
}

.perm-role {
  margin-left: 0.35rem;
  color: var(--p-text-muted-color);
  font-size: 0.85rem;
}

.perm-loading {
  margin: 0 0 1rem;
  font-size: 0.85rem;
  color: var(--p-text-muted-color);
}

.perm-legend {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  margin-bottom: 0.85rem;
}

.perm-legend__chip {
  display: inline-flex;
  align-items: center;
  padding: 0.2rem 0.55rem;
  border-radius: 999px;
  font-size: 0.72rem;
  font-weight: 600;
}

.perm-legend__chip--default {
  background: #64748b;
  color: #fff;
}

.perm-legend__chip--grant {
  background: #16a34a;
  color: #fff;
}

.perm-legend__chip--deny {
  background: #dc2626;
  color: #fff;
}

.perm-accordion {
  max-height: min(58vh, 560px);
  overflow-y: auto;
  padding-right: 0.15rem;
}

.perm-accordion :deep(.p-accordionpanel) {
  border: 1px solid var(--p-content-border-color);
  border-radius: var(--p-content-border-radius);
  margin-bottom: 0.5rem;
  overflow: hidden;
}

.perm-accordion :deep(.p-accordionpanel:last-child) {
  margin-bottom: 0;
}

.perm-accordion :deep(.p-accordionheader) {
  padding: 0.55rem 0.75rem;
  background: color-mix(in srgb, var(--p-content-background) 90%, var(--p-primary-color) 10%);
}

.perm-accordion :deep(.p-accordioncontent-content) {
  padding: 0.35rem 0.75rem 0.65rem;
}

.perm-zone__header-inner {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  width: 100%;
  padding-right: 0.25rem;
}

.perm-zone__title {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.88rem;
  font-weight: 600;
}

.perm-zone__count {
  margin-left: auto;
  font-size: 0.72rem;
  color: var(--p-text-muted-color);
  background: var(--p-content-background);
  border: 1px solid var(--p-content-border-color);
  border-radius: 999px;
  padding: 0.1rem 0.45rem;
}

.perm-zone__list {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
}

.perm-row {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto;
  align-items: center;
  gap: 0.75rem;
  padding: 0.45rem 0;
  font-size: 0.84rem;
  border-bottom: 1px solid color-mix(in srgb, var(--p-content-border-color) 65%, transparent);
}

.perm-row:last-child {
  border-bottom: none;
}

.perm-row__label {
  line-height: 1.35;
}

.perm-segment {
  display: inline-flex;
  flex-shrink: 0;
  border: 1px solid var(--p-content-border-color);
  border-radius: 0.5rem;
  overflow: hidden;
  background: var(--p-content-background);
}

.perm-segment__btn {
  border: none;
  border-right: 1px solid var(--p-content-border-color);
  padding: 0.42rem 0.7rem;
  min-width: 4.6rem;
  font-size: 0.74rem;
  font-weight: 600;
  line-height: 1;
  cursor: pointer;
  background: var(--p-content-background);
  color: var(--p-text-muted-color);
  transition: background-color 0.15s ease, color 0.15s ease;
}

.perm-segment__btn:last-child {
  border-right: none;
}

.perm-segment__btn:hover:not(.perm-segment__btn--active) {
  background: color-mix(in srgb, var(--p-content-background) 85%, var(--p-text-color) 15%);
}

.perm-segment__btn--grant:not(.perm-segment__btn--active) {
  color: #15803d;
}

.perm-segment__btn--deny:not(.perm-segment__btn--active) {
  color: #b91c1c;
}

.perm-segment__btn--active.perm-segment__btn--default {
  background: #64748b;
  color: #fff;
}

.perm-segment__btn--active.perm-segment__btn--grant {
  background: #16a34a;
  color: #fff;
}

.perm-segment__btn--active.perm-segment__btn--deny {
  background: #dc2626;
  color: #fff;
}

@media (max-width: 640px) {
  .perm-row {
    grid-template-columns: 1fr;
    gap: 0.5rem;
  }

  .perm-segment {
    width: 100%;
  }

  .perm-segment__btn {
    flex: 1;
    min-width: 0;
    padding-inline: 0.35rem;
  }
}
</style>
