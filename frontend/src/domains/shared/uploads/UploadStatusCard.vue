<template>
  <div v-if="queue.hasActivity" class="relative">
    <button
      type="button"
      class="upload-status-trigger"
      :title="`Téléversement ${queue.globalPercent} %`"
      ref="trigger"
      @click="toggle"
    >
      <i class="pi pi-cloud-upload" aria-hidden="true" />
      <span class="upload-status-value">{{ queue.globalPercent }}%</span>
    </button>
    <Popover
      ref="popover"
      v-model:visible="visible"
      :autoHide="true"
      :dismissable="true"
      :target="trigger"
      position="bottom"
      class="upload-queue-popover shadow-lg rounded-lg p-3"
    >
      <div class="upload-queue-head">
        <span>Téléversements</span>
        <span class="upload-queue-head-pct">{{ queue.globalPercent }}%</span>
      </div>
      <div v-if="!queue.visibleItems.length && !queue.visibleJobs.length" class="upload-queue-empty">
        Aucun fichier en cours
      </div>
      <ul v-else class="upload-queue-list">
        <li v-for="job in queue.visibleJobs" :key="`job-${job.id}`" class="upload-queue-item">
          <div class="upload-queue-item-row">
            <span class="upload-queue-name" :title="job.name">{{ job.name }}</span>
            <span class="upload-queue-status" :data-status="job.status">{{ jobStatusLabel(job) }}</span>
          </div>
          <ProgressBar
            :value="job.status === 'done' ? 100 : job.status === 'failed' ? 0 : Math.max(queue.globalPercent, 15)"
            :showValue="false"
            style="height: 0.35rem"
          />
          <p v-if="job.error" class="upload-queue-error">{{ job.error }}</p>
          <div class="upload-queue-actions">
            <Button v-if="job.status === 'failed'" label="Reprendre" icon="pi pi-replay" size="small" text @click="queue.retryJob(job.id)" />
            <Button
              v-if="job.status === 'failed'"
              label="Fermer"
              icon="pi pi-times"
              size="small"
              text
              severity="secondary"
              @click="queue.dismissJob(job.id)"
            />
          </div>
        </li>
        <li v-for="item in queue.visibleItems" :key="item.id" class="upload-queue-item">
          <div class="upload-queue-item-row">
            <span class="upload-queue-name" :title="item.name">{{ item.name }}</span>
            <span class="upload-queue-status" :data-status="item.status">{{ statusLabel(item) }}</span>
          </div>
          <div class="upload-queue-size">{{ sizeLabel(item) }}</div>
          <ProgressBar :value="item.progress || 0" :showValue="false" style="height: 0.35rem" />
          <p v-if="item.error" class="upload-queue-error">{{ item.error }}</p>
          <div class="upload-queue-actions">
            <Button v-if="item.status === 'failed'" label="Reprendre" icon="pi pi-replay" size="small" text @click="queue.retry(item.id)" />
            <Button
              v-if="['queued', 'uploading', 'failed', 'completing'].includes(item.status)"
              label="Annuler"
              icon="pi pi-times"
              size="small"
              text
              severity="danger"
              @click="queue.cancel(item.id)"
            />
          </div>
        </li>
      </ul>
    </Popover>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import Popover from 'primevue/popover'
import Button from 'primevue/button'
import ProgressBar from 'primevue/progressbar'
import { useUploadQueue } from './uploadQueue'
import { formatSizeTransition } from './uploadApi'

const queue = useUploadQueue()
const visible = ref(false)
const trigger = ref(null)
const popover = ref(null)

function toggle(event) {
  if (visible.value) {
    popover.value?.hide()
    visible.value = false
    return
  }
  popover.value?.show(event)
  visible.value = true
}

function statusLabel(item) {
  return (
    {
      compressing: 'Compression',
      queued: 'En file',
      uploading: `${item.progress || 0} %`,
      completing: 'Finalisation',
      ready: 'Prêt',
      attached: 'Terminé',
      failed: 'Échec',
    }[item.status] || item.status
  )
}

function jobStatusLabel(job) {
  return (
    {
      queued: 'En file',
      running: 'Enregistrement',
      done: 'Terminé',
      failed: 'Échec',
    }[job.status] || job.status
  )
}

function sizeLabel(item) {
  return formatSizeTransition(item.originalSize || item.size, item.size)
}
</script>

<style scoped>
.upload-status-trigger {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  height: 2.125rem;
  padding: 0 0.55rem;
  border: 0;
  cursor: pointer;
  border-radius: 6px;
  color: var(--text-color);
  font-size: 0.8125rem;
  font-weight: 600;
  background: color-mix(in srgb, var(--p-primary-color, var(--primary-color, #3b82f6)) 10%, transparent);
}

.upload-status-trigger i {
  font-size: 0.95rem;
  color: var(--p-primary-color, var(--primary-color, #3b82f6));
}

.upload-queue-popover {
  background: var(--p-content-background, var(--surface-card, #fff));
  width: 20rem;
  max-width: calc(100vw - 2rem);
}

.upload-queue-head {
  display: flex;
  justify-content: space-between;
  font-weight: 600;
  margin-bottom: 0.75rem;
}

.upload-queue-head-pct {
  color: var(--p-primary-color, var(--primary-color, #3b82f6));
}

.upload-queue-empty {
  font-size: 0.8125rem;
  color: var(--p-text-muted-color, #64748b);
}

.upload-queue-list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 0.85rem;
  max-height: 18rem;
  overflow: auto;
}

.upload-queue-item-row {
  display: flex;
  justify-content: space-between;
  gap: 0.5rem;
  font-size: 0.8125rem;
}

.upload-queue-name {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.upload-queue-status {
  flex-shrink: 0;
  color: var(--p-text-muted-color, #64748b);
  font-variant-numeric: tabular-nums;
}

.upload-queue-status[data-status='failed'] {
  color: var(--red-500, #ef4444);
}

.upload-queue-status[data-status='attached'],
.upload-queue-status[data-status='ready'],
.upload-queue-status[data-status='done'] {
  color: var(--green-500, #22c55e);
}

.upload-queue-size {
  font-size: 0.75rem;
  color: var(--p-text-muted-color, #64748b);
  margin: 0.15rem 0 0.35rem;
}

.upload-queue-error {
  margin: 0.35rem 0 0;
  font-size: 0.75rem;
  color: var(--red-500, #ef4444);
}

.upload-queue-actions {
  display: flex;
  justify-content: flex-end;
  gap: 0.15rem;
  margin-top: 0.15rem;
}
</style>
