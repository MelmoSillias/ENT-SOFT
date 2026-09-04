/* eslint-disable no-restricted-globals */
import ExcelJS from 'exceljs'

const HEADER_FILL = '1E3A5F'
const ALT_FILL = 'F0F4FA'

self.onmessage = async (event) => {
  try {
    const { type, payload } = event.data ?? {}
    if (type === 'excel') {
      const buffer = await buildExcel(payload)
      const ab = buffer instanceof ArrayBuffer ? buffer : buffer.buffer
      self.postMessage({ ok: true, buffer: ab }, [ab])
      return
    }
    if (type === 'image') {
      const blob = await buildImagePng(payload)
      self.postMessage({ ok: true, blob })
      return
    }
    self.postMessage({ ok: false, error: 'Type d’export inconnu' })
  } catch (err) {
    self.postMessage({
      ok: false,
      error: err?.message || String(err),
    })
  }
}

async function buildExcel(payload) {
  const { headers, rows, statusColors = {}, sheetName = 'Sites' } = payload
  const workbook = new ExcelJS.Workbook()
  const sheet = workbook.addWorksheet(sheetName, {
    views: [{ state: 'frozen', ySplit: 1 }],
    pageSetup: { orientation: 'landscape', fitToPage: true, fitToWidth: 1, fitToHeight: 0 },
  })

  sheet.columns = headers.map((h) => ({
    header: h,
    key: h,
    width: Math.max(String(h).length + 2, 12),
  }))

  const headerRow = sheet.getRow(1)
  headerRow.eachCell((cell) => {
    cell.font = { bold: true, color: { argb: `FF${'FFFFFF'}` } }
    cell.fill = {
      type: 'pattern',
      pattern: 'solid',
      fgColor: { argb: `FF${HEADER_FILL}` },
    }
    cell.alignment = { horizontal: 'center', vertical: 'middle', wrapText: true }
    cell.border = {
      top: { style: 'thin', color: { argb: 'FFAAAAAA' } },
      bottom: { style: 'thin', color: { argb: 'FFAAAAAA' } },
      left: { style: 'thin', color: { argb: 'FFAAAAAA' } },
      right: { style: 'thin', color: { argb: 'FFAAAAAA' } },
    }
  })

  for (let i = 0; i < rows.length; i++) {
    const rowData = rows[i]
    const values = headers.map((h) => rowData[h] ?? '')
    const excelRow = sheet.addRow(values)
    const statusKey = rowData.__statusKey ?? 'pending'
    const statusColor = statusColors[statusKey] ?? statusColors.pending ?? '64748B'
    const alt = i % 2 === 1

    excelRow.eachCell({ includeEmpty: true }, (cell, colNumber) => {
      const header = headers[colNumber - 1]
      const isStatus = header === 'Statut'
      if (isStatus) {
        cell.fill = {
          type: 'pattern',
          pattern: 'solid',
          fgColor: { argb: `FF${statusColor}` },
        }
        cell.font = { color: { argb: 'FFFFFFFF' }, bold: true }
      } else {
        cell.fill = {
          type: 'pattern',
          pattern: 'solid',
          fgColor: { argb: `FF${alt ? ALT_FILL : 'FFFFFF'}` },
        }
      }
      cell.alignment = { wrapText: true, vertical: 'middle' }
      cell.border = {
        top: { style: 'thin', color: { argb: 'FFDDDDDD' } },
        bottom: { style: 'thin', color: { argb: 'FFDDDDDD' } },
        left: { style: 'thin', color: { argb: 'FFDDDDDD' } },
        right: { style: 'thin', color: { argb: 'FFDDDDDD' } },
      }
    })
  }

  return workbook.xlsx.writeBuffer()
}

// ─── PNG table (OffscreenCanvas) — visual parity with ProjectSitesTable ───────

const COL_MIN = {
  '#': 36,
  'Code site': 90,
  'Nom du site': 140,
  Statut: 100,
  Commentaires: 160,
  Technicien: 110,
}
const COL_MAX = {
  '#': 48,
  Commentaires: 320,
}

const loadedFontFamilies = new Set()

async function loadPrimaryFont(fontFamily) {
  const primary = String(fontFamily || '')
    .split(',')[0]
    .trim()
    .replace(/^['"]|['"]$/g, '')
  if (!primary || loadedFontFamilies.has(primary)) return
  if (/^(system-ui|sans-serif|serif|monospace|Segoe UI|Arial|Helvetica)/i.test(primary)) {
    loadedFontFamilies.add(primary)
    return
  }
  if (typeof FontFace === 'undefined' || !self.fonts) return

  try {
    const cssUrl =
      'https://fonts.googleapis.com/css2?family=' +
      encodeURIComponent(primary).replace(/%20/g, '+') +
      ':wght@400;600;700&display=swap'
    const cssText = await fetch(cssUrl).then((r) => {
      if (!r.ok) throw new Error('Font CSS fetch failed')
      return r.text()
    })
    const faces = []
    for (const block of cssText.split('@font-face')) {
      const src = block.match(/src:\s*url\(([^)]+)\)/)?.[1]?.replace(/['"]/g, '')
      const weight = block.match(/font-weight:\s*(\d+)/)?.[1] || '400'
      const style = block.match(/font-style:\s*(\w+)/)?.[1] || 'normal'
      if (!src) continue
      faces.push(
        new FontFace(primary, `url(${src})`, { weight, style }).load().then((face) => {
          self.fonts.add(face)
        }),
      )
    }
    await Promise.all(faces)
    loadedFontFamilies.add(primary)
  } catch {
    // Fallback stack (Segoe UI, etc.) still applies via fontFamily
  }
}

async function buildImagePng(payload) {
  if (typeof OffscreenCanvas === 'undefined') {
    throw new Error('OffscreenCanvas non supporté dans ce navigateur')
  }

  const {
    groups = [],
    headers = [],
    theme = {},
    pixelRatio = 2,
  } = payload

  const t = {
    background: theme.background || '#ffffff',
    headerBg: theme.headerBg || '#f1f5f9',
    rowBg: theme.rowBg || '#ffffff',
    border: theme.border || '#d8e0ec',
    text: theme.text || '#1a2744',
    textMuted: theme.textMuted || '#5c6b82',
    fontFamily: theme.fontFamily || 'system-ui, -apple-system, Segoe UI, sans-serif',
    tagStyles: theme.tagStyles || {},
    statusSeverity: theme.statusSeverity || {
      pending: 'secondary',
      in_progress: 'warn',
      completed: 'success',
      blocked: 'danger',
    },
  }

  await loadPrimaryFont(t.fontFamily)

  const scale = Math.max(1, Math.min(3, Number(pixelRatio) || 2))
  const padX = 16
  const padY = 16
  const groupGap = 24
  const lotTitleH = 22
  const lotGap = 8
  const headerPadY = 7
  const headerPadX = 10
  const cellPadY = 6
  const cellPadX = 10
  const headerFontSize = 12.5
  const bodyFontSize = 12.6
  const lotFontSize = 12.8
  const numFontSize = 11.5
  const tagFontSize = 11.5
  const lineHeight = 1.35
  const borderW = 1
  const radius = 8

  const measureCanvas = new OffscreenCanvas(1, 1)
  const mctx = measureCanvas.getContext('2d')
  if (!mctx) throw new Error('Impossible d’obtenir le contexte canvas')

  function setFont(size, weight = '400') {
    mctx.font = `${weight} ${size}px ${t.fontFamily}`
  }

  function measure(text, size, weight = '400') {
    setFont(size, weight)
    return mctx.measureText(String(text ?? '')).width
  }

  function wrapLines(text, maxWidth, size, weight = '400') {
    const raw = String(text ?? '')
    if (!raw) return ['']
    setFont(size, weight)
    const paragraphs = raw.split(/\n/)
    const lines = []
    for (const para of paragraphs) {
      const words = para.length ? para.split(/\s+/) : ['']
      let current = ''
      for (const word of words) {
        const next = current ? `${current} ${word}` : word
        if (mctx.measureText(next).width <= maxWidth || !current) {
          current = next
        } else {
          lines.push(current)
          current = word
        }
      }
      lines.push(current || '')
    }
    return lines.length ? lines : ['']
  }

  const colWidths = headers.map((h) => {
    const min = COL_MIN[h] ?? 96
    const max = COL_MAX[h] ?? 240
    let w = measure(String(h).toUpperCase(), headerFontSize, '700') + headerPadX * 2
    for (const g of groups) {
      for (const row of g.rows ?? []) {
        const val = row[h]
        if (h === 'Statut') {
          w = Math.max(w, measure(String(val ?? ''), tagFontSize, '600') + 28)
        } else if (h === '#') {
          w = Math.max(w, measure(String(val ?? ''), numFontSize, '600') + cellPadX * 2)
        } else if (h === 'Commentaires') {
          // width capped; height will wrap
          w = Math.max(w, Math.min(max, measure(String(val ?? '').slice(0, 40), bodyFontSize) + cellPadX * 2))
        } else {
          w = Math.max(w, measure(String(val ?? ''), bodyFontSize) + cellPadX * 2)
        }
      }
    }
    return Math.round(Math.min(max, Math.max(min, w)))
  })

  const tableInnerW = colWidths.reduce((a, b) => a + b, 0)
  const tableW = tableInnerW + borderW * 2

  const layoutGroups = groups.map((g) => {
    const headerH = headerPadY * 2 + headerFontSize * lineHeight
    const rowLayouts = (g.rows ?? []).map((row) => {
      let maxLines = 1
      const cellLines = headers.map((h, i) => {
        if (h === 'Statut' || h === '#') {
          return [String(row[h] ?? '')]
        }
        const textW = colWidths[i] - cellPadX * 2
        const lines = wrapLines(row[h], Math.max(20, textW), bodyFontSize)
        maxLines = Math.max(maxLines, lines.length)
        return lines
      })
      const h = cellPadY * 2 + maxLines * bodyFontSize * lineHeight
      return { row, cellLines, height: Math.max(28, h) }
    })
    const tableH = headerH + rowLayouts.reduce((a, r) => a + r.height, 0) + borderW * 2
    const lotH = g.lotLabel ? lotTitleH + lotGap : 0
    return { lotLabel: g.lotLabel || null, headerH, rowLayouts, tableH, lotH, height: lotH + tableH }
  })

  const contentW = tableW
  const contentH =
    layoutGroups.reduce((a, g) => a + g.height, 0) +
    Math.max(0, layoutGroups.length - 1) * groupGap

  let cssW = padX * 2 + contentW
  let cssH = padY * 2 + contentH
  if (!layoutGroups.length) {
    cssW = padX * 2 + 320
    cssH = padY * 2 + 80
  }

  // Respect browser canvas limits
  const MAX_DIM = 8192
  let drawScale = scale
  if (cssW * drawScale > MAX_DIM || cssH * drawScale > MAX_DIM) {
    drawScale = Math.max(1, Math.min(MAX_DIM / cssW, MAX_DIM / cssH))
  }

  const canvas = new OffscreenCanvas(Math.ceil(cssW * drawScale), Math.ceil(cssH * drawScale))
  const ctx = canvas.getContext('2d')
  if (!ctx) throw new Error('Impossible d’obtenir le contexte canvas')
  ctx.scale(drawScale, drawScale)
  ctx.textBaseline = 'middle'

  // Background
  ctx.fillStyle = t.background
  ctx.fillRect(0, 0, cssW, cssH)

  function roundRect(x, y, w, h, r) {
    const rr = Math.min(r, w / 2, h / 2)
    ctx.beginPath()
    ctx.moveTo(x + rr, y)
    ctx.arcTo(x + w, y, x + w, y + h, rr)
    ctx.arcTo(x + w, y + h, x, y + h, rr)
    ctx.arcTo(x, y + h, x, y, rr)
    ctx.arcTo(x, y, x + w, y, rr)
    ctx.closePath()
  }

  function drawTag(x, y, cellW, cellH, label, statusKey) {
    const severity = t.statusSeverity[statusKey] ?? 'secondary'
    const style = t.tagStyles[severity] || { bg: '#e2e8f0', color: '#64748b' }
    setFont(tagFontSize, '600')
    const tw = mctx.measureText(label).width
    const th = tagFontSize * 1.55
    const px = 10
    const bw = Math.min(cellW - 8, tw + px * 2)
    const bh = Math.min(cellH - 6, th)
    const bx = x + (cellW - bw) / 2
    const by = y + (cellH - bh) / 2
    ctx.fillStyle = style.bg
    roundRect(bx, by, bw, bh, bh / 2)
    ctx.fill()
    ctx.fillStyle = style.color
    ctx.font = `600 ${tagFontSize}px ${t.fontFamily}`
    ctx.textAlign = 'center'
    ctx.fillText(label, bx + bw / 2, by + bh / 2 + 0.5, bw - 6)
    ctx.textAlign = 'left'
  }

  if (!layoutGroups.length) {
    ctx.fillStyle = t.textMuted
    ctx.font = `400 ${bodyFontSize}px ${t.fontFamily}`
    ctx.textAlign = 'center'
    ctx.fillText('Aucun site à exporter', cssW / 2, cssH / 2)
    ctx.textAlign = 'left'
  }

  let cursorY = padY
  for (let gi = 0; gi < layoutGroups.length; gi++) {
    const g = layoutGroups[gi]
    const tableX = padX

    if (g.lotLabel) {
      ctx.fillStyle = t.textMuted
      ctx.font = `700 ${lotFontSize}px ${t.fontFamily}`
      ctx.fillText(String(g.lotLabel).toUpperCase(), tableX, cursorY + lotTitleH / 2)
      cursorY += g.lotH
    }

    const tableY = cursorY
    // Table chrome
    ctx.fillStyle = t.rowBg
    roundRect(tableX, tableY, tableW, g.tableH, radius)
    ctx.fill()
    ctx.strokeStyle = t.border
    ctx.lineWidth = borderW
    roundRect(tableX, tableY, tableW, g.tableH, radius)
    ctx.stroke()

    // Clip content to rounded table
    ctx.save()
    roundRect(tableX, tableY, tableW, g.tableH, radius)
    ctx.clip()

    // Header
    let x = tableX + borderW
    const headerY = tableY + borderW
    ctx.fillStyle = t.headerBg
    ctx.fillRect(tableX + borderW, headerY, tableInnerW, g.headerH)

    for (let i = 0; i < headers.length; i++) {
      const h = headers[i]
      const w = colWidths[i]
      ctx.strokeStyle = t.border
      ctx.lineWidth = borderW
      ctx.strokeRect(x, headerY, w, g.headerH)
      ctx.fillStyle = t.text
      ctx.font = `700 ${headerFontSize}px ${t.fontFamily}`
      ctx.textAlign = h === '#' ? 'center' : 'left'
      const label = String(h).toUpperCase()
      const tx = h === '#' ? x + w / 2 : x + headerPadX
      ctx.fillText(label, tx, headerY + g.headerH / 2, w - headerPadX * 2)
      ctx.textAlign = 'left'
      x += w
    }

    // Body rows
    let y = headerY + g.headerH
    for (const rl of g.rowLayouts) {
      x = tableX + borderW
      ctx.fillStyle = t.rowBg
      ctx.fillRect(tableX + borderW, y, tableInnerW, rl.height)

      for (let i = 0; i < headers.length; i++) {
        const h = headers[i]
        const w = colWidths[i]
        ctx.strokeStyle = t.border
        ctx.lineWidth = borderW
        ctx.strokeRect(x, y, w, rl.height)

        if (h === 'Statut') {
          drawTag(x, y, w, rl.height, String(rl.row[h] ?? ''), rl.row.__statusKey ?? 'pending')
        } else if (h === '#') {
          ctx.fillStyle = t.textMuted
          ctx.font = `600 ${numFontSize}px ${t.fontFamily}`
          ctx.textAlign = 'center'
          ctx.fillText(String(rl.row[h] ?? ''), x + w / 2, y + rl.height / 2, w - 4)
          ctx.textAlign = 'left'
        } else {
          const lines = rl.cellLines[i]
          const blockH = lines.length * bodyFontSize * lineHeight
          let ty = y + (rl.height - blockH) / 2 + (bodyFontSize * lineHeight) / 2
          ctx.fillStyle = t.text
          ctx.font = `400 ${bodyFontSize}px ${t.fontFamily}`
          for (const line of lines) {
            ctx.fillText(line, x + cellPadX, ty, w - cellPadX * 2)
            ty += bodyFontSize * lineHeight
          }
        }
        x += w
      }
      y += rl.height
    }

    ctx.restore()
    cursorY = tableY + g.tableH + (gi < layoutGroups.length - 1 ? groupGap : 0)
  }

  return canvas.convertToBlob({ type: 'image/png' })
}
