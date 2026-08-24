import { useEffect, useMemo, useState } from 'react'
import { AlertTriangle, ChevronDown, ChevronUp, Download, GripVertical, LockKeyhole, Play, Printer, ShieldAlert, ShieldCheck } from 'lucide-react'
import { api } from './api'

const nameOf = participant => participant?.team_name || participant?.full_name || 'BYE'
const jakartaDate = value => value ? new Intl.DateTimeFormat('en-CA', {timeZone: 'Asia/Jakarta', year: 'numeric', month: '2-digit', day: '2-digit'}).format(new Date(value)) : ''
const jakartaTime = value => value ? new Intl.DateTimeFormat('en-GB', {timeZone: 'Asia/Jakarta', hour: '2-digit', minute: '2-digit', hour12: false}).format(new Date(value)) : ''
const localDateTime = value => value ? `${jakartaDate(value)}T${jakartaTime(value)}` : ''
const dateTime = value => value ? new Intl.DateTimeFormat('id-ID', {dateStyle: 'medium', timeStyle: 'short', timeZone: 'Asia/Jakarta'}).format(new Date(value))+' WIB' : 'Belum dijadwalkan'
const formatLabels = {
  single_elimination: 'Single Elimination',
  double_elimination: 'Double Elimination',
  round_robin: 'Round Robin · Setengah Kompetisi',
  round_robin_full: 'Round Robin · Kompetisi Penuh',
  groups_knockout: 'Grup → Knockout',
}

const initialRules = {avoid_same_school: true, separate_seeds: true, host_policy: 'random', group_count: 2, third_place: true}
const eliminationFormats = ['single_elimination', 'double_elimination']
const bracketSizeFor = count => {
  let size = 2
  while (size < count) size *= 2
  return Math.min(size, 64)
}

function ManualBracketPlacement({slots, participants, participantsById, updateSlot}) {
  return <section className="mt-7 rounded-2xl border border-blue-200 bg-blue-50/60 p-4 sm:p-5">
    <div className="flex flex-wrap items-start justify-between gap-3"><div><h3 className="font-display font-bold text-blue-950">Penempatan bracket manual</h3><p className="mt-1 text-xs leading-5 text-blue-800">Tentukan dua tim pada setiap pertandingan ronde pertama. Pilih BYE untuk mengosongkan slot.</p></div><span className="rounded-full bg-blue-600 px-3 py-1 text-xs font-bold text-white">{slots.length} slot</span></div>
    <div className="mt-4 grid gap-4 lg:grid-cols-2">{Array.from({length: slots.length / 2}, (_, matchIndex) => <article key={matchIndex} className="rounded-2xl border border-blue-200 bg-white p-4"><div className="text-xs font-black uppercase tracking-wider text-blue-700">Match ronde 1 · {matchIndex + 1}</div><div className="mt-3 grid gap-3">{[matchIndex * 2, matchIndex * 2 + 1].map(slotIndex => {const participant = participantsById[slots[slotIndex]]; return <label key={slotIndex} className="block"><span className="label">Slot {slotIndex + 1}</span><select className="input" value={slots[slotIndex] ?? ''} onChange={event => updateSlot(slotIndex, event.target.value ? Number(event.target.value) : null)}><option value="">BYE / Kosong</option>{participants.map(item => <option key={item.id} value={item.id}>{nameOf(item)} · {item.school_name || 'Sekolah belum diisi'}</option>)}</select>{participant && <span className="mt-1 block text-xs text-slate-500">{participant.ticket_code}{participant.force_majeure_issues ? ' · Force majeure' : ''}</span>}</label>})}</div></article>)}</div>
  </section>
}

function ManualGroupPlacement({groupCount, assignments, participants, moveToGroup}) {
  const groups = Array.from({length: groupCount}, (_, groupIndex) => participants.filter(participant => assignments[participant.id] === groupIndex))
  return <section className="mt-7 rounded-2xl border border-violet-200 bg-violet-50/60 p-4 sm:p-5">
    <div><h3 className="font-display font-bold text-violet-950">Penempatan grup manual</h3><p className="mt-1 text-xs leading-5 text-violet-800">Setiap tim wajib berada tepat di satu grup. Minimal dua tim diperlukan pada setiap grup.</p></div>
    <div className="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-3">{groups.map((members, groupIndex) => <article key={groupIndex} className={`rounded-2xl border bg-white p-4 ${members.length < 2 ? 'border-rose-300' : 'border-violet-200'}`}><div className="flex items-center justify-between gap-2"><h4 className="font-display font-bold">Grup {String.fromCharCode(65 + groupIndex)}</h4><span className={`rounded-full px-2 py-1 text-xs font-bold ${members.length < 2 ? 'bg-rose-100 text-rose-700' : 'bg-violet-100 text-violet-700'}`}>{members.length} tim</span></div><div className="mt-3 grid gap-2">{members.map(participant => <div key={participant.id} className="rounded-xl border p-3"><b className="block break-words text-sm">{nameOf(participant)}</b><span className="text-xs text-slate-500">{participant.school_name}</span><label className="mt-2 block"><span className="sr-only">Pindahkan {nameOf(participant)} ke grup</span><select className="input py-2 text-sm" value={groupIndex} onChange={event => moveToGroup(participant.id, Number(event.target.value))}>{groups.map((_, targetGroup) => <option key={targetGroup} value={targetGroup}>Pindahkan ke Grup {String.fromCharCode(65 + targetGroup)}</option>)}</select></label></div>)}</div>{!members.length && <div className="mt-3 rounded-xl bg-rose-50 p-3 text-xs font-bold text-rose-700">Belum ada tim di grup ini.</div>}</article>)}</div>
    {participants.some(participant => assignments[participant.id] === undefined) && <div className="mt-4 rounded-xl bg-rose-100 p-3 text-sm font-bold text-rose-700">Masih ada peserta yang belum ditempatkan.</div>}
  </section>
}

function MatchEditor({match, reload, locked}) {
  const [scoreA, setScoreA] = useState(match.score_a ?? '')
  const [scoreB, setScoreB] = useState(match.score_b ?? '')
  const [scheduled, setScheduled] = useState(localDateTime(match.scheduled_at))
  const [venue, setVenue] = useState(match.venue || '')
  const [busy, setBusy] = useState(false)

  const save = async status => {
    setBusy(true)
    try {
      await api(`/manage/tournaments/matches/${match.id}`, {method: 'PUT', body: JSON.stringify({
        score_a: scoreA === '' ? null : Number(scoreA),
        score_b: scoreB === '' ? null : Number(scoreB),
        scheduled_at: scheduled || null,
        venue,
        status,
      })})
      reload()
    } catch {
      // Kesalahan API sudah ditampilkan oleh pusat pesan aplikasi.
    } finally {
      setBusy(false)
    }
  }

  return <article className="rounded-2xl border bg-white p-4">
    <div className="flex flex-wrap items-center justify-between gap-2"><b className="text-xs text-blue-600">MATCH {String(match.match_number).padStart(2, '0')} · {match.round_label}</b><span className="rounded-full bg-slate-100 px-2 py-1 text-[11px] font-bold">{match.status}</span></div>
    <div className="mt-3 grid grid-cols-[minmax(0,1fr)_70px] items-center gap-2 text-sm">
      <span className={match.winner_id === match.participant_a_id ? 'font-black text-emerald-700' : 'font-bold'}>{nameOf(match.participant_a)}</span><input className="input py-2 text-center" type="number" min="0" value={scoreA} onChange={event => setScoreA(event.target.value)} disabled={locked || !match.participant_a_id}/>
      <span className={match.winner_id === match.participant_b_id ? 'font-black text-emerald-700' : 'font-bold'}>{nameOf(match.participant_b)}</span><input className="input py-2 text-center" type="number" min="0" value={scoreB} onChange={event => setScoreB(event.target.value)} disabled={locked || !match.participant_b_id}/>
    </div>
    <div className="mt-3 grid gap-2 sm:grid-cols-2"><label><span className="label">Waktu (WIB)</span><input className="input py-2 text-sm" type="datetime-local" value={scheduled} onChange={event => setScheduled(event.target.value)} disabled={locked}/></label><label><span className="label">Lapangan / lokasi</span><input className="input py-2 text-sm" value={venue} onChange={event => setVenue(event.target.value)} placeholder="Lapangan / lokasi" disabled={locked}/></label></div>
    {!locked && <div className="mt-3 grid gap-2 sm:flex sm:justify-end"><button className="btn-ghost py-2 text-xs" disabled={busy} onClick={() => save('ongoing')}>Berlangsung</button><button className="btn-dark py-2 text-xs" disabled={busy || !match.participant_a_id || !match.participant_b_id} onClick={() => save('completed')}>Konfirmasi Skor</button></div>}
  </article>
}

function GroupStandings({groups}) {
  if (!groups?.length) return null
  return <section className="mt-6 rounded-3xl bg-white p-4 sm:p-7">
    <div className="flex flex-wrap items-end justify-between gap-3"><div><div className="text-xs font-black uppercase tracking-[.16em] text-emerald-700">Hasil Group Stage</div><h2 className="mt-1 font-display text-2xl font-bold">Klasemen Grup</h2></div><p className="text-xs text-slate-500">Urutan: poin, selisih gol, gol memasukkan, lalu jumlah kemenangan.</p></div>
    <div className="mt-5 grid gap-5 xl:grid-cols-2">{groups.map(group => <article key={group.name} className="overflow-hidden rounded-2xl border"><div className="flex items-center justify-between bg-slate-50 px-4 py-3"><b className="font-display">{group.name}</b><span className={`rounded-full px-2 py-1 text-[10px] font-black ${group.completed ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'}`}>{group.completed ? 'Final' : `${group.played_matches}/${group.total_matches} laga`}</span></div><div className="overflow-x-auto"><table className="w-full min-w-[620px] text-xs"><thead className="bg-ink text-[#fff]"><tr><th className="px-3 py-2 text-center">#</th><th className="px-3 py-2 text-left">Tim</th><th title="Main" className="px-2 py-2">MP</th><th title="Menang" className="px-2 py-2">M</th><th title="Seri" className="px-2 py-2">S</th><th title="Kalah" className="px-2 py-2">K</th><th title="Gol memasukkan" className="px-2 py-2">GM</th><th title="Gol kemasukan" className="px-2 py-2">GK</th><th title="Selisih gol" className="px-2 py-2">SG</th><th className="px-3 py-2">Poin</th></tr></thead><tbody>{group.rows.map(row => <tr key={row.registration_id} className={`border-t ${row.qualified ? 'bg-emerald-50' : ''}`}><td className="px-3 py-3 text-center font-black">{row.position}</td><td className="px-3 py-3"><b>{nameOf(row.participant)}</b>{row.qualified && <span className="ml-2 rounded-full bg-emerald-600 px-2 py-1 text-[9px] font-black text-white">LOLOS</span>}<span className="mt-1 block text-[10px] text-slate-400">{row.participant?.school_name}</span></td><td className="px-2 py-3 text-center">{row.played}</td><td className="px-2 py-3 text-center">{row.won}</td><td className="px-2 py-3 text-center">{row.drawn}</td><td className="px-2 py-3 text-center">{row.lost}</td><td className="px-2 py-3 text-center">{row.goals_for}</td><td className="px-2 py-3 text-center">{row.goals_against}</td><td className="px-2 py-3 text-center">{row.goal_difference}</td><td className="px-3 py-3 text-center font-black">{row.points}</td></tr>)}</tbody></table></div></article>)}</div>
  </section>
}

export function TournamentManager() {
  const [data, setData] = useState(null)
  const [mode, setMode] = useState('random')
  const [format, setFormat] = useState('single_elimination')
  const [manual, setManual] = useState([])
  const [manualSlots, setManualSlots] = useState([])
  const [manualGroupAssignments, setManualGroupAssignments] = useState({})
  const [seeds, setSeeds] = useState([])
  const [hosts, setHosts] = useState([])
  const [forceMajeureIds, setForceMajeureIds] = useState([])
  const [forceMajeureReason, setForceMajeureReason] = useState('')
  const [rules, setRules] = useState(initialRules)
  const [reveal, setReveal] = useState(999)
  const [dragged, setDragged] = useState(null)
  const [busy, setBusy] = useState(false)

  const hydrate = (value, reset = false) => {
    setData(value)
    if (reset) {
      setManual(value.participants.map(participant => participant.id))
      setManualSlots([])
      setManualGroupAssignments({})
      setSeeds([])
      setHosts([])
      setForceMajeureIds([])
      setForceMajeureReason('')
    }
  }
  const load = async (id, reset = false, sessionId = null) => {
    try {
      const query = new URLSearchParams()
      if (id) query.set('competition_id', id)
      if (sessionId) query.set('session_id', sessionId)
      hydrate(await api(`/manage/tournaments${query.size ? `?${query}` : ''}`), reset)
    } catch {
      // Kesalahan API sudah ditampilkan oleh pusat pesan aplikasi.
    }
  }

  useEffect(() => { load(undefined, true) }, [])
  useEffect(() => {
    if (!data?.draw || reveal >= data.draw.entries.length) return undefined
    const timer = setTimeout(() => setReveal(value => value + 1), 260)
    return () => clearTimeout(timer)
  }, [data?.draw, reveal])

  const candidates = useMemo(() => data?.force_majeure_candidates || [], [data?.force_majeure_candidates])
  const candidateById = useMemo(() => Object.fromEntries(candidates.map(candidate => [candidate.id, candidate])), [candidates])
  const drawingParticipants = useMemo(() => [...(data?.participants || []), ...forceMajeureIds.map(id => candidateById[id]).filter(Boolean)], [data?.participants, forceMajeureIds, candidateById])
  const participantsById = useMemo(() => Object.fromEntries(drawingParticipants.map(participant => [participant.id, participant])), [drawingParticipants])
  const participantIds = useMemo(() => drawingParticipants.map(participant => participant.id), [drawingParticipants])
  const groupCount = Math.max(2, Number(rules.group_count) || 2)

  useEffect(() => {
    if (mode !== 'manual') return
    setManual(current => [...current.filter(id => participantIds.includes(id)), ...participantIds.filter(id => !current.includes(id))])
    if (eliminationFormats.includes(format)) {
      const size = bracketSizeFor(participantIds.length)
      setManualSlots(current => {
        const seen = new Set()
        const next = Array.from({length: size}, (_, index) => {
          const id = current[index]
          if (!participantIds.includes(id) || seen.has(id)) return null
          seen.add(id)
          return id
        })
        for (const id of participantIds) if (!seen.has(id)) {
          const empty = next.indexOf(null)
          if (empty >= 0) next[empty] = id
        }
        return next
      })
    }
    if (format === 'groups_knockout') {
      setManualGroupAssignments(current => {
        const next = {}, counts = Array(groupCount).fill(0)
        for (const id of participantIds) {
          const previous = current[id]
          if (Number.isInteger(previous) && previous >= 0 && previous < groupCount) {
            next[id] = previous
            counts[previous]++
          }
        }
        for (const id of participantIds) if (next[id] === undefined) {
          const smallest = counts.indexOf(Math.min(...counts))
          next[id] = smallest
          counts[smallest]++
        }
        return next
      })
    }
  }, [mode, format, participantIds, groupCount])

  if (!data) return <div className="p-10 text-center font-bold">Memuat drawing...</div>
  if (!data.competition) return <div className="rounded-3xl bg-white p-10 text-center">Belum ada lomba yang dapat dikelola.</div>

  const reload = () => load(data.competition.id, false, data.session?.id)
  const scopeValue = `${data.competition.id}:${data.session?.id || 0}`
  const selectScope = value => {
    const [competitionId, sessionId] = value.split(':').map(Number)
    load(competitionId, true, sessionId || null)
  }

  const toggle = (setter, values, id) => setter(values.includes(id) ? values.filter(value => value !== id) : [...values, id])
  const toggleForceMajeure = id => {
    const selected = forceMajeureIds.includes(id)
    setForceMajeureIds(selected ? forceMajeureIds.filter(value => value !== id) : [...forceMajeureIds, id])
    if (selected) {
      setManual(manual.filter(value => value !== id))
      setSeeds(seeds.filter(value => value !== id))
      setHosts(hosts.filter(value => value !== id))
    } else setManual([...manual, id])
  }
  const drop = id => {
    if (dragged === null || dragged === id) return
    const next = [...manual], from = next.indexOf(dragged), to = next.indexOf(id)
    next.splice(from, 1)
    next.splice(to, 0, dragged)
    setManual(next)
    setDragged(null)
  }
  const moveManual = (id, direction) => {
    const from = manual.indexOf(id), to = from + direction
    if (to < 0 || to >= manual.length) return
    const next = [...manual]
    ;[next[from], next[to]] = [next[to], next[from]]
    setManual(next)
  }
  const updateManualSlot = (slotIndex, participantId) => {
    setManualSlots(current => {
      const next = [...current], currentId = next[slotIndex]
      const previousSlot = participantId === null ? -1 : next.indexOf(participantId)
      next[slotIndex] = participantId
      if (previousSlot >= 0 && previousSlot !== slotIndex) next[previousSlot] = currentId ?? null
      return next
    })
  }
  const moveToGroup = (participantId, targetGroup) => setManualGroupAssignments(current => ({...current, [participantId]: targetGroup}))
  const manualGroups = Array.from({length: groupCount}, (_, groupIndex) => participantIds.filter(id => manualGroupAssignments[id] === groupIndex))
  const placedSlots = manualSlots.filter(id => id !== null)
  const sameParticipants = values => values.length === participantIds.length && [...values].sort((a, b) => a - b).every((id, index) => id === [...participantIds].sort((a, b) => a - b)[index])
  const manualReady = mode !== 'manual'
    || (eliminationFormats.includes(format) && manualSlots.length === bracketSizeFor(participantIds.length) && sameParticipants(placedSlots))
    || (format === 'groups_knockout' && manualGroups.length === groupCount && manualGroups.every(group => group.length >= 2) && sameParticipants(manualGroups.flat()))
    || (['round_robin', 'round_robin_full'].includes(format) && sameParticipants(manual))
  const start = async () => {
    if (!manualReady || (forceMajeureIds.length && forceMajeureReason.trim().length < 10)) return
    if (forceMajeureIds.length && !window.confirm(`${forceMajeureIds.length} tim belum terverifikasi akan dimasukkan melalui force majeure. Status verifikasi mereka tidak berubah. Lanjutkan drawing?`)) return
    setBusy(true)
    try {
      const draw = await api(`/manage/tournaments/competitions/${data.competition.id}/draw`, {method: 'POST', body: JSON.stringify({
        mode,
        format,
        manual_order: mode === 'manual' && ['round_robin', 'round_robin_full'].includes(format) ? manual : undefined,
        manual_slots: mode === 'manual' && eliminationFormats.includes(format) ? manualSlots : undefined,
        manual_groups: mode === 'manual' && format === 'groups_knockout' ? manualGroups : undefined,
        seeded_ids: mode === 'manual' ? [] : seeds,
        host_ids: mode === 'manual' ? [] : hosts,
        force_majeure_ids: forceMajeureIds,
        force_majeure_reason: forceMajeureIds.length ? forceMajeureReason.trim() : undefined,
        competition_session_id: data.session?.id || null,
        ...rules,
        group_count: Number(rules.group_count),
      })})
      setData({...data, draw})
      setReveal(0)
      await reload()
    } catch {
      // Kesalahan API sudah ditampilkan oleh pusat pesan aplikasi.
    } finally {
      setBusy(false)
    }
  }
  const lock = async () => {
    if (!window.confirm('Kunci drawing? Drawing ulang dan perubahan bagan tidak dapat dilakukan setelah dikunci.')) return
    try {
      await api(`/manage/tournaments/draws/${data.draw.id}/lock`, {method: 'POST'})
      reload()
    } catch {
      // Kesalahan API sudah ditampilkan oleh pusat pesan aplikasi.
    }
  }
  const unlock = async () => {
    if (!window.confirm('Buka kunci drawing? Bagan resmi dan TV Mode tidak ditayangkan sampai drawing dikunci kembali.')) return
    try {
      await api(`/manage/tournaments/draws/${data.draw.id}/unlock`, {method: 'POST'})
      reload()
    } catch {
      // Kesalahan API sudah ditampilkan oleh pusat pesan aplikasi.
    }
  }
  const download = () => {
    const blob = new Blob([JSON.stringify(data.draw, null, 2)], {type: 'application/json'})
    const url = URL.createObjectURL(blob), anchor = document.createElement('a')
    anchor.href = url
    anchor.download = `drawing-${data.competition.slug}-v${data.draw.version}.json`
    anchor.click()
    URL.revokeObjectURL(url)
  }

  const locked = data.draw?.status === 'locked'
  const forceAudit = data.draw?.settings?.force_majeure
  const forceAuditIds = forceAudit?.registration_ids || []

  return <div className="print:bg-white">
    <div className="mb-7 flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-end"><div><div className="text-xs font-black uppercase tracking-[.18em] text-blue-600">Tournament desk</div><h1 className="mt-2 font-display text-3xl font-bold">Drawing & Bagan per Kota</h1>{data.session && <p className="mt-2 text-sm font-bold text-slate-500">{data.session.city} · {data.session.venue}</p>}</div><select aria-label="Pilih lomba dan kota drawing" className="input w-full sm:w-80" value={scopeValue} onChange={event => selectScope(event.target.value)}>{(data.scopes || []).map(scope => <option key={`${scope.competition_id}:${scope.session_id || 0}`} value={`${scope.competition_id}:${scope.session_id || 0}`}>{scope.label}</option>)}</select></div>

    <section className="rounded-3xl bg-ink p-5 text-white sm:p-6"><ShieldCheck className="text-electric"/><p className="mt-4 max-w-3xl leading-7 text-slate-300">Drawing memakai peserta yang telah diverifikasi. Tim yang belum lolos verifikasi hanya dapat disertakan melalui keputusan force majeure yang tercatat.</p><div className="mt-5 flex flex-wrap gap-2 text-xs font-bold"><span className="rounded-full bg-emerald-500/20 px-3 py-2 text-emerald-300">{data.drawing_readiness?.verified ?? data.participants.length} terverifikasi</span><span className="rounded-full bg-amber-500/20 px-3 py-2 text-amber-200">{data.drawing_readiness?.force_majeure_candidates ?? candidates.length} kandidat force majeure</span>{data.drawing_readiness?.rejected > 0 && <span className="rounded-full bg-rose-500/20 px-3 py-2 text-rose-200">{data.drawing_readiness.rejected} ditolak</span>}</div></section>

    {!locked && <section className="mt-6 rounded-3xl bg-white p-4 sm:p-7">
      <h2 className="font-display text-xl font-bold">1. Pilih Mode Drawing</h2>
      <div className="mt-4 grid gap-3 md:grid-cols-3">{[['random', 'Random Drawing', 'Seluruh peserta diacak otomatis.'], ['seeded', 'Seeded Drawing', 'Peserta unggulan ditempatkan terpisah.'], ['manual', 'Manual Drawing', 'Tempatkan langsung ke slot bracket, grup, atau urutan liga.']].map(([value, label, text]) => <button key={value} onClick={() => setMode(value)} className={`rounded-2xl border-2 p-4 text-left ${mode === value ? 'border-ink bg-electric' : 'border-slate-200'}`}><b>{label}</b><p className="mt-1 text-xs text-slate-500">{text}</p></button>)}</div>
      <div className="mt-6 grid gap-5 lg:grid-cols-2"><div><label className="label">Format Kompetisi</label><select className="input" value={format} onChange={event => setFormat(event.target.value)}>{Object.entries(formatLabels).map(([value, label]) => <option key={value} value={value}>{label}</option>)}</select></div>{format === 'groups_knockout' && <div><label className="label">Jumlah Grup</label><input className="input" type="number" min="2" max="16" value={rules.group_count} onChange={event => setRules({...rules, group_count: event.target.value})}/></div>}</div>
      <h3 className="mt-6 font-display font-bold">Aturan Drawing</h3>
      {mode === 'manual' ? <div className="mt-3 grid gap-3 sm:grid-cols-2"><div className="rounded-xl bg-blue-50 p-3 text-sm font-bold text-blue-800">Penempatan manual menjadi keputusan utama; sistem tidak mengubah posisi berdasarkan sekolah, unggulan, atau tuan rumah.</div>{!['round_robin', 'round_robin_full'].includes(format) && <label className="rounded-xl bg-slate-50 p-3 text-sm font-bold"><input className="mr-2" type="checkbox" checked={rules.third_place} onChange={event => setRules({...rules, third_place: event.target.checked})}/>Perebutan juara ketiga</label>}</div> : <div className="mt-3 grid gap-3 sm:grid-cols-2"><label className="rounded-xl bg-slate-50 p-3 text-sm font-bold"><input className="mr-2" type="checkbox" checked={rules.avoid_same_school} onChange={event => setRules({...rules, avoid_same_school: event.target.checked})}/>Pisahkan sekolah/klub di babak pertama</label><label className="rounded-xl bg-slate-50 p-3 text-sm font-bold"><input className="mr-2" type="checkbox" checked={rules.separate_seeds} onChange={event => setRules({...rules, separate_seeds: event.target.checked})}/>Pisahkan peserta unggulan</label><label className="rounded-xl bg-slate-50 p-3 text-sm font-bold"><input className="mr-2" type="checkbox" checked={rules.third_place} onChange={event => setRules({...rules, third_place: event.target.checked})}/>Perebutan juara ketiga</label><select className="input" value={rules.host_policy} onChange={event => setRules({...rules, host_policy: event.target.value})}><option value="random">Tuan rumah: acak</option><option value="first">Tuan rumah: posisi awal</option><option value="last">Tuan rumah: posisi akhir</option></select></div>}

      {!!candidates.length && <section className="mt-7 rounded-2xl border border-amber-300 bg-amber-50 p-4 sm:p-5"><div className="flex items-start gap-3"><span className="grid size-10 shrink-0 place-items-center rounded-xl bg-amber-500 text-white"><ShieldAlert size={19}/></span><div><h3 className="font-display font-bold text-amber-950">Force majeure tim belum terverifikasi</h3><p className="mt-1 text-xs leading-5 text-amber-800">Gunakan hanya berdasarkan keputusan panitia. Tim ditolak tidak dapat dipilih dan status verifikasi tidak berubah.</p></div></div><div className="mt-4 grid gap-3 lg:grid-cols-2">{candidates.map(candidate => <label key={candidate.id} className={`flex cursor-pointer items-start gap-3 rounded-xl border p-3 ${forceMajeureIds.includes(candidate.id) ? 'border-amber-500 bg-white' : 'border-amber-200 bg-amber-50/50'}`}><input type="checkbox" className="mt-1 size-4 accent-amber-600" checked={forceMajeureIds.includes(candidate.id)} onChange={() => toggleForceMajeure(candidate.id)}/><span className="min-w-0"><b className="block break-words text-sm">{nameOf(candidate)}</b><span className="text-xs text-slate-500">{candidate.ticket_code} · {candidate.school_name || 'Sekolah belum diisi'}</span><span className="mt-2 block text-xs font-bold text-amber-800">{candidate.force_majeure_issues?.join(' · ')}</span></span></label>)}</div>{forceMajeureIds.length > 0 && <label className="mt-4 block"><span className="label">Alasan/keputusan force majeure *</span><textarea className="input min-h-24 resize-y bg-white" value={forceMajeureReason} onChange={event => setForceMajeureReason(event.target.value)} placeholder="Contoh: dokumen asli sedang diperiksa dan rapat panitia menyetujui tim mengikuti drawing."/><span className={`mt-1 block text-xs font-bold ${forceMajeureReason.trim().length >= 10 ? 'text-emerald-700' : 'text-rose-600'}`}>{forceMajeureReason.trim().length >= 10 ? 'Alasan siap disimpan dalam audit drawing.' : 'Isi minimal 10 karakter.'}</span></label>}</section>}

      <div className="mt-7 flex flex-wrap items-center justify-between gap-2"><h3 className="font-display font-bold">2. Penempatan Peserta</h3><span className="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold">{drawingParticipants.length} peserta</span></div>
      {mode === 'manual' && eliminationFormats.includes(format) ? <ManualBracketPlacement slots={manualSlots} participants={drawingParticipants} participantsById={participantsById} updateSlot={updateManualSlot}/> : mode === 'manual' && format === 'groups_knockout' ? <ManualGroupPlacement groupCount={groupCount} assignments={manualGroupAssignments} participants={drawingParticipants} moveToGroup={moveToGroup}/> : <div className="mt-3 grid gap-2">{(mode === 'manual' ? manual : drawingParticipants.map(participant => participant.id)).map((id, index) => {const participant = participantsById[id]; const forced = forceMajeureIds.includes(id); return <div key={id} draggable={mode === 'manual'} onDragStart={() => setDragged(id)} onDragOver={event => event.preventDefault()} onDrop={() => drop(id)} className={`flex flex-wrap items-center gap-3 rounded-xl border p-3 ${forced ? 'border-amber-300 bg-amber-50' : 'bg-white'}`}><span className="grid size-8 shrink-0 place-items-center rounded-full bg-ink text-xs font-bold text-white">{index + 1}</span>{mode === 'manual' && <GripVertical className="hidden cursor-grab text-slate-400 sm:block" size={17}/>}<div className="min-w-[140px] flex-1"><b className="block break-words">{nameOf(participant)}</b><span className="text-xs text-slate-400">{participant?.school_name}</span>{forced && <span className="mt-1 block text-[11px] font-black uppercase text-amber-700">Force majeure</span>}</div><div className="ml-auto flex flex-wrap items-center justify-end gap-2">{mode === 'manual' && <><button type="button" aria-label="Naikkan posisi" onClick={() => moveManual(id, -1)} disabled={index === 0} className="grid size-10 place-items-center rounded-xl border bg-white disabled:opacity-30"><ChevronUp size={16}/></button><button type="button" aria-label="Turunkan posisi" onClick={() => moveManual(id, 1)} disabled={index === manual.length - 1} className="grid size-10 place-items-center rounded-xl border bg-white disabled:opacity-30"><ChevronDown size={16}/></button></>}{mode === 'seeded' && <label className="text-xs font-bold"><input type="checkbox" checked={seeds.includes(id)} onChange={() => toggle(setSeeds, seeds, id)} className="mr-1"/>Unggulan</label>}{mode !== 'manual' && <label className="text-xs font-bold"><input type="checkbox" checked={hosts.includes(id)} onChange={() => toggle(setHosts, hosts, id)} className="mr-1"/>Tuan rumah</label>}</div></div>})}</div>}
      {!manualReady && <div className="mt-4 rounded-xl bg-rose-100 p-3 text-sm font-bold text-rose-700">Penempatan manual belum lengkap. Pastikan setiap peserta ditempatkan tepat satu kali dan setiap grup berisi minimal dua tim.</div>}
      <button className="btn-primary mt-6 w-full py-4" onClick={start} disabled={busy || !manualReady || drawingParticipants.length < 2 || (forceMajeureIds.length > 0 && forceMajeureReason.trim().length < 10)}><Play size={18}/>{busy ? 'Mengundi...' : mode === 'manual' ? 'Buat Drawing dari Penempatan Manual' : forceMajeureIds.length ? `Mulai Drawing dengan ${forceMajeureIds.length} Force Majeure` : 'Mulai Drawing'}</button>
    </section>}

    {data.draw && <>
      <section className="mt-6 rounded-3xl bg-white p-4 sm:p-7"><div className="flex flex-wrap items-start justify-between gap-4"><div><div className="text-xs font-bold uppercase tracking-wider text-blue-600">Hasil Drawing · Versi {data.draw.version}</div><h2 className="mt-1 font-display text-2xl font-bold">{formatLabels[data.draw.format]}</h2><p className="mt-1 text-xs text-slate-400">{dateTime(data.draw.drawn_at)} · Operator {data.draw.operator?.name}</p></div><div className="grid w-full gap-2 sm:flex sm:w-auto sm:flex-wrap"><button className="btn-ghost" onClick={download}><Download size={15}/>Unduh</button><button className="btn-ghost" onClick={() => window.print()}><Printer size={15}/>Cetak</button>{!locked && <button className="btn-dark" onClick={lock}><LockKeyhole size={15}/>Kunci Drawing</button>}{locked && data.can_unlock && <button className="btn-ghost border-amber-300 text-amber-700" onClick={unlock}><LockKeyhole size={15}/>Buka Kunci</button>}{locked && !data.can_unlock && <span className="rounded-xl bg-slate-100 px-4 py-3 text-xs font-bold text-slate-500"><LockKeyhole className="mr-1 inline" size={14}/>Drawing terkunci</span>}</div></div>
        {forceAudit && <div className="mt-5 rounded-2xl border border-amber-300 bg-amber-50 p-4"><div className="flex items-center gap-2 font-display font-bold text-amber-950"><AlertTriangle size={18}/>Audit force majeure</div><p className="mt-2 text-sm leading-6 text-amber-900">{forceAudit.reason}</p><p className="mt-2 text-xs font-bold text-amber-700">Disetujui {forceAudit.approved_by?.name} · {dateTime(forceAudit.approved_at)} · {forceAudit.teams?.length || 0} tim</p></div>}
        <div className="mt-6 grid gap-2 sm:grid-cols-2">{data.draw.entries.slice(0, reveal).map(entry => <div key={entry.id} className={`rounded-xl border p-3 ${forceAuditIds.includes(entry.registration_id) ? 'border-amber-300 bg-amber-50' : ''}`}><span className="mr-3 inline-grid size-7 place-items-center rounded-full bg-blue-600 text-xs font-bold text-white">{entry.slot_number}</span><b>{entry.is_bye ? 'BYE' : nameOf(entry.registration)}</b>{forceAuditIds.includes(entry.registration_id) && <span className="ml-2 rounded-full bg-amber-200 px-2 py-1 text-[10px] font-black uppercase text-amber-800">Force majeure</span>}<span className="ml-2 text-xs text-slate-400">{entry.group_name || entry.registration?.school_name}</span></div>)}</div>
      </section>
      <GroupStandings groups={data.draw.group_standings}/>
      <section className="mt-6"><div className="mb-4 flex flex-wrap items-center justify-between gap-3"><h2 className="font-display text-2xl font-bold">Bagan Pertandingan</h2>{data.draw.format === 'groups_knockout' && !data.draw.matches.some(match => match.stage === 'knockout') && <button className="btn-dark" onClick={async () => {try {await api(`/manage/tournaments/draws/${data.draw.id}/knockout`, {method: 'POST'}); reload()} catch { /* Pusat pesan aplikasi menampilkan kesalahan. */ }}}>Buat Babak Knockout</button>}</div><div className="grid gap-5 xl:grid-cols-2">{data.draw.matches.map(match => <MatchEditor key={match.id} match={match} reload={reload} locked={locked}/>)}</div></section>
      <section className="mt-6 rounded-3xl bg-white p-5"><h2 className="font-display font-bold">Riwayat Drawing Ulang</h2><div className="mt-3 flex flex-wrap gap-2">{data.history.map(item => <span key={item.id} className="rounded-full bg-slate-100 px-3 py-2 text-xs font-bold">Versi {item.version} · {item.mode} · {dateTime(item.drawn_at)} · {item.status}</span>)}</div></section>
    </>}
  </div>
}
