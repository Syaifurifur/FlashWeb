import { useEffect, useMemo, useState } from 'react'
import { AlertTriangle, CalendarDays, Clock3, GripVertical, MapPin, Plus, Send, Sparkles, Trash2 } from 'lucide-react'
import { Link, useParams } from 'react-router-dom'
import { api } from './api'

const TIME_ZONE = 'Asia/Jakarta'
const labels = {unscheduled:'Belum dijadwalkan',upcoming:'Akan datang',check_in:'Check-in',ongoing:'Sedang berlangsung',delayed:'Tertunda',completed:'Selesai',walkover:'Walkover',cancelled:'Dibatalkan',bye:'Bye'}
const colors = {unscheduled:'bg-slate-100 text-slate-600',upcoming:'bg-blue-50 text-blue-700',check_in:'bg-amber-50 text-amber-700',ongoing:'bg-emerald-50 text-emerald-700',delayed:'bg-orange-50 text-orange-700',completed:'bg-slate-800 text-white',walkover:'bg-violet-50 text-violet-700',cancelled:'bg-rose-50 text-rose-700',bye:'bg-slate-100 text-slate-500'}
const nameOf = participant => participant?.team_name || participant?.full_name || 'Menunggu pemenang'
const pad = value => String(value).padStart(2, '0')
const dateKey = value => value ? new Intl.DateTimeFormat('en-CA', {timeZone:TIME_ZONE, year:'numeric', month:'2-digit', day:'2-digit'}).format(new Date(value)) : ''
const timeValue = value => value ? new Intl.DateTimeFormat('en-GB', {timeZone:TIME_ZONE, hour:'2-digit', minute:'2-digit', hour12:false}).format(new Date(value)) : ''
const timeKey = value => value ? `${timeValue(value)} WIB` : ''
const localDateTime = value => value ? `${dateKey(value)}T${timeValue(value)}` : ''
const displayDate = value => value ? new Intl.DateTimeFormat('id-ID', {dateStyle:'medium', timeStyle:'short', timeZone:TIME_ZONE}).format(new Date(value))+' WIB' : 'Belum dijadwalkan'
const slots = Array.from({length:29}, (_, index) => `${pad(7 + Math.floor(index / 2))}:${index % 2 ? '30' : '00'} WIB`)

function MatchCard({match, onClick, compact = false}) {
  return <button type="button" onClick={onClick} draggable={!compact} className={`w-full rounded-xl border bg-white p-3 text-left shadow-sm ${compact ? '' : 'cursor-grab hover:border-blue-400'}`}>
    <div className="flex items-center justify-between gap-2"><b className="text-[11px] text-blue-600">MATCH {pad(match.match_number)} · {match.round_label}</b>{!compact && <GripVertical size={14} className="text-slate-300"/>}</div>
    <div className="mt-1 truncate text-xs font-bold">{nameOf(match.participant_a)} vs {nameOf(match.participant_b)}</div>
    <span className={`mt-2 inline-block rounded-full px-2 py-1 text-[10px] font-bold ${colors[match.status]}`}>{labels[match.status] || match.status}</span>
  </button>
}

function MatchModal({match, venues, close, save}) {
  const [form, setForm] = useState({scheduled_at:localDateTime(match.scheduled_at), venue:match.venue || venues[0], duration_minutes:match.duration_minutes || 60, status:match.status || 'unscheduled', score_a:match.score_a ?? '', score_b:match.score_b ?? '', winner_id:match.winner_id || '', notify:false})
  const [busy, setBusy] = useState(false)
  const submit = async () => {
    setBusy(true)
    try {
      await save({...form, duration_minutes:Number(form.duration_minutes), score_a:form.score_a === '' ? null : Number(form.score_a), score_b:form.score_b === '' ? null : Number(form.score_b), winner_id:form.winner_id ? Number(form.winner_id) : null, scheduled_at:form.scheduled_at || null})
      close()
    } catch (error) { alert(`${error.message}${error.conflicts?.length ? `\n${error.conflicts.join('\n')}` : ''}`) }
    finally { setBusy(false) }
  }
  return <div className="fixed inset-0 z-50 grid place-items-center bg-black/60 p-4"><div className="max-h-[90vh] w-full max-w-xl overflow-y-auto rounded-3xl bg-white p-6">
    <div className="flex justify-between"><div><div className="text-xs font-black text-blue-600">MATCH {pad(match.match_number)}</div><h2 className="mt-1 font-display text-2xl font-bold">{match.round_label}</h2></div><button type="button" onClick={close}>✕</button></div>
    <div className="mt-5 grid gap-4 sm:grid-cols-2">
      <label><span className="label">Mulai (WIB)</span><input className="input" type="datetime-local" value={form.scheduled_at} onChange={event => setForm({...form, scheduled_at:event.target.value})}/></label>
      <label><span className="label">Lapangan</span><select className="input" value={form.venue} onChange={event => setForm({...form, venue:event.target.value})}>{venues.map(venue => <option key={venue}>{venue}</option>)}</select></label>
      <label><span className="label">Durasi (menit)</span><input className="input" type="number" min="5" max="720" value={form.duration_minutes} onChange={event => setForm({...form, duration_minutes:event.target.value})}/></label>
      <label><span className="label">Status</span><select className="input" value={form.status} onChange={event => setForm({...form, status:event.target.value})}>{Object.entries(labels).map(([key, label]) => <option key={key} value={key}>{label}</option>)}</select></label>
    </div>
    <div className="mt-5 grid grid-cols-[1fr_90px] items-center gap-3"><b>{nameOf(match.participant_a)}</b><input className="input text-center" type="number" min="0" value={form.score_a} onChange={event => setForm({...form, score_a:event.target.value})}/><b>{nameOf(match.participant_b)}</b><input className="input text-center" type="number" min="0" value={form.score_b} onChange={event => setForm({...form, score_b:event.target.value})}/></div>
    {form.status === 'walkover' && <label className="mt-4 block"><span className="label">Pemenang walkover</span><select className="input" value={form.winner_id} onChange={event => setForm({...form, winner_id:event.target.value})}><option value="">Pilih pemenang</option>{[match.participant_a, match.participant_b].filter(Boolean).map(participant => <option key={participant.id} value={participant.id}>{nameOf(participant)}</option>)}</select></label>}
    <label className="mt-5 flex gap-3 rounded-2xl bg-blue-50 p-4 text-sm font-bold text-blue-800"><input type="checkbox" checked={form.notify} onChange={event => setForm({...form, notify:event.target.checked})}/><span>Kirim pembaruan jadwal ke ruang login peserta</span></label>
    <button type="button" className="btn-primary mt-5 w-full py-3" disabled={busy} onClick={submit}><Send size={16}/>{busy ? 'Menyimpan...' : 'Simpan Jadwal Manual'}</button>
  </div></div>
}

function AutomaticScheduler({data, onGenerated}) {
  const [form, setForm] = useState({start_date:data.competition.event_date || dateKey(Date.now()), start_time:'08:00', end_time:'18:00', duration_minutes:60, gap_minutes:30, max_days:1, venues:[...data.competition.venues], replace_existing:false, notify:false})
  const [busy, setBusy] = useState(false)
  const [message, setMessage] = useState('')
  const [error, setError] = useState('')
  const toggleVenue = venue => setForm(current => ({...current, venues:current.venues.includes(venue) ? current.venues.filter(item => item !== venue) : [...current.venues, venue]}))
  const generate = async event => {
    event.preventDefault()
    if (!form.venues.length) { setError('Pilih minimal satu lapangan.'); return }
    setBusy(true); setError(''); setMessage('')
    try {
      const result = await api(`/manage/schedules/competitions/${data.competition.id}/generate`, {method:'POST', body:JSON.stringify({...form, duration_minutes:Number(form.duration_minutes), gap_minutes:Number(form.gap_minutes), max_days:Number(form.max_days)})})
      setMessage(`${result.automation.scheduled_count} pertandingan berhasil dijadwalkan otomatis.${result.automation.waiting_count ? ` ${result.automation.waiting_count} pertandingan menunggu peserta babak sebelumnya.` : ''}`)
      onGenerated(result)
    } catch (requestError) { setError(requestError.message) }
    finally { setBusy(false) }
  }
  return <section className="rounded-3xl border border-blue-200 bg-gradient-to-br from-blue-50 to-white p-5 sm:p-7">
    <div className="flex flex-wrap items-start justify-between gap-4"><div><div className="flex items-center gap-2 text-xs font-black uppercase tracking-[.16em] text-blue-700"><Sparkles size={16}/>Mode Otomatis</div><h2 className="mt-2 font-display text-2xl font-bold">Buat jadwal otomatis</h2><p className="mt-2 max-w-3xl text-sm leading-6 text-slate-600">Sistem memilih slot paling awal, membagi pertandingan ke lapangan aktif, menjaga jeda tim, dan melewati peserta yang belum diketahui.</p></div><span className="rounded-full bg-blue-600 px-3 py-2 text-xs font-bold text-white">Semua waktu WIB</span></div>
    <form onSubmit={generate} className="mt-6 grid gap-4">
      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <label><span className="label">Tanggal mulai</span><input className="input bg-white" type="date" value={form.start_date} onChange={event => setForm({...form, start_date:event.target.value})} required/></label>
        <label><span className="label">Jam mulai (WIB)</span><input className="input bg-white" type="time" value={form.start_time} onChange={event => setForm({...form, start_time:event.target.value})} required/></label>
        <label><span className="label">Jam selesai (WIB)</span><input className="input bg-white" type="time" value={form.end_time} onChange={event => setForm({...form, end_time:event.target.value})} required/></label>
        <label><span className="label">Maksimal hari</span><input className="input bg-white" type="number" min="1" max="31" value={form.max_days} onChange={event => setForm({...form, max_days:event.target.value})} required/></label>
        <label><span className="label">Durasi pertandingan</span><div className="relative"><input className="input bg-white pr-20" type="number" min="5" max="720" value={form.duration_minutes} onChange={event => setForm({...form, duration_minutes:event.target.value})} required/><span className="absolute right-3 top-1/2 -translate-y-1/2 text-xs font-bold text-slate-400">menit</span></div></label>
        <label><span className="label">Jeda antarsesi</span><div className="relative"><input className="input bg-white pr-20" type="number" min="0" max="240" value={form.gap_minutes} onChange={event => setForm({...form, gap_minutes:event.target.value})} required/><span className="absolute right-3 top-1/2 -translate-y-1/2 text-xs font-bold text-slate-400">menit</span></div></label>
      </div>
      <div><span className="label">Lapangan yang digunakan</span><div className="mt-2 flex flex-wrap gap-2">{data.competition.venues.map(venue => <label key={venue} className={`cursor-pointer rounded-xl border px-4 py-3 text-sm font-bold ${form.venues.includes(venue) ? 'border-blue-500 bg-blue-600 text-white' : 'bg-white text-slate-600'}`}><input type="checkbox" className="sr-only" checked={form.venues.includes(venue)} onChange={() => toggleVenue(venue)}/>{venue}</label>)}</div></div>
      <div className="grid gap-3 sm:grid-cols-2"><label className="flex gap-3 rounded-2xl border bg-white p-4 text-sm"><input type="checkbox" checked={form.replace_existing} onChange={event => setForm({...form, replace_existing:event.target.checked})}/><span><b className="block">Atur ulang jadwal yang ada</b><span className="text-xs text-slate-500">Pertandingan selesai, walkover, dibatalkan, check-in, dan berlangsung tidak diubah.</span></span></label><label className="flex gap-3 rounded-2xl border bg-white p-4 text-sm"><input type="checkbox" checked={form.notify} onChange={event => setForm({...form, notify:event.target.checked})}/><span><b className="block">Kirim notifikasi peserta</b><span className="text-xs text-slate-500">Kirim satu ringkasan setelah jadwal berhasil dibuat.</span></span></label></div>
      {error && <p className="rounded-xl bg-rose-100 p-3 text-sm font-bold text-rose-700">{error}</p>}{message && <p className="rounded-xl bg-emerald-100 p-3 text-sm font-bold text-emerald-700">{message}</p>}
      <button className="btn-primary w-full py-4" disabled={busy}><Sparkles size={18}/>{busy ? 'Menyusun jadwal...' : 'Buat Jadwal Otomatis'}</button>
    </form>
  </section>
}

export function ScheduleManager() {
  const [data, setData] = useState(null)
  const [day, setDay] = useState('')
  const [dragged, setDragged] = useState(null)
  const [selected, setSelected] = useState(null)
  const [venuesText, setVenuesText] = useState('')
  const [notifyDrag, setNotifyDrag] = useState(true)
  const [block, setBlock] = useState({title:'Istirahat', venue:'', starts_at:'', duration_minutes:60})
  const load = id => api(`/manage/schedules${id ? `?competition_id=${id}` : ''}`).then(value => {
    setData(value); setVenuesText(value.competition?.venues?.join(', ') || '')
    const selectedDay = value.competition?.event_date || dateKey(Date.now())
    setDay(selectedDay); setBlock(current => ({...current, venue:value.competition?.venues?.[0] || '', starts_at:`${selectedDay}T12:00`}))
  })
  useEffect(() => { load() }, [])
  if (!data) return <div className="p-10 text-center font-bold">Memuat jadwal...</div>
  if (!data.competition) return <div className="rounded-3xl bg-white p-10 text-center">Belum ada lomba yang dapat dijadwalkan.</div>

  const scheduled = data.matches.filter(match => dateKey(match.scheduled_at) === day)
  const unscheduled = data.matches.filter(match => !match.scheduled_at || match.status === 'unscheduled')
  const timelineSlots = [...new Set([
    ...slots,
    ...scheduled.map(match => timeKey(match.scheduled_at)),
    ...data.blocks.filter(item => dateKey(item.starts_at) === day).map(item => timeKey(item.starts_at)),
  ])].filter(Boolean).sort((a, b) => a.localeCompare(b))
  const update = async (match, fields, force = false) => {
    try { const value = await api(`/manage/schedules/matches/${match.id}`, {method:'PUT', body:JSON.stringify({scheduled_at:match.scheduled_at, venue:match.venue, duration_minutes:match.duration_minutes || 60, status:match.status, ...fields, force})}); setData({...data, ...value}); return value }
    catch (error) { if (error.conflicts?.length && confirm(`${error.conflicts.join('\n')}\n\nTetap simpan jadwal?`)) return update(match, fields, true); throw error }
  }
  const drop = async (venue, time) => { if (!dragged) return; const match = data.matches.find(item => item.id === dragged); setDragged(null); try { await update(match, {scheduled_at:`${day}T${time.replace(' WIB', '')}`, venue, status:'upcoming', notify:notifyDrag}) } catch (error) { alert(error.message) } }
  const saveVenues = async () => { const venues = venuesText.split(',').map(value => value.trim()).filter(Boolean); try { const value = await api(`/manage/schedules/competitions/${data.competition.id}/venues`, {method:'PUT', body:JSON.stringify({venues})}); setData({...data, ...value}); setVenuesText(value.competition.venues.join(', ')) } catch (error) { alert(error.message) } }
  const addBlock = async () => { try { const value = await api(`/manage/schedules/competitions/${data.competition.id}/blocks`, {method:'POST', body:JSON.stringify({...block, duration_minutes:Number(block.duration_minutes)})}); setData({...data, ...value}) } catch (error) { if (error.conflicts?.length && confirm(`${error.conflicts.join('\n')}\nTetap tambahkan?`)) { const value = await api(`/manage/schedules/competitions/${data.competition.id}/blocks`, {method:'POST', body:JSON.stringify({...block, duration_minutes:Number(block.duration_minutes), force:true})}); setData({...data, ...value}) } else alert(error.message) } }
  const deleteBlock = async id => { const value = await api(`/manage/schedules/blocks/${id}`, {method:'DELETE'}); setData({...data, ...value}) }

  return <div>
    <div className="mb-7 flex flex-wrap items-end justify-between gap-4"><div><div className="text-xs font-black uppercase tracking-[.18em] text-blue-600">Match operations</div><h1 className="mt-2 font-display text-3xl font-bold">Jadwal Pertandingan</h1><p className="mt-2 text-sm text-slate-500">Pilih penjadwalan otomatis atau atur setiap pertandingan secara manual. Seluruh waktu menggunakan WIB.</p></div><select className="input w-full sm:w-72" value={data.competition.id} onChange={event => load(event.target.value)}>{data.competitions.map(competition => <option key={competition.id} value={competition.id}>{competition.title}</option>)}</select></div>
    {!data.draw ? <div className="rounded-3xl bg-amber-50 p-8 font-bold text-amber-800">Buat drawing dan bagan terlebih dahulu sebelum menyusun jadwal.</div> : <div className="grid gap-7">
      <AutomaticScheduler key={`${data.competition.id}-${data.competition.venues.join('|')}`} data={data} onGenerated={result => { setData(result); if (result.automation?.start_at) setDay(dateKey(result.automation.start_at)) }}/>
      <section><div className="mb-4 flex flex-wrap items-end justify-between gap-3"><div><div className="text-xs font-black uppercase tracking-[.16em] text-violet-700">Mode Manual</div><h2 className="mt-1 font-display text-2xl font-bold">Atur jadwal secara manual</h2><p className="mt-1 text-sm text-slate-500">Tarik kartu ke slot waktu atau klik pertandingan untuk mengedit detailnya.</p></div><label className="text-xs font-bold"><input className="mr-2" type="checkbox" checked={notifyDrag} onChange={event => setNotifyDrag(event.target.checked)}/>Notifikasi saat dipindah</label></div>
        <div className="grid gap-5 xl:grid-cols-[1fr_340px]">
          <section className="overflow-hidden rounded-3xl bg-white"><div className="flex flex-wrap items-center justify-between gap-3 border-b p-5"><div className="flex items-center gap-3"><CalendarDays className="text-blue-600"/><input className="input py-2" type="date" value={day} onChange={event => setDay(event.target.value)}/></div><span className="rounded-full bg-blue-50 px-3 py-2 text-xs font-black text-blue-700">WIB</span></div><div className="max-h-[720px] overflow-auto"><div className="grid min-w-[760px]" style={{gridTemplateColumns:`90px repeat(${data.competition.venues.length}, minmax(210px, 1fr))`}}><div className="sticky top-0 z-20 bg-ink p-3 text-xs font-bold text-white">Waktu (WIB)</div>{data.competition.venues.map(venue => <div key={venue} className="sticky top-0 z-20 border-l bg-ink p-3 text-center text-xs font-bold text-white">{venue}</div>)}{timelineSlots.map(time => <div key={time} className="contents"><div className="border-t bg-slate-50 p-3 text-xs font-bold text-slate-500">{time}</div>{data.competition.venues.map(venue => { const matches = scheduled.filter(match => match.venue === venue && timeKey(match.scheduled_at) === time), blocks = data.blocks.filter(item => item.venue === venue && dateKey(item.starts_at) === day && timeKey(item.starts_at) === time); return <div key={`${time}-${venue}`} onDragOver={event => event.preventDefault()} onDrop={() => drop(venue, time)} className="min-h-24 border-l border-t p-2 hover:bg-blue-50">{blocks.map(item => <div key={item.id} className="mb-2 rounded-xl bg-amber-100 p-2 text-xs font-bold text-amber-800">{item.title} · {item.duration_minutes}m <button type="button" onClick={() => deleteBlock(item.id)} className="float-right"><Trash2 size={13}/></button></div>)}{matches.map(match => <div key={match.id} draggable onDragStart={() => setDragged(match.id)}><MatchCard match={match} onClick={() => setSelected(match)}/></div>)}</div>})}</div>)}</div></div></section>
          <aside className="space-y-5"><section className="rounded-3xl bg-white p-5"><h2 className="font-display text-lg font-bold">Belum Dijadwalkan</h2><p className="mt-1 text-xs text-slate-400">Tarik pertandingan ke kotak waktu dan lapangan.</p><div className="mt-4 grid max-h-80 gap-3 overflow-auto">{unscheduled.length ? unscheduled.map(match => <div key={match.id} draggable onDragStart={() => setDragged(match.id)}><MatchCard match={match} onClick={() => setSelected(match)}/></div>) : <p className="rounded-xl bg-emerald-50 p-3 text-sm font-bold text-emerald-700">Semua pertandingan siap sudah ditempatkan.</p>}</div></section><section className="rounded-3xl bg-white p-5"><h2 className="font-display font-bold">Daftar Lapangan</h2><textarea className="input mt-3 min-h-24" value={venuesText} onChange={event => setVenuesText(event.target.value)} placeholder="Pisahkan dengan koma"/><button type="button" className="btn-dark mt-3 w-full" onClick={saveVenues}>Simpan Lapangan</button></section><section className="rounded-3xl bg-white p-5"><h2 className="font-display font-bold">Tambah Waktu Istirahat (WIB)</h2><div className="mt-3 grid gap-2"><input className="input" value={block.title} onChange={event => setBlock({...block, title:event.target.value})}/><select className="input" value={block.venue} onChange={event => setBlock({...block, venue:event.target.value})}>{data.competition.venues.map(venue => <option key={venue}>{venue}</option>)}</select><input className="input" aria-label="Mulai istirahat (WIB)" type="datetime-local" value={block.starts_at} onChange={event => setBlock({...block, starts_at:event.target.value})}/><input className="input" aria-label="Durasi istirahat dalam menit" type="number" value={block.duration_minutes} onChange={event => setBlock({...block, duration_minutes:event.target.value})}/><button type="button" className="btn-primary" onClick={addBlock}><Plus size={16}/>Tambahkan</button></div></section>{data.conflicts.length > 0 && <section className="rounded-3xl bg-rose-50 p-5 text-rose-800"><div className="flex items-center gap-2 font-bold"><AlertTriangle size={18}/>Konflik Jadwal ({data.conflicts.length})</div>{data.conflicts.map((conflict, index) => <p key={index} className="mt-2 text-xs">• {conflict.message}</p>)}</section>}</aside>
        </div>
      </section>
      {selected && <MatchModal match={selected} venues={data.competition.venues} close={() => setSelected(null)} save={fields => update(selected, fields)}/>}
    </div>}
  </div>
}

export function SchedulePublic() {
  const {slug} = useParams()
  const [data, setData] = useState(null)
  const [filters, setFilters] = useState({day:'', category:'', group:'', participant:'', venue:'', round:'', status:''})
  useEffect(() => { api(`/competitions/${slug}/schedule`).then(value => { setData(value); setFilters(current => ({...current, category:value.competition.category || ''})) }) }, [slug])
  const matches = useMemo(() => data?.matches.filter(match => (!filters.day || dateKey(match.scheduled_at) === filters.day) && (!filters.category || data.competition.category === filters.category) && (!filters.group || match.group_name === filters.group) && (!filters.participant || `${nameOf(match.participant_a)} ${nameOf(match.participant_b)}`.toLowerCase().includes(filters.participant.toLowerCase())) && (!filters.venue || match.venue === filters.venue) && (!filters.round || match.round_label === filters.round) && (!filters.status || match.status === filters.status)) || [], [data, filters])
  if (!data) return <div className="min-h-96 p-20 text-center font-bold">Memuat jadwal...</div>
  const groups = [...new Set(data.matches.map(match => match.group_name).filter(Boolean))]
  const rounds = [...new Set(data.matches.map(match => match.round_label))]
  return <section className="min-h-screen bg-slate-50 px-5 py-12"><div className="mx-auto max-w-6xl"><Link to={`/lomba/${slug}`} className="text-sm font-bold text-slate-500">← Kembali ke detail lomba</Link><div className="mt-7"><div className="text-xs font-black uppercase tracking-[.18em] text-blue-600">Jadwal Resmi · WIB</div><h1 className="mt-2 font-display text-4xl font-bold">{data.competition.title}</h1><p className="mt-2 text-slate-500">Seluruh waktu menggunakan WIB. Jadwal dapat berubah dan pembaruan panitia dikirim ke ruang login peserta.</p></div>
    <div className="mt-7 grid gap-3 rounded-3xl bg-white p-5 sm:grid-cols-2 lg:grid-cols-4"><input className="input" type="date" value={filters.day} onChange={event => setFilters({...filters, day:event.target.value})}/><select className="input" value={filters.category} onChange={event => setFilters({...filters, category:event.target.value})}><option value="">Semua kategori</option><option>{data.competition.category}</option></select><select className="input" value={filters.group} onChange={event => setFilters({...filters, group:event.target.value})}><option value="">Semua grup</option>{groups.map(group => <option key={group}>{group}</option>)}</select><input className="input" placeholder="Cari peserta..." value={filters.participant} onChange={event => setFilters({...filters, participant:event.target.value})}/><select className="input" value={filters.venue} onChange={event => setFilters({...filters, venue:event.target.value})}><option value="">Semua lapangan</option>{data.competition.venues.map(venue => <option key={venue}>{venue}</option>)}</select><select className="input" value={filters.round} onChange={event => setFilters({...filters, round:event.target.value})}><option value="">Semua babak</option>{rounds.map(round => <option key={round}>{round}</option>)}</select><select className="input" value={filters.status} onChange={event => setFilters({...filters, status:event.target.value})}><option value="">Semua status</option>{Object.entries(labels).map(([key, label]) => <option key={key} value={key}>{label}</option>)}</select><button type="button" className="btn-ghost" onClick={() => setFilters({day:'', category:'', group:'', participant:'', venue:'', round:'', status:''})}>Reset Filter</button></div>
    <div className="mt-6 grid gap-4">{matches.length ? matches.map(match => <article key={match.id} className="grid gap-4 rounded-3xl bg-white p-5 sm:grid-cols-[110px_1fr_auto] sm:items-center"><div><div className="text-xs font-black text-blue-600">MATCH {pad(match.match_number)}</div><div className="mt-1 text-xs text-slate-400">{match.round_label}</div></div><div><h2 className="font-display text-lg font-bold">{nameOf(match.participant_a)} <span className="text-slate-300">vs</span> {nameOf(match.participant_b)}</h2><div className="mt-2 flex flex-wrap gap-4 text-xs text-slate-500"><span className="flex gap-1"><Clock3 size={14}/>{displayDate(match.scheduled_at)} · {match.duration_minutes} menit</span><span className="flex gap-1"><MapPin size={14}/>{match.venue || 'Belum ditentukan'}</span></div></div><span className={`rounded-full px-3 py-2 text-xs font-bold ${colors[match.status]}`}>{labels[match.status] || match.status}</span></article>) : <div className="rounded-3xl bg-white p-12 text-center text-slate-500">Tidak ada pertandingan yang sesuai filter.</div>}</div>
  </div></section>
}
