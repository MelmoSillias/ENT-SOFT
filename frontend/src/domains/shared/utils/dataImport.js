// Keep creation permissions aligned with the API import formats.
const definitions = {
  clients: { label: 'clients', permission: 'client.clients.create' },
  sites: { label: 'sites', permission: 'site.sites.create' },
  prestataires: { label: 'prestataires', permission: 'employee.prestataires.create' },
  prestations: { label: 'prestations', permission: 'employee.prestataires.create' },
  depenses: { label: 'dépenses', permission: 'finance.transactions.create' },
  revenus: { label: 'revenus', permission: 'finance.transactions.create' },
  equipements: { label: 'équipements', permission: 'stock.equipment.create' },
  factures: { label: 'factures', permission: 'finance.invoices.create' },
  bons_livraison: { label: 'bons de livraison', permission: 'project.projects.update', requiresProject: true },
}

const tableImportTypes = {
  clients: ['clients'],
  sites: ['sites'],
  prestataires: ['prestataires'],
  prestations: ['prestations'],
  expenses: ['depenses'],
  transactions: ['depenses', 'revenus'],
  equipment: ['equipements'],
  invoices: ['factures'],
}

export function getImportDefinition(type) {
  return Object.hasOwn(definitions, type) ? definitions[type] : null
}

export function getTableImportTypes(tableType) {
  return Object.hasOwn(tableImportTypes, tableType) ? tableImportTypes[tableType] : []
}
