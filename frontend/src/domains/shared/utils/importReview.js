/**
 * Review flattened API report rows without modifying them.
 * Returns one { field, value, rows: [{ fileName, line }], message } per duplicate
 * value, with idImport conflicts first, then natural-key conflicts.
 * Missing/empty keys are left to API validation. Natural keys use ready rows only;
 * emails are lowercased, while site codes remain case-sensitive.
 */
export function getImportConflicts(type, rows) {
  const conflicts = []

  function collect(field, candidates, getValue) {
    const groups = new Map()
    for (const row of candidates) {
      const value = getValue(row)
      if (value === undefined || value === null || value === '') continue
      if (!groups.has(value)) groups.set(value, [])
      groups.get(value).push({ fileName: row.fileName, line: row.line })
    }
    for (const [value, locations] of groups) {
      if (locations.length < 2) continue
      const details = locations.map(({ fileName, line }) => `${fileName}, ligne ${line}`).join(' ; ')
      conflicts.push({
        field,
        value,
        rows: locations,
        message: `${field} « ${value} » répété : ${details}.`,
      })
    }
  }

  collect('idImport', rows, (row) => row.idImport)
  if (type === 'sites') {
    collect('code', rows.filter((row) => row.status === 'ready'), (row) => row.data?.code)
  } else if (type === 'prestataires') {
    collect('email', rows.filter((row) => row.status === 'ready'), (row) => row.data?.email?.toLowerCase())
  }

  return conflicts
}
