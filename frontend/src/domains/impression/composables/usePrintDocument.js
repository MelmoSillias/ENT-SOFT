import { impressionService } from '@/domains/impression/services/impressionService'
import { usePrintSettingsStore } from '@/domains/impression/stores/printSettings'
import { useAppToast } from '@/domains/shared/composables/useAppToast'

const openBlob = (blob, { filename, openInNewTab = true, download = false } = {}) => {
  const url = URL.createObjectURL(blob)

  if (download && filename) {
    const link = document.createElement('a')
    link.href = url
    link.download = filename
    link.click()
  }

  if (openInNewTab) {
    window.open(url, '_blank', 'noopener,noreferrer')
  }

  setTimeout(() => URL.revokeObjectURL(url), 60_000)
}

const extensionForFormat = (format) => {
  const map = { html: 'html', pdf: 'pdf', excel: 'xlsx', csv: 'csv', word: 'docx' }
  return map[format] || format
}

async function readBlobError(error) {
  const data = error?.response?.data
  if (data instanceof Blob) {
    try {
      const text = await data.text()
      const json = JSON.parse(text)
      return json.error || text
    } catch {
      return 'Erreur lors de l\'impression / export.'
    }
  }
  return error?.response?.data?.error || error?.message || 'Erreur lors de l\'impression / export.'
}

export function usePrintDocument() {
  const printSettingsStore = usePrintSettingsStore()
  const toast = useAppToast()

  const printDocument = async (type, id, options = {}) => {
    try {
      await printSettingsStore.fetchSettings()
      const page = options.page || printSettingsStore.defaultPageFor(type)
      const orientation = options.orientation || printSettingsStore.defaultOrientationFor(type)
      const blob = await impressionService.fetchDocument(type, id, {
        format: 'html',
        page,
        orientation,
        disposition: 'inline',
        from: options.from,
        to: options.to,
      })
      openBlob(blob, { openInNewTab: true })
    } catch (error) {
      toast.add({ severity: 'error', summary: 'Impression', detail: await readBlobError(error) })
    }
  }

  const exportDocument = async (type, id, format = 'pdf', options = {}) => {
    try {
      await printSettingsStore.fetchSettings()
      const page = options.page || printSettingsStore.defaultPageFor(type)
      const orientation = options.orientation || printSettingsStore.defaultOrientationFor(type)
      const blob = await impressionService.fetchDocument(type, id, {
        format,
        page,
        orientation,
        disposition: format === 'html' ? 'inline' : 'attachment',
      })
      const filename = `${type}-${String(id).slice(0, 8)}.${extensionForFormat(format)}`
      openBlob(blob, {
        filename,
        openInNewTab: ['pdf', 'word', 'html'].includes(format),
        download: !['pdf', 'word', 'html'].includes(format),
      })
    } catch (error) {
      toast.add({ severity: 'error', summary: 'Export', detail: await readBlobError(error) })
    }
  }

  return { printDocument, exportDocument }
}

export function useTablePrint() {
  const printSettingsStore = usePrintSettingsStore()
  const toast = useAppToast()

  const printTable = async (tableType, { filters = {}, columns = [], search = '', page, orientation } = {}) => {
    try {
      await printSettingsStore.fetchSettings()
      const blob = await impressionService.printTable(tableType, {
        filters,
        columns,
        search,
        page: page || printSettingsStore.defaultPageFor('table'),
        orientation: orientation || printSettingsStore.defaultOrientationFor('table'),
      })
      openBlob(blob, { openInNewTab: true })
    } catch (error) {
      toast.add({ severity: 'error', summary: 'Impression', detail: await readBlobError(error) })
    }
  }

  return { printTable }
}

export function useTableExport() {
  const printSettingsStore = usePrintSettingsStore()
  const toast = useAppToast()

  const exportTable = async (tableType, format, { filters = {}, columns = [], search = '', page, orientation } = {}) => {
    try {
      await printSettingsStore.fetchSettings()
      const resolvedFormat = format || printSettingsStore.defaultExportFormat
      const blob = await impressionService.exportTable(tableType, {
        format: resolvedFormat,
        filters,
        columns,
        search,
        page: page || printSettingsStore.defaultPageFor('table'),
        orientation: orientation || printSettingsStore.defaultOrientationFor('table'),
      })
      const filename = `${tableType}-${new Date().toISOString().slice(0, 10)}.${extensionForFormat(resolvedFormat)}`
      openBlob(blob, {
        filename,
        openInNewTab: ['pdf', 'word'].includes(resolvedFormat),
        download: true,
      })
      toast.add({ severity: 'success', summary: 'Export', detail: 'Export généré avec succès.' })
    } catch (error) {
      toast.add({ severity: 'error', summary: 'Export', detail: await readBlobError(error) })
    }
  }

  return { exportTable }
}
