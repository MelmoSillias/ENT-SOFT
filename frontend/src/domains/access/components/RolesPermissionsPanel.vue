<script setup>
import { computed, onMounted, ref } from 'vue'
import api from '@/services/api'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Button from 'primevue/button'
import Card from 'primevue/card'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import Tag from 'primevue/tag'
import Accordion from 'primevue/accordion'
import AccordionPanel from 'primevue/accordionpanel'
import AccordionHeader from 'primevue/accordionheader'
import AccordionContent from 'primevue/accordioncontent'
import { useConfirm } from 'primevue/useconfirm'
import AppTablePanelHeader from '@/domains/shared/components/AppTablePanelHeader.vue'
import AppTableState from '@/domains/shared/components/AppTableState.vue'
import AppTableSettingsPopover from '@/domains/shared/components/AppTableSettingsPopover.vue'
import AppRowContextMenu from '@/domains/shared/components/AppRowContextMenu.vue'
import AppTableActionsMenu from '@/domains/shared/components/AppTableActionsMenu.vue'
import AppEntityDataView from '@/domains/shared/components/AppEntityDataView.vue'
import AppMobileFab from '@/domains/shared/components/AppMobileFab.vue'
import AppFieldError from '@/domains/shared/components/AppFieldError.vue'
import { useTableSettings } from '@/domains/shared/composables/useTableSettings'
import { sortByField } from '@/domains/shared/utils/sortByField'
import { useAsyncAction } from '@/domains/shared/composables/useAsyncAction'
import { useFormFieldErrors } from '@/domains/shared/composables/useFormFieldErrors'
import { hasRequiredText, requiredMessage } from '@/domains/shared/utils/formValidation'
import { usePermissions } from '@/domains/auth/composables/usePermissions'
import { createRole, deleteRole, listRoles, updateRole } from '@/domains/access/services/roleService'
import { useAppToast } from '@/domains/shared/composables/useAppToast'
import { useAppMobileLayout } from '@/domains/layout/composables/useAppMobileLayout'

defineProps({
  embedded: {
    type: Boolean,
    default: false,
  },
})

const toast = useAppToast()
const confirm = useConfirm()
const { hasPermission } = usePermissions()
const { isAppMobile } = useAppMobileLayout()

const canManage = computed(() => hasPermission('access.roles.manage'))

const ROLE_COLUMNS = [
  { key: 'libelle', label: 'Libellé', defaultVisible: true },
  { key: 'code', label: 'Code', defaultVisible: true },
  { key: 'isSystem', label: 'Type', defaultVisible: true },
  { key: 'isEnabled', label: 'Statut', defaultVisible: true },
  { key: 'permissions', label: 'Permissions', defaultVisible: true, sortable: false },
]

const {
  ROW_OPTIONS,
  columns: tableColumns,
  visibleColKeys,
  rows: tableRows,
  showIndex,
  sortField,
  sortOrder,
  sortOptions,
  isColVisible,
  toggleCol,
} = useTableSettings('table_roles', ROLE_COLUMNS, {
  defaultSortField: 'libelle',
})

const items = ref([])
const searchTerm = ref('')
const permissionsCatalog = ref([])
const loading = ref(true)
const dialog = ref(false)
const permDialog = ref(false)
const editingId = ref(null)
const selectedRole = ref(null)
const selectedCodes = ref([])
const expandedZones = ref([])
const rowContextMenu = ref()

const MODULE_ZONES = {
  dashboard: { label: 'Tableau de bord', icon: 'pi pi-home', order: 1 },
  client: { label: 'Clients', icon: 'pi pi-users', order: 2 },
  site: { label: 'Sites', icon: 'pi pi-map-marker', order: 3 },
  project: { label: 'Projets', icon: 'pi pi-briefcase', order: 4 },
  employee: { label: 'RH', icon: 'pi pi-id-card', order: 5 },
  task: { label: 'Tâches', icon: 'pi pi-check-square', order: 6 },
  finance: { label: 'Finances', icon: 'pi pi-wallet', order: 7 },
  stock: { label: 'Stock', icon: 'pi pi-box', order: 8 },
  document: { label: 'Documents', icon: 'pi pi-file', order: 9 },
  configuration: { label: 'Configurations', icon: 'pi pi-cog', order: 10 },
  access: { label: 'Administration', icon: 'pi pi-shield', order: 11 },
  referentiel: { label: 'Référentiel', icon: 'pi pi-database', order: 12 },
}

const permRoleOptions = [
  { label: 'Autoriser', value: true, tone: 'grant' },
  { label: 'Non autorisé', value: false, tone: 'deny' },
]

function emptyForm() {
  return { code: '', libelle: '' }
}

const form = ref(emptyForm())

const { errors: fieldErrors, validate, resetErrors } = useFormFieldErrors(() => {
  const errs = {}
  if (!editingId.value && !hasRequiredText(form.value.code)) errs.code = requiredMessage('Code')
  if (!hasRequiredText(form.value.libelle)) errs.libelle = requiredMessage('Libellé')
  return errs
})

async function load() {
  loading.value = true
  try {
    items.value = await listRoles()
  } finally {
    loading.value = false
  }
}

async function loadPermissionsCatalog() {
  const { data } = await api.get('/permissions')
  permissionsCatalog.value = data.data ?? data
}

const groupedPermissions = computed(() => {
  const groups = new Map()
  for (const perm of permissionsCatalog.value) {
    const module = perm.module ?? 'other'
    if (!groups.has(module)) {
      const zone = MODULE_ZONES[module] ?? { label: module, icon: 'pi pi-folder', order: 99 }
      groups.set(module, { ...zone, module, permissions: [] })
    }
    groups.get(module).permissions.push(perm)
  }
  return [...groups.values()].sort((a, b) => a.order - b.order)
})

const filteredItems = computed(() => {
  const q = searchTerm.value.trim().toLowerCase()
  let list = items.value
  if (q) {
    list = list.filter((item) =>
      [item.libelle, item.code].filter(Boolean).join(' ').toLowerCase().includes(q),
    )
  }
  return sortByField(list, sortField.value, sortOrder.value)
})

onMounted(async () => {
  await Promise.all([load(), loadPermissionsCatalog()])
})

function openCreate() {
  editingId.value = null
  form.value = emptyForm()
  resetErrors()
  dialog.value = true
}

function openEdit(role) {
  editingId.value = role.id
  form.value = { code: role.code, libelle: role.libelle }
  resetErrors()
  dialog.value = true
}

const { pending: saving, run: saveRole } = useAsyncAction(async () => {
  if (!validate()) return
  if (editingId.value) {
    await updateRole(editingId.value, { libelle: form.value.libelle })
  } else {
    await createRole({
      code: form.value.code.trim().toUpperCase(),
      libelle: form.value.libelle,
      permissions: [],
    })
  }
  dialog.value = false
  toast.add({ severity: 'success', summary: 'Rôle enregistré' })
  await load()
})

function openPermissions(role) {
  selectedRole.value = role
  selectedCodes.value = [...(role.permissions ?? [])]
  expandedZones.value = groupedPermissions.value.length ? [groupedPermissions.value[0].module] : []
  permDialog.value = true
}

const { pending: savingPerms, run: savePermissions } = useAsyncAction(async () => {
  await updateRole(selectedRole.value.id, {
    libelle: selectedRole.value.libelle,
    permissions: selectedCodes.value,
  })
  permDialog.value = false
  toast.add({ severity: 'success', summary: 'Permissions du rôle mises à jour' })
  await load()
})

function askDelete(role) {
  if (role.isSystem) {
    toast.add({ severity: 'warn', summary: 'Impossible de masquer un rôle système' })
    return
  }
  confirm.require({
    header: 'Masquer le rôle',
    message: `Masquer « ${role.libelle} » ? Les utilisateurs qui l'ont déjà le conserve.`,
    icon: 'pi pi-exclamation-triangle',
    rejectProps: { label: 'Annuler', severity: 'secondary', outlined: true },
    acceptProps: { label: 'Masquer', severity: 'danger' },
    accept: async () => {
      await deleteRole(role.id)
      toast.add({ severity: 'success', summary: 'Rôle masqué' })
      await load()
    },
  })
}

function roleActions(role) {
  if (!canManage.value) return []
  return [
    {
      label: 'Modifier',
      icon: 'pi pi-pencil',
      disabled: !role.isEnabled,
      command: () => openEdit(role),
    },
    {
      label: 'Permissions',
      icon: 'pi pi-shield',
      disabled: !role.isEnabled,
      command: () => openPermissions(role),
    },
    {
      label: 'Masquer',
      icon: 'pi pi-eye-slash',
      severity: 'danger',
      disabled: role.isSystem || !role.isEnabled,
      command: () => askDelete(role),
    },
  ]
}

function onRowContextMenu(event) {
  rowContextMenu.value?.onContextMenu(event.originalEvent, event.data)
}

function roleStatusOf(item) {
  return {
    value: item.isEnabled ? 'Actif' : 'Masqué',
    severity: item.isEnabled ? 'success' : 'warn',
  }
}

function roleSubtitleOf(item) {
  return item.isSystem ? 'Système' : 'Métier'
}

function roleMetaOf(item) {
  return `${(item.permissions ?? []).length} permission${(item.permissions ?? []).length === 1 ? '' : 's'}`
}

function isPermGranted(code) {
  return selectedCodes.value.includes(code)
}

function setPermGranted(code, granted) {
  const idx = selectedCodes.value.indexOf(code)
  if (granted && idx === -1) selectedCodes.value.push(code)
  if (!granted && idx !== -1) selectedCodes.value.splice(idx, 1)
}
</script>

<template>
  <div class="roles-permissions-panel">
    <Card v-if="!embedded" class="dashboard-panel">
      <template #content>
        <AppTablePanelHeader
          title="Rôles & permissions"
          :count-label="`${filteredItems.length}`"
          create-label="Nouveau rôle"
          :show-create="canManage"
          :hide-create-on-mobile="isAppMobile"
          :sticky="isAppMobile"
          show-search
          v-model:search-term="searchTerm"
          search-placeholder="Rechercher libellé, code…"
          @create="openCreate"
          @reload="load"
        >
          <template #actions>
            <AppTableSettingsPopover
              v-model:visible-col-keys="visibleColKeys"
              v-model:rows="tableRows"
              v-model:show-index="showIndex"
              v-model:sort-field="sortField"
              v-model:sort-order="sortOrder"
              :columns="tableColumns"
              :row-options="ROW_OPTIONS"
              :sort-options="sortOptions"
              @toggle-col="toggleCol"
            />
          </template>
        </AppTablePanelHeader>
        <AppTableState
          :loading="loading"
          :is-empty="!loading && filteredItems.length === 0"
          empty-title="Aucun rôle"
          empty-text="Créez un rôle pour commencer."
          @retry="load"
        >
          <AppEntityDataView
            v-if="isAppMobile"
            :items="filteredItems"
            :rows="tableRows"
            :show-index="showIndex"
            :code-of="(item) => item.code"
            :title-of="(item) => item.libelle"
            :subtitle-of="roleSubtitleOf"
            :status-of="roleStatusOf"
            :meta-of="roleMetaOf"
            :actions-of="roleActions"
            :row-bindings-of="(item) => rowContextMenu?.rowBindings(item) ?? {}"
            @select="(item) => canManage && item.isEnabled && openEdit(item)"
          />
          <DataTable
            v-else
            :value="filteredItems"
            paginator
            :rows="tableRows"
            striped-rows
            :sort-field="sortField || undefined"
            :sort-order="sortOrder"
            @row-contextmenu="onRowContextMenu"
          >
            <Column v-if="showIndex" header="#" style="width: 3.5rem">
              <template #body="{ index }">{{ index + 1 }}</template>
            </Column>
            <Column v-if="isColVisible('libelle')" field="libelle" header="Libellé" sortable />
            <Column v-if="isColVisible('code')" field="code" header="Code" sortable />
            <Column v-if="isColVisible('isSystem')" header="Type" sortable field="isSystem">
              <template #body="{ data }">
                <Tag :value="data.isSystem ? 'Système' : 'Métier'" :severity="data.isSystem ? 'info' : 'secondary'" />
              </template>
            </Column>
            <Column v-if="isColVisible('isEnabled')" header="Statut" sortable field="isEnabled">
              <template #body="{ data }">
                <Tag :value="data.isEnabled ? 'Actif' : 'Masqué'" :severity="data.isEnabled ? 'success' : 'warn'" />
              </template>
            </Column>
            <Column v-if="isColVisible('permissions')" header="Permissions">
              <template #body="{ data }">{{ (data.permissions ?? []).length }}</template>
            </Column>
            <Column v-if="canManage" header="Actions" style="width: 10rem">
              <template #body="{ data }">
                <AppTableActionsMenu :actions="roleActions(data)" />
              </template>
            </Column>
          </DataTable>
        </AppTableState>
      </template>
    </Card>

    <template v-else>
      <AppTablePanelHeader
        title="Rôles & permissions"
        :count-label="`${filteredItems.length}`"
        create-label="Nouveau rôle"
        :show-create="canManage"
        :hide-create-on-mobile="isAppMobile"
        :sticky="isAppMobile"
        show-search
        v-model:search-term="searchTerm"
        search-placeholder="Rechercher libellé, code…"
        @create="openCreate"
        @reload="load"
      >
        <template #actions>
          <AppTableSettingsPopover
            v-model:visible-col-keys="visibleColKeys"
            v-model:rows="tableRows"
            v-model:show-index="showIndex"
            v-model:sort-field="sortField"
            v-model:sort-order="sortOrder"
            :columns="tableColumns"
            :row-options="ROW_OPTIONS"
            :sort-options="sortOptions"
            @toggle-col="toggleCol"
          />
        </template>
      </AppTablePanelHeader>
      <AppTableState
        :loading="loading"
        :is-empty="!loading && filteredItems.length === 0"
        empty-title="Aucun rôle"
        empty-text="Créez un rôle pour commencer."
        @retry="load"
      >
        <AppEntityDataView
          v-if="isAppMobile"
          :items="filteredItems"
          :rows="tableRows"
          :show-index="showIndex"
          :code-of="(item) => item.code"
          :title-of="(item) => item.libelle"
          :subtitle-of="roleSubtitleOf"
          :status-of="roleStatusOf"
          :meta-of="roleMetaOf"
          :actions-of="roleActions"
          :row-bindings-of="(item) => rowContextMenu?.rowBindings(item) ?? {}"
          @select="(item) => canManage && item.isEnabled && openEdit(item)"
        />
        <DataTable
          v-else
          :value="filteredItems"
          paginator
          :rows="tableRows"
          striped-rows
          :sort-field="sortField || undefined"
          :sort-order="sortOrder"
          @row-contextmenu="onRowContextMenu"
        >
          <Column v-if="showIndex" header="#" style="width: 3.5rem">
            <template #body="{ index }">{{ index + 1 }}</template>
          </Column>
          <Column v-if="isColVisible('libelle')" field="libelle" header="Libellé" sortable />
          <Column v-if="isColVisible('code')" field="code" header="Code" sortable />
          <Column v-if="isColVisible('isSystem')" header="Type" sortable field="isSystem">
            <template #body="{ data }">
              <Tag :value="data.isSystem ? 'Système' : 'Métier'" :severity="data.isSystem ? 'info' : 'secondary'" />
            </template>
          </Column>
          <Column v-if="isColVisible('isEnabled')" header="Statut" sortable field="isEnabled">
            <template #body="{ data }">
              <Tag :value="data.isEnabled ? 'Actif' : 'Masqué'" :severity="data.isEnabled ? 'success' : 'warn'" />
            </template>
          </Column>
          <Column v-if="isColVisible('permissions')" header="Permissions">
            <template #body="{ data }">{{ (data.permissions ?? []).length }}</template>
          </Column>
          <Column v-if="canManage" header="Actions" style="width: 10rem">
            <template #body="{ data }">
              <AppTableActionsMenu :actions="roleActions(data)" />
            </template>
          </Column>
        </DataTable>
      </AppTableState>
    </template>

    <AppMobileFab
      v-if="isAppMobile && canManage"
      aria-label="Nouveau rôle"
      @click="openCreate"
    />

    <AppRowContextMenu ref="rowContextMenu" :actions-of="roleActions" />

    <Dialog v-model:visible="dialog" :header="editingId ? 'Modifier le rôle' : 'Nouveau rôle'" modal style="width: min(420px, 95vw)">
      <div v-if="!editingId" class="field">
        <label>Code <span class="required">*</span></label>
        <InputText v-model="form.code" :invalid="Boolean(fieldErrors.code)" fluid placeholder="TECHNICIEN" />
        <AppFieldError :message="fieldErrors.code" />
      </div>
      <div class="field">
        <label>Libellé <span class="required">*</span></label>
        <InputText v-model="form.libelle" :invalid="Boolean(fieldErrors.libelle)" fluid />
        <AppFieldError :message="fieldErrors.libelle" />
      </div>
      <template #footer>
        <Button label="Annuler" text @click="dialog = false" />
        <Button label="Enregistrer" icon="pi pi-check" :loading="saving" @click="saveRole" />
      </template>
    </Dialog>

    <Dialog v-model:visible="permDialog" header="Permissions par défaut" modal class="perm-dialog" style="width: min(680px, 95vw)">
      <p v-if="selectedRole" class="perm-user">
        Rôle : <strong>{{ selectedRole.libelle }}</strong>
        <span class="perm-role">({{ selectedRole.code }})</span>
      </p>
      <div class="perm-legend" aria-hidden="true">
        <span class="perm-legend__chip perm-legend__chip--grant">Autoriser</span>
        <span class="perm-legend__chip perm-legend__chip--deny">Non autorisé</span>
      </div>
      <Accordion v-model:value="expandedZones" multiple class="perm-accordion">
        <AccordionPanel v-for="zone in groupedPermissions" :key="zone.module" :value="zone.module">
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
                    v-for="opt in permRoleOptions"
                    :key="String(opt.value)"
                    type="button"
                    class="perm-segment__btn"
                    :class="[
                      `perm-segment__btn--${opt.tone}`,
                      { 'perm-segment__btn--active': isPermGranted(perm.code) === opt.value },
                    ]"
                    @click="setPermGranted(perm.code, opt.value)"
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
        <Button label="Enregistrer" icon="pi pi-check" :loading="savingPerms" @click="savePermissions" />
      </template>
    </Dialog>
  </div>
</template>

<style scoped>
.field { margin-bottom: 0.85rem; }
.required { color: var(--p-red-500, #ef4444); }

.perm-user { margin: 0 0 1rem; }
.perm-role { margin-left: 0.35rem; color: var(--p-text-muted-color); font-size: 0.85rem; }

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

.perm-legend__chip--grant { background: #16a34a; color: #fff; }
.perm-legend__chip--deny { background: #dc2626; color: #fff; }

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

.perm-accordion :deep(.p-accordionpanel:last-child) { margin-bottom: 0; }

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

.perm-row:last-child { border-bottom: none; }
.perm-row__label { line-height: 1.35; }

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
  min-width: 5.2rem;
  font-size: 0.74rem;
  font-weight: 600;
  line-height: 1;
  cursor: pointer;
  background: var(--p-content-background);
  color: var(--p-text-muted-color);
  transition: background-color 0.15s ease, color 0.15s ease;
}

.perm-segment__btn:last-child { border-right: none; }

.perm-segment__btn:hover:not(.perm-segment__btn--active) {
  background: color-mix(in srgb, var(--p-content-background) 85%, var(--p-text-color) 15%);
}

.perm-segment__btn--grant:not(.perm-segment__btn--active) { color: #15803d; }
.perm-segment__btn--deny:not(.perm-segment__btn--active) { color: #b91c1c; }
.perm-segment__btn--active.perm-segment__btn--grant { background: #16a34a; color: #fff; }
.perm-segment__btn--active.perm-segment__btn--deny { background: #dc2626; color: #fff; }

@media (max-width: 640px) {
  .perm-row {
    grid-template-columns: 1fr;
    gap: 0.5rem;
  }

  .perm-segment { width: 100%; }

  .perm-segment__btn {
    flex: 1;
    min-width: 0;
    padding-inline: 0.35rem;
  }
}
</style>
