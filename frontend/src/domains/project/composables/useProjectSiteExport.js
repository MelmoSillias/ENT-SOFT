/**
 * Shared export helpers for ProjectSitesTable.
 * Excel generation runs in a Web Worker (ExcelJS) so the UI stays responsive.
 * Word uses docx on the main thread after yielding to the event loop.
 */

import { toRaw } from 'vue'

export const STATUS_COLORS = {
  pending: '64748B',
  in_progress: 'D97706',
  completed: '16A34A',
  blocked: 'DC2626',
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
      else if (col.key === '__status') row['Statut'] = site.statusLabel ?? site.status ?? ''
      else if (col.key === '__comment') row['Commentaires'] = site.comment ?? ''
      else if (col.key === '__technician') row['Technicien'] = site.technicianName ?? ''
      else if (col.key) {
        const raw = site.informationsValues?.[col.key]
        row[col.label ?? col.key] =
          raw === null || raw === undefined || raw === '' ? '' : String(raw)
      }
    }
    return row
  })
}

export function flatSites(groupedSites) {
  return groupedSites.flatMap((g) => g.sites)
}

function displayHeaders(rows) {
  const keys = Object.keys(rows[0] ?? { '#': 1 })
  return keys.filter((k) => k !== '__statusKey')
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

// ─── Excel (Web Worker + ExcelJS) ─────────────────────────────────────────────

export async function exportExcel({ groupedSites, columns, projectTitle = 'export' }) {
  const sites = flatSites(toRaw(groupedSites))
  const rows = buildRows(sites, columns)
  const headers = displayHeaders(rows)

  const worker = new Worker(new URL('../workers/projectSiteExport.worker.js', import.meta.url), {
    type: 'module',
  })

  try {
    const buffer = await new Promise((resolve, reject) => {
      const onMessage = (event) => {
        worker.removeEventListener('message', onMessage)
        worker.removeEventListener('error', onError)
        if (event.data?.ok) resolve(event.data.buffer)
        else reject(new Error(event.data?.error || 'Export Excel échoué'))
      }
      const onError = (err) => {
        worker.removeEventListener('message', onMessage)
        worker.removeEventListener('error', onError)
        reject(err instanceof Error ? err : new Error('Worker export error'))
      }
      worker.addEventListener('message', onMessage)
      worker.addEventListener('error', onError)
      worker.postMessage({
        type: 'excel',
        payload: {
          headers,
          rows,
          statusColors: STATUS_COLORS,
          sheetName: 'Sites',
        },
      })
    })

    const { saveAs } = await import('file-saver')
    const blob = new Blob([buffer], {
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

// ─── Clipboard image ────────────────────────────────────────────────────────────

export async function exportClipboardImage(tableEl) {
  const { toPng } = await import('html-to-image')
  try {
    const dataUrl = await toPng(tableEl, { quality: 1, pixelRatio: 2, backgroundColor: '#ffffff' })
    const res = await fetch(dataUrl)
    const blob = await res.blob()
    await navigator.clipboard.write([new ClipboardItem({ 'image/png': blob })])
    return true
  } catch (e) {
    console.error('Clipboard export failed', e)
    return false
  }
}
