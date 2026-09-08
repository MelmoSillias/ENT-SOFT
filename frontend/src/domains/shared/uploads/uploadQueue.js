import { defineStore } from 'pinia'
import { compressFile } from './compressFile'
import { deleteUploadBlob, getUploadBlob, listUploadBlobs, saveUploadBlob } from './uploadDb'
import {
  MAX_UPLOAD_BYTES,
  abortUploadSession,
  completeUploadSession,
  createUploadSession,
  getUploadStatus,
  putUploadChunk,
  sha256Hex,
} from './uploadApi'

const META_KEY = 'entsoft.uploadQueue'
const MAX_RETRIES = 3
const DONE_STATUSES = ['ready', 'attached']

function uid() {
  return crypto.randomUUID ? crypto.randomUUID() : bin2hex()
}

function bin2hex() {
  const bytes = new Uint8Array(16)
  crypto.getRandomValues(bytes)
  return [...bytes].map((b) => b.toString(16).padStart(2, '0')).join('')
}

function sleep(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms))
}

function loadMeta() {
  try {
    const raw = localStorage.getItem(META_KEY)
    const parsed = raw ? JSON.parse(raw) : []
    return Array.isArray(parsed) ? parsed : []
  } catch {
    return []
  }
}

export const useUploadQueue = defineStore('uploadQueue', {
  state: () => ({
    items: loadMeta(),
    jobs: [],
    booted: false,
    pumping: {},
  }),

  getters: {
    visibleItems: (state) =>
      state.items.filter(
        (item) => !['aborted', 'staged'].includes(item.status) && !(item.status === 'attached' && item.dismissed),
      ),
    visibleJobs: (state) => state.jobs.filter((job) => !job.dismissed && job.status !== 'done'),
    inFlight: (state) => state.items.filter((item) => ['queued', 'uploading', 'completing'].includes(item.status)),
    failedItems: (state) => state.items.filter((item) => item.status === 'failed'),
    activeJobs: (state) => state.jobs.filter((job) => ['queued', 'running'].includes(job.status)),
    globalPercent: (state) => {
      const active = state.items.filter((item) => ['queued', 'uploading', 'completing', 'failed'].includes(item.status))
      if (!active.length) {
        return state.jobs.some((job) => ['queued', 'running'].includes(job.status)) ? 100 : 0
      }
      const total = active.reduce((sum, item) => sum + (item.size || 0), 0)
      const sent = active.reduce((sum, item) => sum + (item.bytesReceived || 0), 0)
      if (!total) {
        return 0
      }
      return Math.min(100, Math.round((sent / total) * 100))
    },
    hasActivity() {
      return this.inFlight.length > 0 || this.failedItems.length > 0 || this.activeJobs.length > 0
    },
  },

  actions: {
    persist() {
      const slim = this.items
        .filter((item) => item.sessionId && ['queued', 'uploading', 'completing', 'failed'].includes(item.status))
        .map(({ blob: _blob, ...rest }) => rest)
      localStorage.setItem(META_KEY, JSON.stringify(slim))
    },

    upsert(item) {
      const index = this.items.findIndex((row) => row.id === item.id)
      if (index >= 0) {
        this.items[index] = { ...this.items[index], ...item }
      } else {
        this.items.unshift(item)
      }
      this.persist()
    },

    removeLocal(localId) {
      this.items = this.items.filter((row) => row.id !== localId)
      this.persist()
    },

    /**
     * Prépare un fichier localement (compression images, contrôle de taille)
     * sans démarrer l'envoi. Retourne { localId, file, name, ... }.
     */
    async stage(file, options = {}) {
      const maxSize = options.maxSize ?? MAX_UPLOAD_BYTES
      const compressed = await compressFile(file)
      const readyFile = compressed.file
      if (readyFile.size > maxSize) {
        throw new Error(`Fichier trop volumineux (max ${Math.round(maxSize / (1024 * 1024))} Mo)`)
      }

      const localId = uid()
      this.upsert({
        id: localId,
        sessionId: null,
        name: readyFile.name,
        originalSize: compressed.originalSize,
        size: readyFile.size,
        mimeType: readyFile.type,
        status: 'staged',
        progress: 0,
        bytesReceived: 0,
        error: null,
        receivedChunks: [],
        chunkCount: 0,
        blob: readyFile,
      })

      return {
        localId,
        sessionId: null,
        file: readyFile,
        name: readyFile.name,
        originalSize: compressed.originalSize,
        compressedSize: readyFile.size,
        compressed: compressed.compressed,
      }
    },

    async startSession(localId) {
      const item = this.items.find((row) => row.id === localId)
      if (!item) {
        throw new Error('Fichier introuvable')
      }
      if (item.sessionId) {
        return item.sessionId
      }

      const readyFile = item.blob
      if (!(readyFile instanceof Blob)) {
        this.upsert({ id: localId, status: 'failed', error: 'Fichier local introuvable, resélectionnez-le.' })
        throw new Error('Fichier local introuvable, resélectionnez-le.')
      }

      try {
        const checksum = await sha256Hex(readyFile)
        const session = await createUploadSession({
          filename: readyFile.name,
          size: readyFile.size,
          mimeType: readyFile.type || 'application/octet-stream',
          checksum,
        })

        await saveUploadBlob({
          id: localId,
          sessionId: session.id,
          blob: readyFile,
          name: readyFile.name,
          type: readyFile.type,
          size: readyFile.size,
          originalSize: item.originalSize,
        })

        this.upsert({
          id: localId,
          sessionId: session.id,
          chunkCount: session.chunkCount,
          chunkSize: session.chunkSize,
          status: 'queued',
          error: null,
        })

        return session.id
      } catch (error) {
        this.upsert({
          id: localId,
          status: 'failed',
          error: error?.response?.data?.error || error?.message || 'Impossible de créer la session',
        })
        throw error
      }
    },

    /**
     * Envoie le fichier (session + chunks + complete) et retourne le sessionId
     * à transmettre à l'API métier (ex: POST /documents/upload { uploadSessionId }).
     */
    async commit(localId) {
      const item = this.items.find((row) => row.id === localId)
      if (!item) {
        throw new Error('Fichier introuvable')
      }
      if (DONE_STATUSES.includes(item.status) && item.sessionId) {
        return item.sessionId
      }

      if (!item.sessionId) {
        await this.startSession(localId)
      } else if (item.status === 'failed') {
        this.upsert({ id: localId, status: 'queued', error: null })
      }

      await this.pump(localId)

      const done = this.items.find((row) => row.id === localId)
      if (!done || !DONE_STATUSES.includes(done.status) || !done.sessionId) {
        throw new Error(done?.error || "Échec d'envoi du fichier")
      }
      return done.sessionId
    },

    async commitMany(localIds) {
      const ids = [...new Set((localIds || []).filter(Boolean))]
      await Promise.all(ids.map((id) => this.commit(id)))
      return (localIds || []).map((id) => {
        if (!id) return null
        const item = this.items.find((row) => row.id === id)
        return item?.sessionId || null
      })
    },

    async pump(localId) {
      if (this.pumping[localId]) {
        return this.pumping[localId]
      }
      const run = this.uploadItem(localId).finally(() => {
        delete this.pumping[localId]
      })
      this.pumping[localId] = run
      return run
    },

    async uploadItem(localId) {
      const item = this.items.find((row) => row.id === localId)
      if (!item?.sessionId) {
        return
      }

      const stored = await getUploadBlob(localId)
      const blob = stored?.blob || item.blob
      if (!blob) {
        this.upsert({ id: localId, status: 'failed', error: 'Fichier local introuvable, resélectionnez-le.' })
        return
      }

      let status
      try {
        status = await getUploadStatus(item.sessionId)
      } catch (error) {
        this.upsert({ id: localId, status: 'failed', error: error?.response?.data?.error || 'Statut serveur indisponible' })
        return
      }

      if (status.status === 'ready' || status.status === 'attached') {
        this.upsert({
          id: localId,
          status: status.status,
          progress: 100,
          bytesReceived: item.size,
        })
        await deleteUploadBlob(localId)
        return
      }

      const chunkSize = status.chunkSize || item.chunkSize || 4 * 1024 * 1024
      const chunkCount = status.chunkCount || item.chunkCount || Math.ceil(blob.size / chunkSize)
      const received = new Set(status.receivedChunks || [])

      this.upsert({
        id: localId,
        status: 'uploading',
        chunkCount,
        chunkSize,
        receivedChunks: [...received],
        bytesReceived: Math.min(blob.size, received.size * chunkSize),
      })

      for (let index = 0; index < chunkCount; index += 1) {
        const current = this.items.find((row) => row.id === localId)
        if (!current || current.status === 'aborted') {
          return
        }
        if (received.has(index)) {
          continue
        }
        const start = index * chunkSize
        const end = Math.min(blob.size, start + chunkSize)
        const slice = blob.slice(start, end)
        let lastError = null
        for (let attempt = 0; attempt < MAX_RETRIES; attempt += 1) {
          try {
            const result = await putUploadChunk(item.sessionId, index, slice)
            received.add(index)
            const bytesReceived = result.bytesReceived ?? Math.min(blob.size, received.size * chunkSize)
            this.upsert({
              id: localId,
              status: 'uploading',
              receivedChunks: [...received],
              bytesReceived,
              progress: Math.round((bytesReceived / blob.size) * 100),
              error: null,
            })
            lastError = null
            break
          } catch (error) {
            lastError = error
            await sleep(400 * 2 ** attempt)
          }
        }
        if (lastError) {
          this.upsert({
            id: localId,
            status: 'failed',
            error: lastError?.response?.data?.error || lastError?.message || "Échec d'envoi d'un bloc",
          })
          return
        }
      }

      this.upsert({ id: localId, status: 'completing', progress: 99 })
      try {
        const done = await completeUploadSession(item.sessionId)
        this.upsert({
          id: localId,
          status: done.status === 'attached' ? 'attached' : 'ready',
          progress: 100,
          bytesReceived: blob.size,
        })
        await deleteUploadBlob(localId)
      } catch (error) {
        this.upsert({
          id: localId,
          status: 'failed',
          error: error?.response?.data?.error || 'Finalisation impossible',
        })
      }
    },

    retry(localId) {
      const item = this.items.find((row) => row.id === localId)
      if (!item) {
        return
      }
      if (item.sessionId) {
        this.upsert({ id: localId, status: 'queued', error: null })
        this.pump(localId)
        return
      }
      this.commit(localId).catch(() => {})
    },

    async cancel(localId) {
      const item = this.items.find((row) => row.id === localId)
      if (!item) {
        return
      }
      this.upsert({ id: localId, status: 'aborted' })
      if (item.sessionId) {
        try {
          await abortUploadSession(item.sessionId)
        } catch {
          // ignore
        }
      }
      await deleteUploadBlob(localId)
    },

    async discard(localId) {
      const item = this.items.find((row) => row.id === localId)
      if (!item) {
        return
      }
      if (item.sessionId) {
        await this.cancel(localId)
        return
      }
      this.removeLocal(localId)
    },

    async discardMany(localIds) {
      for (const id of [...new Set((localIds || []).filter(Boolean))]) {
        await this.discard(id)
      }
    },

    dismiss(localId) {
      this.upsert({ id: localId, dismissed: true })
    },

    /** Tâche d'arrière-plan (ex: enregistrement métier après upload) avec retry UI. */
    enqueueJob({ name, task }) {
      const id = uid()
      const job = {
        id,
        name: name || 'Enregistrement',
        status: 'queued',
        error: null,
        dismissed: false,
        retryTask: task,
      }
      this.jobs.unshift(job)
      void this.runJob(id)
      return id
    },

    async runJob(jobId) {
      const job = this.jobs.find((row) => row.id === jobId)
      if (!job?.retryTask) return
      this.patchJob(jobId, { status: 'running', error: null, dismissed: false })
      try {
        await job.retryTask()
        this.patchJob(jobId, { status: 'done' })
        setTimeout(() => this.dismissJob(jobId), 2500)
      } catch (error) {
        this.patchJob(jobId, {
          status: 'failed',
          error: error?.response?.data?.error || error?.message || 'Échec',
        })
      }
    },

    patchJob(jobId, patch) {
      const index = this.jobs.findIndex((job) => job.id === jobId)
      if (index < 0) return
      this.jobs[index] = { ...this.jobs[index], ...patch }
    },

    dismissJob(jobId) {
      this.patchJob(jobId, { dismissed: true })
    },

    retryJob(jobId) {
      void this.runJob(jobId)
    },

    /** Reprise des uploads interrompus (à appeler au démarrage de l'app). */
    async boot() {
      if (this.booted) {
        return
      }
      this.booted = true
      const stored = await listUploadBlobs()
      const byId = Object.fromEntries(stored.map((row) => [row.id, row]))
      for (const item of [...this.items]) {
        if (!item.sessionId || item.status === 'staged') {
          this.removeLocal(item.id)
          continue
        }
        const resumable = ['uploading', 'queued', 'completing', 'failed', 'compressing'].includes(item.status)
        if (resumable && (byId[item.id] || item.sessionId)) {
          if (item.status !== 'failed') {
            this.upsert({ id: item.id, status: 'queued' })
          }
          this.pump(item.id)
        } else if (resumable) {
          this.upsert({
            id: item.id,
            status: 'failed',
            error: 'Fichier local introuvable, resélectionnez-le.',
          })
        }
      }
    },
  },
})

export function installUploadUnloadGuard() {
  window.addEventListener('beforeunload', (event) => {
    try {
      const queue = useUploadQueue()
      if (queue.inFlight.length || queue.activeJobs.length) {
        event.preventDefault()
        event.returnValue = ''
      }
    } catch {
      // pinia pas prêt
    }
  })
}
