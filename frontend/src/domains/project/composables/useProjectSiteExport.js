/**
 * Shared export helpers for ProjectSitesTable.
 * Excel + image generation run in a Web Worker so the UI stays responsive.
 * Word uses docx on the main thread after yielding to the event loop.
 */

import { toRaw } from 'vue'
import { formatDateFr } from '@/domains/shared/utils/entLabels'

export const STATUS_COLORS = {
  pending: '64748B',
  in_progress: 'D97706',
  completed: '16A34A',
  blocked: 'DC2626',
}

const STATUS_SEVERITY = {
  pending: 'secondary',
  in_progress: 'warn',
  completed: 'success',
  blocked: 'danger',
}

function statusFill(status) {
  return STATUS_COLORS[status] ?? STATUS_COLORS.pending
}

export function buildRows(sites, columns) {
  return sites.map((site, idx) => {
    const row = { '#': idx + 1, __statusKey: site.status ?? 'pending' }
    for (const col of columns) {
      if (col.field === 'siteCode') row['Code site'] = site.siteCode ?? ''
      else if (col.field === 'siteTitle') row['Nom du site'] = site.siteTitle ?? ''
      else if (col.key === '__technician') row['Techniciens'] = site.technicianName ?? ''
      else if (col.key === '__status') row['Statut'] = site.statusLabel ?? site.status ?? ''
      else if (col.key === '__comment') row['Commentaires'] = site.comment ?? ''
      else if (col.key) {
        const raw = site.informationsValues?.[col.key]
        row[col.label ?? col.key] =
          raw === null || raw === undefined || raw === '' ? '' : String(raw)
      }
    }
    return row
  })
}

/** Display-faithful rows for image export (matches on-screen cell formatting). */
export function buildImageRows(sites, columns) {
  return sites.map((site, idx) => {
    const row = { '#': idx + 1, __statusKey: site.status ?? 'pending' }
    for (const col of columns) {
      if (col.field === 'siteCode') row['Code site'] = displayOrDash(site.siteCode)
      else if (col.field === 'siteTitle') row['Nom du site'] = displayOrDash(site.siteTitle)
      else if (col.key === '__technician') row['Techniciens'] = site.technicianName || '—'
      else if (col.key === '__status') row['Statut'] = site.statusLabel ?? site.status ?? '—'
      else if (col.key === '__comment') row['Commentaires'] = site.comment || '—'
      else if (col.key) row[col.label ?? col.key] = formatInfoCell(site, col.key)
    }
    return row
  })
}

function displayOrDash(value) {
  if (value === null || value === undefined || value === '') return '—'
  return String(value)
}

function formatInfoCell(site, key) {
  const raw = site.informationsValues?.[key]
  if (raw === null || raw === undefined || raw === '') return '—'
  if (typeof raw === 'boolean') return raw ? 'Oui' : 'Non'
  if (key.endsWith('_date') || key === 'start_date' || key === 'end_date') {
    return formatDateFr(raw)
  }
  return String(raw)
}

export function flatSites(groupedSites) {
  return groupedSites.flatMap((g) => g.sites)
}

function displayHeaders(rows) {
  const keys = Object.keys(rows[0] ?? { '#': 1 })
  return keys.filter((k) => k !== '__statusKey')
}

function headersFromColumns(columns) {
  const headers = ['#']
  for (const col of columns) {
    if (col.field === 'siteCode') headers.push('Code site')
    else if (col.field === 'siteTitle') headers.push('Nom du site')
    else if (col.key === '__technician') headers.push('Techniciens')
    else if (col.key === '__status') headers.push('Statut')
    else if (col.key === '__comment') headers.push('Commentaires')
    else if (col.key) headers.push(col.label ?? col.key)
  }
  return headers
}

function yieldToMain() {
  return new Promise((resolve) => {
    if (typeof requestIdleCallback === 'function') {
      requestIdleCallback(() => resolve(), { timeout: 50 })
    } else {
      setTimeout(resolve, 0)
    }
  })
}

function createExportWorker() {
  return new Worker(new URL('../workers/projectSiteExport.worker.js', import.meta.url), {
    type: 'module',
  })
}

function runWorkerJob(worker, message) {
  return new Promise((resolve, reject) => {
    const onMessage = (event) => {
      worker.removeEventListener('message', onMessage)
      worker.removeEventListener('error', onError)
      if (event.data?.ok) resolve(event.data)
      else reject(new Error(event.data?.error || 'Export échoué'))
    }
    const onError = (err) => {
      worker.removeEventListener('message', onMessage)
      worker.removeEventListener('error', onError)
      reject(err instanceof Error ? err : new Error('Worker export error'))
    }
    worker.addEventListener('message', onMessage)
    worker.addEventListener('error', onError)
    worker.postMessage(message)
  })
}

function cssColor(el, prop, fallback) {
  if (!el) return fallback
  const value = getComputedStyle(el).getPropertyValue(prop).trim()
  return value || fallback
}

function sampleTagStyles() {
  const severities = ['secondary', 'warn', 'success', 'danger']
  const host = document.createElement('div')
  host.setAttribute('aria-hidden', 'true')
  host.style.cssText = 'position:fixed;left:-99999px;top:0;pointer-events:none;opacity:0'
  document.body.appendChild(host)
  const styles = {}
  try {
    for (const severity of severities) {
      const el = document.createElement('span')
      el.className = `p-tag p-tag-${severity}`
      el.textContent = 'Tag'
      host.appendChild(el)
      const cs = getComputedStyle(el)
      styles[severity] = {
        bg: cs.backgroundColor || '#e2e8f0',
        color: cs.color || '#64748b',
      }
    }
  } finally {
    host.remove()
  }
  return styles
}

export function readExportTheme(themeRoot) {
  const root =
    themeRoot?.closest?.('.pst-root') ??
    themeRoot ??
    document.querySelector('.pst-root') ??
    document.documentElement
  const cs = getComputedStyle(root)
  return {
    background: cssColor(root, '--pst-row-bg', cssColor(root, '--layout-panel-bg', '#ffffff')),
    headerBg: cssColor(root, '--pst-header-bg', '#f1f5f9'),
    rowBg: cssColor(root, '--pst-row-bg', '#ffffff'),
    border: cssColor(root, '--pst-border', '#d8e0ec'),
    text: cssColor(root, '--pst-text', '#1a2744'),
    textMuted: cssColor(root, '--pst-text-muted', '#5c6b82'),
    fontFamily: cs.fontFamily || 'system-ui, -apple-system, Segoe UI, sans-serif',
    tagStyles: sampleTagStyles(),
    statusSeverity: { ...STATUS_SEVERITY },
  }
}

// ─── Excel (Web Worker + ExcelJS) ─────────────────────────────────────────────

export async function exportExcel({ groupedSites, columns, projectTitle = 'export' }) {
  const sites = flatSites(toRaw(groupedSites))
  const rows = buildRows(sites, columns)
  const headers = displayHeaders(rows)

  const worker = createExportWorker()
  try {
    const result = await runWorkerJob(worker, {
      type: 'excel',
      payload: {
        headers,
        rows,
        statusColors: STATUS_COLORS,
        sheetName: 'Sites',
      },
    })
    const { saveAs } = await import('file-saver')
    const blob = new Blob([result.buffer], {
      type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    })
    saveAs(blob, `${projectTitle}_sites.xlsx`)
  } finally {
    worker.terminate()
  }
}

// ─── Word ──────────────────────────────────────────────────────────────────────

export async function exportWord({ groupedSites, columns, projectTitle = 'export' }) {
  await yieldToMain()

  const {
    Document, Packer, Table, TableRow, TableCell, Paragraph, TextRun,
    HeadingLevel, AlignmentType, WidthType, ShadingType, BorderStyle,
    PageOrientation,
  } = await import('docx')
  const { saveAs } = await import('file-saver')

  await yieldToMain()

  const sites = flatSites(toRaw(groupedSites))
  const rows = buildRows(sites, columns)
  const headers = displayHeaders(rows)

  const HEADER_COLOR = '1E3A5F'
  const ALT_COLOR = 'F0F4FA'

  const cellBorder = {
    top: { style: BorderStyle.SINGLE, size: 6, color: 'AAAAAA' },
    bottom: { style: BorderStyle.SINGLE, size: 6, color: 'AAAAAA' },
    left: { style: BorderStyle.SINGLE, size: 6, color: 'AAAAAA' },
    right: { style: BorderStyle.SINGLE, size: 6, color: 'AAAAAA' },
  }

  const headerRow = new TableRow({
    tableHeader: true,
    children: headers.map(
      (h) =>
        new TableCell({
          borders: cellBorder,
          shading: { type: ShadingType.SOLID, color: HEADER_COLOR },
          children: [
            new Paragraph({
              alignment: AlignmentType.CENTER,
              children: [new TextRun({ text: h, bold: true, color: 'FFFFFF', size: 18 })],
            }),
          ],
          width: { size: Math.floor(9000 / headers.length), type: WidthType.DXA },
        }),
    ),
  })

  const dataRows = rows.map((row, idx) => {
    return new TableRow({
      children: headers.map((h) => {
        const isStatus = h === 'Statut'
        const fill = isStatus
          ? statusFill(row.__statusKey)
          : idx % 2 === 1
            ? ALT_COLOR
            : 'FFFFFF'
        const textColor = isStatus ? 'FFFFFF' : '000000'
        return new TableCell({
          borders: cellBorder,
          shading: { type: ShadingType.SOLID, color: fill },
          children: [
            new Paragraph({
              children: [
                new TextRun({
                  text: String(row[h] ?? ''),
                  size: 16,
                  color: textColor,
                  bold: isStatus,
                }),
              ],
            }),
          ],
          width: { size: Math.floor(9000 / headers.length), type: WidthType.DXA },
        })
      }),
    })
  })

  const table = new Table({ rows: [headerRow, ...dataRows], width: { size: 100, type: WidthType.PERCENTAGE } })

  const doc = new Document({
    sections: [
      {
        properties: {
          page: {
            size: { orientation: PageOrientation.LANDSCAPE },
            margin: { top: 720, bottom: 720, left: 720, right: 720 },
          },
        },
        children: [
          new Paragraph({
            heading: HeadingLevel.HEADING_1,
            children: [new TextRun({ text: `${projectTitle} — Sites`, bold: true, size: 28 })],
          }),
          new Paragraph({ text: '' }),
          table,
        ],
      },
    ],
  })

  const blob = await Packer.toBlob(doc)
  saveAs(blob, `${projectTitle}_sites.docx`)
}

// ─── Clipboard image (Web Worker + OffscreenCanvas) ───────────────────────────

export async function exportClipboardImage({
  groupedSites,
  columns,
  themeRoot = null,
  pixelRatio = 2,
}) {
  const rawGroups = toRaw(groupedSites) ?? []
  const headers = headersFromColumns(columns)
  const groups = rawGroups
    .map((g) => {
      const sites = g.sites ?? []
      if (!sites.length) return null
      return {
        lotLabel: g.lotLabel ?? null,
        rows: buildImageRows(sites, columns),
      }
    })
    .filter(Boolean)

  const theme = readExportTheme(themeRoot)
  const worker = createExportWorker()

  try {
    const result = await runWorkerJob(worker, {
      type: 'image',
      payload: {
        groups,
        headers,
        theme,
        pixelRatio,
      },
    })

    const blob = result.blob
    if (!blob) return false

    if (!navigator.clipboard?.write || typeof ClipboardItem === 'undefined') {
      console.error('Clipboard image API unavailable')
      return false
    }

    await navigator.clipboard.write([
      new ClipboardItem({ 'image/png': Promise.resolve(blob) }),
    ])
    return true
  } catch (e) {
    console.error('Clipboard export failed', e)
    return false
  } finally {
    worker.terminate()
  }
}
