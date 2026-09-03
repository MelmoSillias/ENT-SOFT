import { toRaw } from 'vue'

/**
 * Export composable for the ProjectSitesTable.
 * Supports Excel (xlsx/SheetJS), Word (docx), and clipboard image (html-to-image).
 * All exports use landscape orientation.
 */

// ─── helpers ──────────────────────────────────────────────────────────────────

function buildRows(sites, columns) {
  return sites.map((site, idx) => {
    const row = { '#': idx + 1 }
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

function flatSites(groupedSites) {
  return groupedSites.flatMap((g) => g.sites)
}

// ─── Excel ─────────────────────────────────────────────────────────────────────

export async function exportExcel({ groupedSites, columns, projectTitle = 'export' }) {
  const { utils, writeFile } = await import('xlsx')

  const sites = flatSites(toRaw(groupedSites))
  const rows = buildRows(sites, columns)
  const headers = Object.keys(rows[0] ?? { '#': 1 })

  const ws = utils.json_to_sheet(rows, { header: headers })

  // Column widths
  ws['!cols'] = headers.map((h) => ({ wch: Math.max(h.length + 2, 12) }))

  // Landscape page setup
  ws['!pageSetup'] = { orientation: 'landscape', fitToPage: true, fitToWidth: 1, fitToHeight: 0 }

  // Style header row (bold + fill)
  const range = utils.decode_range(ws['!ref'])
  for (let C = range.s.c; C <= range.e.c; C++) {
    const addr = utils.encode_cell({ r: 0, c: C })
    if (!ws[addr]) continue
    ws[addr].s = {
      font: { bold: true, color: { rgb: 'FFFFFF' } },
      fill: { fgColor: { rgb: '1E3A5F' } },
      alignment: { horizontal: 'center', vertical: 'center', wrapText: true },
      border: {
        top: { style: 'thin', color: { rgb: 'AAAAAA' } },
        bottom: { style: 'thin', color: { rgb: 'AAAAAA' } },
        left: { style: 'thin', color: { rgb: 'AAAAAA' } },
        right: { style: 'thin', color: { rgb: 'AAAAAA' } },
      },
    }
  }

  // Data rows alternating fill + borders
  for (let R = 1; R <= range.e.r; R++) {
    for (let C = range.s.c; C <= range.e.c; C++) {
      const addr = utils.encode_cell({ r: R, c: C })
      if (!ws[addr]) ws[addr] = { t: 's', v: '' }
      ws[addr].s = {
        fill: { fgColor: { rgb: R % 2 === 0 ? 'F0F4FA' : 'FFFFFF' } },
        border: {
          top: { style: 'thin', color: { rgb: 'DDDDDD' } },
          bottom: { style: 'thin', color: { rgb: 'DDDDDD' } },
          left: { style: 'thin', color: { rgb: 'DDDDDD' } },
          right: { style: 'thin', color: { rgb: 'DDDDDD' } },
        },
        alignment: { wrapText: true },
      }
    }
  }

  const wb = utils.book_new()
  utils.book_append_sheet(wb, ws, 'Sites')
  writeFile(wb, `${projectTitle}_sites.xlsx`, { bookType: 'xlsx', cellStyles: true })
}

// ─── Word ──────────────────────────────────────────────────────────────────────

export async function exportWord({ groupedSites, columns, projectTitle = 'export' }) {
  const {
    Document, Packer, Table, TableRow, TableCell, Paragraph, TextRun,
    HeadingLevel, AlignmentType, WidthType, ShadingType, BorderStyle,
    PageOrientation,
  } = await import('docx')
  const { saveAs } = await import('file-saver')

  const sites = flatSites(toRaw(groupedSites))
  const rows = buildRows(sites, columns)
  const headers = Object.keys(rows[0] ?? {})

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

  const dataRows = rows.map(
    (row, idx) =>
      new TableRow({
        children: headers.map(
          (h) =>
            new TableCell({
              borders: cellBorder,
              shading:
                idx % 2 === 1
                  ? { type: ShadingType.SOLID, color: ALT_COLOR }
                  : { type: ShadingType.CLEAR, color: 'FFFFFF' },
              children: [
                new Paragraph({
                  children: [new TextRun({ text: String(row[h] ?? ''), size: 16 })],
                }),
              ],
              width: { size: Math.floor(9000 / headers.length), type: WidthType.DXA },
            }),
        ),
      }),
  )

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
