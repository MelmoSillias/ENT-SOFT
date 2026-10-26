const WEEKDAY = new Intl.DateTimeFormat('fr-FR', { weekday: 'long' })
const DAY = new Intl.DateTimeFormat('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric' })

export function planningHeading(date) {
  const value = date instanceof Date ? date : new Date(`${date}T12:00:00`)
  if (Number.isNaN(value.getTime())) return 'Planning'
  return `Planning du ${WEEKDAY.format(value)} ${DAY.format(value)}`
}

function orderedMembers(group) {
  const members = group.members || []
  const leaderId = group.leaderEmployeeId
  if (!leaderId) return members
  const leader = members.find((member) => member.id === leaderId)
  if (!leader) return members
  return [leader, ...members.filter((member) => member.id !== leaderId)]
}

function memberLine(member, isLeader) {
  const mention = String(member.mention || '').trim()
  const name = String(member.name || 'Employé').trim()
  const base = mention ? `${name} (${mention})` : name
  return isLeader ? `${base} — Chef` : base
}

function siteLines(site) {
  const lines = []
  const title = [site.code, site.title].map((part) => String(part || '').trim()).filter(Boolean).join(' ')
  if (title) lines.push(title)
  const task = String(site.description || '').trim()
  if (task) lines.push(`Tâche : ${task}`)
  return lines
}

export function formatGroupPlanningText(date, groups) {
  const lines = [planningHeading(date), '']
  ;(groups || []).forEach((group, index) => {
    if (index > 0) lines.push('')
    const label = String(group.label || '').trim()
    lines.push(label ? `Groupe ${index + 1} — ${label}` : `Groupe ${index + 1}`)
    for (const site of group.sites || []) lines.push(...siteLines(site))
    for (const member of orderedMembers(group)) {
      lines.push(memberLine(member, member.id === group.leaderEmployeeId))
    }
  })
  return `${lines.join('\n').trim()}\n`
}

export async function copyText(text) {
  if (navigator.clipboard?.writeText) {
    await navigator.clipboard.writeText(text)
    return
  }
  const area = document.createElement('textarea')
  area.value = text
  area.setAttribute('readonly', '')
  area.style.position = 'fixed'
  area.style.left = '-9999px'
  document.body.appendChild(area)
  area.select()
  document.execCommand('copy')
  area.remove()
}

export async function copyImage(node, filename) {
  const { toBlob } = await import('html-to-image')
  const blob = await toBlob(node, {
    pixelRatio: 2,
    backgroundColor: '#ffffff',
    cacheBust: true,
  })
  if (!blob) throw new Error('Image vide')

  const canWrite = typeof ClipboardItem !== 'undefined' && navigator.clipboard?.write
  if (canWrite) {
    try {
      await navigator.clipboard.write([
        new ClipboardItem({ 'image/png': Promise.resolve(blob) }),
      ])
      return 'clipboard'
    } catch {
      // Le presse-papiers image est refusé sur une partie des navigateurs mobiles.
    }
  }

  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = filename
  link.click()
  URL.revokeObjectURL(url)
  return 'download'
}

export { orderedMembers, memberLine }
