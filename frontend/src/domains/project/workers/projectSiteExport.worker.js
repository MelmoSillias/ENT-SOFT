/* eslint-disable no-restricted-globals */
import ExcelJS from 'exceljs'

const HEADER_FILL = '1E3A5F'
const ALT_FILL = 'F0F4FA'

self.onmessage = async (event) => {
  try {
    const { type, payload } = event.data ?? {}
    if (type !== 'excel') {
      self.postMessage({ ok: false, error: 'Type d’export inconnu' })
      return
    }

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

    const buffer = await workbook.xlsx.writeBuffer()
    const ab = buffer instanceof ArrayBuffer ? buffer : buffer.buffer
    self.postMessage({ ok: true, buffer: ab }, [ab])
  } catch (err) {
    self.postMessage({
      ok: false,
      error: err?.message || String(err),
    })
  }
}
