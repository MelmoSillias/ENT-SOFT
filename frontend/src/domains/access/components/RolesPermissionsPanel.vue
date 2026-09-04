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
import Checkbox from 'primevue/checkbox'
import { useConfirm } from 'primevue/useconfirm'
import AppTablePanelHeader from '@/domains/shared/components/AppTablePanelHeader.vue'
import AppTableState from '@/domains/shared/components/AppTableState.vue'
import AppTableSettingsPopover from '@/domains/shared/components/AppTableSettingsPopover.vue'
import AppRowContextMenu from '@/domains/shared/components/AppRowContextMenu.vue'
import AppTableActionsMenu from '@/domains/shared/components/AppTableActionsMenu.vue'
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
</script>

<template>
  <div class="roles-permissions-panel">
    <Card v-if="!embedded" class="dashboard-panel">
      <template #title>
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
      </template>
      <template #content>
        <AppTableState
          :loading="loading"
          :is-empty="!loading && filteredItems.length === 0"
          empty-title="Aucun rôle"
          empty-text="Créez un rôle pour commencer."
          @retry="load"
        >
          <DataTable
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
        <DataTable
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

    <Dialog v-model:visible="permDialog" header="Permissions par défaut" modal style="width: min(680px, 95vw)">
      <p v-if="selectedRole" class="perm-user">
        Rôle : <strong>{{ selectedRole.libelle }}</strong>
        <span class="perm-role">({{ selectedRole.code }})</span>
      </p>
      <Accordion v-model:value="expandedZones" multiple>
        <AccordionPanel v-for="zone in groupedPermissions" :key="zone.module" :value="zone.module">
          <AccordionHeader>{{ zone.label }}</AccordionHeader>
          <AccordionContent>
            <div v-for="perm in zone.permissions" :key="perm.code" class="perm-check-row">
              <Checkbox v-model="selectedCodes" :input-id="perm.code" :value="perm.code" />
              <label :for="perm.code">{{ perm.libelle }}</label>
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
.perm-check-row {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  padding: 0.35rem 0;
}
</style>
