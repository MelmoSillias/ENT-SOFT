import { useUploadQueue } from './uploadQueue'

/**
 * Composable simple pour les intégrations ponctuelles :
 * donne-lui un File, il retourne un uploadSessionId prêt à être
 * transmis à l'API métier.
 *
 * Exemple :
 *   const { uploadFile } = useChunkedUpload()
 *   const uploadSessionId = await uploadFile(file)
 *   await api.post('/documents/upload', { uploadSessionId, title, ownerType, ownerId })
 */
export function useChunkedUpload() {
  const queue = useUploadQueue()

  async function uploadFile(file, options = {}) {
    const staged = await queue.stage(file, options)
    return queue.commit(staged.localId)
  }

  async function stageFile(file, options = {}) {
    return queue.stage(file, options)
  }

  async function commitStaged(localId) {
    return queue.commit(localId)
  }

  async function discardStaged(localId) {
    return queue.discard(localId)
  }

  return { queue, uploadFile, stageFile, commitStaged, discardStaged }
}
