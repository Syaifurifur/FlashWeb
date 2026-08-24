import { useCallback, useEffect, useMemo, useRef, useState } from 'react'
import { AlertTriangle, CalendarDays, Check, Clock3, GripVertical, MapPin, Maximize2, Minimize2, Play, Plus, RefreshCw, Send, Sparkles, Trash2, Trophy, Tv } from 'lucide-react'
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

function MatchCard({match, onClick, onStart, onFinish, busy = false}) {
  const ready = Boolean(match.participant_a_id && match.participant_b_id && match.scheduled_at && match.venue)
  const final = ['walkover', 'bye', 'cancelled'].includes(match.status)
  return <article className="w-full overflow-hidden rounded-xl border bg-white shadow-sm transition hover:border-blue-400">
    <button type="button" onClick={onClick} className="w-full cursor-grab p-3 text-left">
      <div className="flex items-center justify-between gap-2"><b className="text-[11px] text-blue-600">MATCH {pad(match.match_number)} · {match.round_label}</b><GripVertical size={14} className="text-slate-300"/></div>
      <div className="mt-1 truncate text-xs font-bold">{nameOf(match.participant_a)} vs {nameOf(match.participant_b)}</div>
      <span className={`mt-2 inline-block rounded-full px-2 py-1 text-[10px] font-bold ${colors[match.status]}`}>{labels[match.status] || match.status}</span>
    </button>
    {ready && !final && <div className={`grid gap-2 border-t bg-slate-50 p-2 ${match.status === 'completed' ? 'grid-cols-1' : 'grid-cols-2'}`}>
      {match.status !== 'completed' && <button type="button" onClick={onStart} disabled={busy || match.status === 'ongoing'} className={`rounded-lg px-2 py-2 text-[10px] font-bold ${match.status === 'ongoing' ? 'bg-emerald-600 text-white' : 'border bg-white text-emerald-700 disabled:opacity-40'}`}><Play className="mr-1 inline" size={12}/>{match.status === 'ongoing' ? 'Berlangsung' : 'Mulai'}</button>}
      <button type="button" onClick={onFinish} disabled={busy} className={`rounded-lg px-2 py-2 text-[10px] font-bold ${match.status === 'completed' ? 'bg-slate-800 text-white' : 'border bg-white text-slate-700'}`}><Check className="mr-1 inline" size={12}/>{match.status === 'completed' ? 'Edit Hasil' : 'Selesaikan'}</button>
    </div>}
  </article>
}

function MatchModal({match, venues, close, save, initialStatus}) {
  const [form, setForm] = useState({scheduled_at:localDateTime(match.scheduled_at), venue:match.venue || venues[0], duration_minutes:match.duration_minutes || 60, status:initialStatus || match.status || 'unscheduled', score_a:match.score_a ?? '', score_b:match.score_b ?? '', winner_id:match.winner_id || '', notify:false})
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
    {form.status === 'completed' && <p className="mt-5 rounded-xl bg-emerald-50 p-3 text-sm font-bold text-emerald-800">Isi skor akhir kedua peserta untuk menyelesaikan pertandingan.</p>}
    <div className="mt-5 grid grid-cols-[1fr_90px] items-center gap-3"><b>{nameOf(match.participant_a)}</b><input className="input text-center" type="number" min="0" value={form.score_a} onChange={event => setForm({...form, score_a:event.target.value})}/><b>{nameOf(match.participant_b)}</b><input className="input text-center" type="number" min="0" value={form.score_b} onChange={event => setForm({...form, score_b:event.target.value})}/></div>
    {form.status === 'walkover' && <label className="mt-4 block"><span className="label">Pemenang walkover</span><select className="input" value={form.winner_id} onChange={event => setForm({...form, winner_id:event.target.value})}><option value="">Pilih pemenang</option>{[match.participant_a, match.participant_b].filter(Boolean).map(participant => <option key={participant.id} value={participant.id}>{nameOf(participant)}</option>)}</select></label>}
    <label className="mt-5 flex gap-3 rounded-2xl bg-blue-50 p-4 text-sm font-bold text-blue-800"><input type="checkbox" checked={form.notify} onChange={event => setForm({...form, notify:event.target.checked})}/><span>Kirim pembaruan jadwal ke ruang login peserta</span></label>
    <button type="button" className="btn-primary mt-5 w-full py-3" disabled={busy} onClick={submit}><Send size={16}/>{busy ? 'Menyimpan...' : form.status === 'completed' ? 'Simpan Hasil Pertandingan' : 'Simpan Perubahan'}</button>
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
      const result = await api(`/manage/schedules/competitions/${data.competition.id}/generate`, {method:'POST', body:JSON.stringify({...form, competition_session_id:data.session?.id || null, duration_minutes:Number(form.duration_minutes), gap_minutes:Number(form.gap_minutes), max_days:Number(form.max_days)})})
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
  const [quickBusy, setQuickBusy] = useState(null)
  const [venuesText, setVenuesText] = useState('')
  const [notifyDrag, setNotifyDrag] = useState(true)
  const [block, setBlock] = useState({title:'Istirahat', venue:'', starts_at:'', duration_minutes:60})
  const load = (id, sessionId = null) => { const query = new URLSearchParams(); if (id) query.set('competition_id', id); if (sessionId) query.set('session_id', sessionId); return api(`/manage/schedules${query.size ? `?${query}` : ''}`).then(value => {
    setData(value); setVenuesText(value.competition?.venues?.join(', ') || '')
    const selectedDay = value.competition?.event_date || dateKey(Date.now())
    setDay(selectedDay); setBlock(current => ({...current, venue:value.competition?.venues?.[0] || '', starts_at:`${selectedDay}T12:00`}))
  }) }
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
  const startMatch = async match => {
    setQuickBusy(match.id)
    try { await update(match, {status:'ongoing'}) }
    catch (error) { alert(error.message) }
    finally { setQuickBusy(null) }
  }
  const finishMatch = match => setSelected({match, initialStatus:'completed'})
  const drop = async (venue, time) => { if (!dragged) return; const match = data.matches.find(item => item.id === dragged); setDragged(null); try { await update(match, {scheduled_at:`${day}T${time.replace(' WIB', '')}`, venue, status:'upcoming', notify:notifyDrag}) } catch (error) { alert(error.message) } }
  const saveVenues = async () => { const venues = venuesText.split(',').map(value => value.trim()).filter(Boolean); try { const value = await api(`/manage/schedules/competitions/${data.competition.id}/venues`, {method:'PUT', body:JSON.stringify({competition_session_id:data.session?.id || null, venues})}); setData({...data, ...value}); setVenuesText(value.competition.venues.join(', ')) } catch (error) { alert(error.message) } }
  const addBlock = async () => { const payload={...block, competition_session_id:data.session?.id || null, duration_minutes:Number(block.duration_minutes)}; try { const value = await api(`/manage/schedules/competitions/${data.competition.id}/blocks`, {method:'POST', body:JSON.stringify(payload)}); setData({...data, ...value}) } catch (error) { if (error.conflicts?.length && confirm(`${error.conflicts.join('\n')}\nTetap tambahkan?`)) { const value = await api(`/manage/schedules/competitions/${data.competition.id}/blocks`, {method:'POST', body:JSON.stringify({...payload, force:true})}); setData({...data, ...value}) } else alert(error.message) } }
  const deleteBlock = async id => { const value = await api(`/manage/schedules/blocks/${id}`, {method:'DELETE'}); setData({...data, ...value}) }

  return <div>
    <div className="mb-7 flex flex-wrap items-end justify-between gap-4"><div><div className="text-xs font-black uppercase tracking-[.18em] text-blue-600">Match operations</div><h1 className="mt-2 font-display text-3xl font-bold">Jadwal Pertandingan per Kota</h1><p className="mt-2 text-sm text-slate-500">{data.session ? `${data.session.city} · ${data.session.venue}. ` : ''}Pilih penjadwalan otomatis atau atur setiap pertandingan secara manual. Seluruh waktu menggunakan WIB.</p></div><div className="flex w-full flex-col gap-2 sm:w-auto sm:flex-row"><Link to={`/lomba/${data.competition.slug}/tv${data.session?.id ? `?session_id=${data.session.id}` : ''}`} target="_blank" className="btn-dark justify-center"><Tv size={17}/>Buka TV Mode</Link><select aria-label="Pilih lomba dan kota jadwal" className="input w-full sm:w-80" value={`${data.competition.id}:${data.session?.id || 0}`} onChange={event => {const [competitionId,sessionId]=event.target.value.split(':').map(Number);load(competitionId,sessionId || null)}}>{(data.scopes || []).map(scope => <option key={`${scope.competition_id}:${scope.session_id || 0}`} value={`${scope.competition_id}:${scope.session_id || 0}`}>{scope.label}</option>)}</select></div></div>
    {!data.draw ? <div className="rounded-3xl bg-amber-50 p-8 font-bold text-amber-800">Buat drawing dan bagan terlebih dahulu sebelum menyusun jadwal.</div> : <div className="grid gap-7">
      <AutomaticScheduler key={`${data.competition.id}-${data.session?.id || 0}-${data.competition.venues.join('|')}`} data={data} onGenerated={result => { setData(current => ({...current, ...result})); if (result.automation?.start_at) setDay(dateKey(result.automation.start_at)) }}/>
      <section><div className="mb-4 flex flex-wrap items-end justify-between gap-3"><div><div className="text-xs font-black uppercase tracking-[.16em] text-violet-700">Mode Manual</div><h2 className="mt-1 font-display text-2xl font-bold">Atur jadwal secara manual</h2><p className="mt-1 text-sm text-slate-500">Tarik kartu ke slot waktu atau klik pertandingan untuk mengedit detailnya.</p></div><label className="text-xs font-bold"><input className="mr-2" type="checkbox" checked={notifyDrag} onChange={event => setNotifyDrag(event.target.checked)}/>Notifikasi saat dipindah</label></div>
        <div className="grid gap-5 xl:grid-cols-[1fr_340px]">
          <section className="overflow-hidden rounded-3xl bg-white"><div className="flex flex-wrap items-center justify-between gap-3 border-b p-5"><div className="flex items-center gap-3"><CalendarDays className="text-blue-600"/><input className="input py-2" type="date" value={day} onChange={event => setDay(event.target.value)}/></div><span className="rounded-full bg-blue-50 px-3 py-2 text-xs font-black text-blue-700">WIB</span></div><div className="max-h-[720px] overflow-auto"><div className="grid min-w-[760px]" style={{gridTemplateColumns:`90px repeat(${data.competition.venues.length}, minmax(210px, 1fr))`}}><div className="sticky top-0 z-20 bg-ink p-3 text-xs font-bold text-white">Waktu (WIB)</div>{data.competition.venues.map(venue => <div key={venue} className="sticky top-0 z-20 border-l bg-ink p-3 text-center text-xs font-bold text-white">{venue}</div>)}{timelineSlots.map(time => <div key={time} className="contents"><div className="border-t bg-slate-50 p-3 text-xs font-bold text-slate-500">{time}</div>{data.competition.venues.map(venue => { const matches = scheduled.filter(match => match.venue === venue && timeKey(match.scheduled_at) === time), blocks = data.blocks.filter(item => item.venue === venue && dateKey(item.starts_at) === day && timeKey(item.starts_at) === time); return <div key={`${time}-${venue}`} onDragOver={event => event.preventDefault()} onDrop={() => drop(venue, time)} className="min-h-24 border-l border-t p-2 hover:bg-blue-50">{blocks.map(item => <div key={item.id} className="mb-2 rounded-xl bg-amber-100 p-2 text-xs font-bold text-amber-800">{item.title} · {item.duration_minutes}m <button type="button" onClick={() => deleteBlock(item.id)} className="float-right"><Trash2 size={13}/></button></div>)}{matches.map(match => <div key={match.id} draggable onDragStart={() => setDragged(match.id)}><MatchCard match={match} busy={quickBusy === match.id} onClick={() => setSelected({match})} onStart={() => startMatch(match)} onFinish={() => finishMatch(match)}/></div>)}</div>})}</div>)}</div></div></section>
          <aside className="space-y-5"><section className="rounded-3xl bg-white p-5"><h2 className="font-display text-lg font-bold">Belum Dijadwalkan</h2><p className="mt-1 text-xs text-slate-400">Tarik pertandingan ke kotak waktu dan lapangan.</p><div className="mt-4 grid max-h-80 gap-3 overflow-auto">{unscheduled.length ? unscheduled.map(match => <div key={match.id} draggable onDragStart={() => setDragged(match.id)}><MatchCard match={match} onClick={() => setSelected({match})}/></div>) : <p className="rounded-xl bg-emerald-50 p-3 text-sm font-bold text-emerald-700">Semua pertandingan siap sudah ditempatkan.</p>}</div></section><section className="rounded-3xl bg-white p-5"><h2 className="font-display font-bold">Daftar Lapangan</h2><textarea className="input mt-3 min-h-24" value={venuesText} onChange={event => setVenuesText(event.target.value)} placeholder="Pisahkan dengan koma"/><button type="button" className="btn-dark mt-3 w-full" onClick={saveVenues}>Simpan Lapangan</button></section><section className="rounded-3xl bg-white p-5"><h2 className="font-display font-bold">Tambah Waktu Istirahat (WIB)</h2><div className="mt-3 grid gap-2"><input className="input" value={block.title} onChange={event => setBlock({...block, title:event.target.value})}/><select className="input" value={block.venue} onChange={event => setBlock({...block, venue:event.target.value})}>{data.competition.venues.map(venue => <option key={venue}>{venue}</option>)}</select><input className="input" aria-label="Mulai istirahat (WIB)" type="datetime-local" value={block.starts_at} onChange={event => setBlock({...block, starts_at:event.target.value})}/><input className="input" aria-label="Durasi istirahat dalam menit" type="number" value={block.duration_minutes} onChange={event => setBlock({...block, duration_minutes:event.target.value})}/><button type="button" className="btn-primary" onClick={addBlock}><Plus size={16}/>Tambahkan</button></div></section>{data.conflicts.length > 0 && <section className="rounded-3xl bg-rose-50 p-5 text-rose-800"><div className="flex items-center gap-2 font-bold"><AlertTriangle size={18}/>Konflik Jadwal ({data.conflicts.length})</div>{data.conflicts.map((conflict, index) => <p key={index} className="mt-2 text-xs">• {conflict.message}</p>)}</section>}</aside>
        </div>
      </section>
      {selected && <MatchModal match={selected.match} initialStatus={selected.initialStatus} venues={data.competition.venues} close={() => setSelected(null)} save={fields => update(selected.match, fields)}/>}
    </div>}
  </div>
}

const tvScore = value => value === null || value === undefined ? '–' : Number(value) % 1 === 0 ? Number(value) : Number(value).toFixed(1)

function TvScoreCard({match, live = false}) {
  const participantA = nameOf(match.participant_a), participantB = nameOf(match.participant_b)
  const winnerA = Number(match.winner_id) === Number(match.participant_a_id)
  const winnerB = Number(match.winner_id) === Number(match.participant_b_id)
  return <article className={`overflow-hidden rounded-2xl border ${live ? 'border-emerald-400 bg-emerald-400/10' : 'border-white/10 bg-white/[.06]'}`}>
    <div className="flex items-center justify-between border-b border-white/10 px-4 py-2 text-[10px] font-black uppercase tracking-[.16em] text-cyan-300"><span>Match {pad(match.match_number)} · {match.round_label}</span><span>{labels[match.status]} · {match.venue || 'Venue belum ditentukan'}</span></div>
    <div className="grid grid-cols-[1fr_auto_1fr] items-center gap-3 p-4"><div className={`min-w-0 text-right ${winnerA ? 'text-white' : 'text-slate-300'}`}><div className="truncate font-display text-base font-bold sm:text-lg">{participantA}</div>{winnerA&&<span className="text-[10px] font-black uppercase text-emerald-300">Pemenang</span>}</div>{match.status==='walkover'?<div className="min-w-32 rounded-xl bg-violet-500/20 px-4 py-4 text-center font-display text-2xl font-black text-violet-200">W.O.</div>:<div className="flex min-w-32 items-center justify-center gap-3 rounded-xl bg-black/30 px-4 py-3 font-display text-3xl font-black text-white sm:text-4xl"><span>{tvScore(match.score_a)}</span><span className="text-white/30">:</span><span>{tvScore(match.score_b)}</span></div>}<div className={`min-w-0 ${winnerB ? 'text-white' : 'text-slate-300'}`}><div className="truncate font-display text-base font-bold sm:text-lg">{participantB}</div>{winnerB&&<span className="text-[10px] font-black uppercase text-emerald-300">Pemenang</span>}</div></div>
  </article>
}

function TvUpcomingCard({match, index}) {
  return <article className="grid grid-cols-[54px_1fr_auto] items-center gap-3 rounded-2xl border border-white/10 bg-white/[.05] p-3"><span className="grid size-11 place-items-center rounded-xl bg-blue-500/20 font-display text-lg font-black text-blue-200">{index+1}</span><div className="min-w-0"><div className="text-[10px] font-black uppercase tracking-wider text-cyan-300">Match {pad(match.match_number)} · {match.round_label}</div><div className="mt-1 truncate font-bold text-white">{nameOf(match.participant_a)} <span className="text-white/30">vs</span> {nameOf(match.participant_b)}</div><div className="mt-1 flex gap-3 text-[11px] text-slate-400"><span>{match.venue || 'Venue belum ditentukan'}</span>{match.group_name&&<span>{match.group_name}</span>}</div></div><time className="rounded-xl bg-white/10 px-3 py-2 text-center"><b className="block font-display text-xl text-white">{timeValue(match.scheduled_at)}</b><span className="text-[10px] font-bold text-slate-400">WIB · {dateKey(match.scheduled_at)}</span></time></article>
}

export function TournamentTv() {
  const {slug} = useParams()
  const rootRef = useRef(null)
  const [data, setData] = useState(null)
  const [error, setError] = useState('')
  const [now, setNow] = useState(new Date())
  const [lastUpdated, setLastUpdated] = useState(null)
  const [venue, setVenue] = useState('')
  const [sessionId, setSessionId] = useState(() => new URLSearchParams(window.location.search).get('session_id') || '')
  const [slide, setSlide] = useState(0)
  const [fullscreen, setFullscreen] = useState(false)
  const load = useCallback(() => api(`/competitions/${slug}/schedule${sessionId ? `?session_id=${sessionId}` : ''}`).then(value => { setData(value); setLastUpdated(new Date()); setError('') }).catch(requestError => setError(requestError.message)), [slug,sessionId])
  useEffect(() => { load(); const timer = setInterval(load, 15000); return () => clearInterval(timer) }, [load])
  useEffect(() => { const clock = setInterval(() => setNow(new Date()), 1000); return () => clearInterval(clock) }, [])
  useEffect(() => { const rotation = setInterval(() => setSlide(current => current + 1), 10000); return () => clearInterval(rotation) }, [])
  useEffect(() => { const changed = () => setFullscreen(Boolean(document.fullscreenElement)); document.addEventListener('fullscreenchange', changed); return () => document.removeEventListener('fullscreenchange', changed) }, [])
  const toggleFullscreen = async () => { if (document.fullscreenElement) await document.exitFullscreen(); else await rootRef.current?.requestFullscreen() }
  if (!data) return <main className="grid min-h-screen place-items-center bg-[#07111f] p-8 text-white"><div className="text-center"><Tv className="mx-auto text-cyan-300" size={48}/><p className="mt-4 font-bold">{error || 'Menyiapkan TV Mode...'}</p></div></main>

  const scoped = data.matches.filter(match => !venue || match.venue === venue)
  const bySchedule = (a, b) => (new Date(a.scheduled_at || 0).getTime() || a.match_number) - (new Date(b.scheduled_at || 0).getTime() || b.match_number)
  const liveMatches = scoped.filter(match => ['check_in','ongoing','delayed'].includes(match.status)).sort(bySchedule)
  const results = scoped.filter(match => ['completed','walkover'].includes(match.status)).sort((a, b) => -bySchedule(a, b))
  const upcoming = scoped.filter(match => match.status === 'upcoming' && match.scheduled_at).sort(bySchedule)
  const resultPageCount = Math.max(1, Math.ceil(results.length / 6)), upcomingPageCount = Math.max(1, Math.ceil(upcoming.length / 6))
  const resultPage = slide % resultPageCount, upcomingPage = slide % upcomingPageCount
  const visibleResults = results.slice(resultPage * 6, resultPage * 6 + 6)
  const visibleUpcoming = upcoming.slice(upcomingPage * 6, upcomingPage * 6 + 6)
  const clock = new Intl.DateTimeFormat('id-ID', {timeZone:TIME_ZONE, hour:'2-digit', minute:'2-digit', second:'2-digit', hour12:false}).format(now)
  const today = new Intl.DateTimeFormat('id-ID', {timeZone:TIME_ZONE, weekday:'long', day:'numeric', month:'long', year:'numeric'}).format(now)

  return <main ref={rootRef} className="min-h-screen overflow-hidden bg-[#07111f] text-white">
    <div className="fixed inset-0 opacity-40 [background-image:radial-gradient(circle_at_15%_20%,#0ea5e955,transparent_28%),radial-gradient(circle_at_85%_10%,#22c55e33,transparent_25%),linear-gradient(#ffffff08_1px,transparent_1px),linear-gradient(90deg,#ffffff08_1px,transparent_1px)] [background-size:auto,auto,40px_40px,40px_40px]"/>
    <div className="relative flex min-h-screen flex-col p-4 sm:p-6 lg:p-8">
      <header className="flex flex-wrap items-center justify-between gap-4 border-b border-white/10 pb-5"><div className="flex items-center gap-4"><span className="grid size-12 place-items-center rounded-2xl bg-cyan-400 text-[#07111f]"><Tv size={25}/></span><div><div className="text-[10px] font-black uppercase tracking-[.24em] text-cyan-300">BSI Flash · Live Scoreboard</div><h1 className="mt-1 font-display text-2xl font-black sm:text-3xl">{data.competition.title}</h1>{data.session&&<p className="mt-1 text-xs font-bold text-cyan-200">{data.session.city} · {data.session.venue}</p>}</div></div><div className="flex flex-wrap items-center gap-2">{data.sessions?.length>1&&<select aria-label="Pilih kota TV Mode" className="rounded-xl border border-white/15 bg-white/10 px-3 py-2 text-xs font-bold text-white outline-none" value={data.session?.id || ''} onChange={event => {setSessionId(event.target.value);setVenue('');setSlide(0)}}>{data.sessions.map(session=><option className="text-slate-900" key={session.id} value={session.id}>{session.city}</option>)}</select>}<select aria-label="Filter lapangan TV Mode" className="rounded-xl border border-white/15 bg-white/10 px-3 py-2 text-xs font-bold text-white outline-none" value={venue} onChange={event => { setVenue(event.target.value); setSlide(0) }}><option className="text-slate-900" value="">Semua lapangan</option>{data.competition.venues.map(item=><option className="text-slate-900" key={item}>{item}</option>)}</select><button type="button" onClick={load} className="grid size-10 place-items-center rounded-xl border border-white/15 bg-white/10" aria-label="Perbarui skor"><RefreshCw size={17}/></button><button type="button" onClick={toggleFullscreen} className="grid size-10 place-items-center rounded-xl border border-white/15 bg-white/10" aria-label={fullscreen?'Keluar layar penuh':'Layar penuh'}>{fullscreen?<Minimize2 size={18}/>:<Maximize2 size={18}/>}</button><div className="min-w-36 text-right"><div className="font-display text-3xl font-black tabular-nums">{clock}</div><div className="text-[10px] font-bold uppercase text-slate-400">{today} · WIB</div></div></div></header>

      {!data.draw&&<section className="mt-5 rounded-2xl border border-amber-400/30 bg-amber-400/10 p-4 text-sm font-bold text-amber-200"><AlertTriangle className="mr-2 inline" size={18}/>Drawing resmi belum dikunci. Kunci drawing terlebih dahulu agar skor dan jadwal dapat ditayangkan.</section>}
      {liveMatches.length>0&&<section className="mt-5"><div className="mb-3 flex items-center gap-2 text-xs font-black uppercase tracking-[.2em] text-emerald-300"><span className="size-2 animate-pulse rounded-full bg-emerald-400"/>Sedang Berlangsung</div><div className="grid gap-3 xl:grid-cols-2">{liveMatches.slice(0,2).map(match=><TvScoreCard key={match.id} match={match} live/>)}</div></section>}

      <div className="mt-5 grid flex-1 gap-5 xl:grid-cols-2"><section className="flex min-h-0 flex-col rounded-3xl border border-white/10 bg-black/20 p-4"><div className="mb-4 flex items-center justify-between"><div className="flex items-center gap-3"><span className="grid size-9 place-items-center rounded-xl bg-amber-400/20 text-amber-300"><Trophy size={18}/></span><div><h2 className="font-display text-xl font-black">Hasil Skor Pertandingan</h2><p className="text-[10px] uppercase tracking-wider text-slate-500">Hasil terbaru ditampilkan lebih dahulu</p></div></div><span className="text-xs font-bold text-slate-400">{results.length} hasil · {resultPage+1}/{resultPageCount}</span></div><div className="grid gap-3">{visibleResults.length?visibleResults.map(match=><TvScoreCard key={match.id} match={match}/>):<div className="grid min-h-52 place-items-center rounded-2xl border border-dashed border-white/15 text-sm text-slate-500">Belum ada hasil pertandingan.</div>}</div></section>
        <section className="flex min-h-0 flex-col rounded-3xl border border-white/10 bg-black/20 p-4"><div className="mb-4 flex items-center justify-between"><div className="flex items-center gap-3"><span className="grid size-9 place-items-center rounded-xl bg-blue-400/20 text-blue-300"><CalendarDays size={18}/></span><div><h2 className="font-display text-xl font-black">Pertandingan Selanjutnya</h2><p className="text-[10px] uppercase tracking-wider text-slate-500">Jadwal resmi dalam WIB</p></div></div><span className="text-xs font-bold text-slate-400">{upcoming.length} jadwal · {upcomingPage+1}/{upcomingPageCount}</span></div><div className="grid gap-3">{visibleUpcoming.length?visibleUpcoming.map((match,index)=><TvUpcomingCard key={match.id} match={match} index={upcomingPage*6+index}/>):<div className="grid min-h-52 place-items-center rounded-2xl border border-dashed border-white/15 text-sm text-slate-500">Tidak ada pertandingan berikutnya.</div>}</div></section></div>

      <footer className="mt-5 flex flex-wrap items-center justify-between gap-3 border-t border-white/10 pt-4 text-[10px] font-bold uppercase tracking-wider text-slate-500"><span>Data diperbarui otomatis setiap 15 detik · Tampilan berganti setiap 10 detik</span><span>Pembaruan terakhir {lastUpdated ? timeValue(lastUpdated) : '--:--'} WIB</span></footer>
    </div>
  </main>
}

export function SchedulePublic() {
  const {slug} = useParams()
  const [data, setData] = useState(null)
  const [sessionId, setSessionId] = useState(() => new URLSearchParams(window.location.search).get('session_id') || '')
  const [filters, setFilters] = useState({day:'', category:'', group:'', participant:'', venue:'', round:'', status:''})
  useEffect(() => { api(`/competitions/${slug}/schedule${sessionId ? `?session_id=${sessionId}` : ''}`).then(value => { setData(value); setFilters(current => ({...current, category:value.competition.category || '', group:'', participant:'', venue:'', round:'', status:''})) }) }, [slug,sessionId])
  const matches = useMemo(() => data?.matches.filter(match => (!filters.day || dateKey(match.scheduled_at) === filters.day) && (!filters.category || data.competition.category === filters.category) && (!filters.group || match.group_name === filters.group) && (!filters.participant || `${nameOf(match.participant_a)} ${nameOf(match.participant_b)}`.toLowerCase().includes(filters.participant.toLowerCase())) && (!filters.venue || match.venue === filters.venue) && (!filters.round || match.round_label === filters.round) && (!filters.status || match.status === filters.status)) || [], [data, filters])
  if (!data) return <div className="min-h-96 p-20 text-center font-bold">Memuat jadwal...</div>
  const groups = [...new Set(data.matches.map(match => match.group_name).filter(Boolean))]
  const rounds = [...new Set(data.matches.map(match => match.round_label))]
  return <section className="min-h-screen bg-slate-50 px-5 py-12"><div className="mx-auto max-w-6xl"><div className="flex flex-wrap items-center justify-between gap-3"><Link to={`/lomba/${slug}`} className="text-sm font-bold text-slate-500">← Kembali ke detail lomba</Link><Link to={`/lomba/${slug}/tv${data.session?.id ? `?session_id=${data.session.id}` : ''}`} target="_blank" className="btn-dark"><Tv size={16}/>Buka TV Mode</Link></div><div className="mt-7 flex flex-wrap items-end justify-between gap-4"><div><div className="text-xs font-black uppercase tracking-[.18em] text-blue-600">Jadwal Resmi · WIB</div><h1 className="mt-2 font-display text-4xl font-bold">{data.competition.title}</h1>{data.session&&<p className="mt-2 font-bold text-blue-700">{data.session.city} · {data.session.venue}</p>}<p className="mt-2 text-slate-500">Seluruh waktu menggunakan WIB. Jadwal dapat berubah dan pembaruan panitia dikirim ke ruang login peserta.</p></div>{data.sessions?.length>1&&<select aria-label="Pilih kota jadwal" className="input w-full sm:w-72" value={data.session?.id || ''} onChange={event => setSessionId(event.target.value)}>{data.sessions.map(session=><option key={session.id} value={session.id}>{session.city} · {session.venue}</option>)}</select>}</div>
    <div className="mt-7 grid gap-3 rounded-3xl bg-white p-5 sm:grid-cols-2 lg:grid-cols-4"><input className="input" type="date" value={filters.day} onChange={event => setFilters({...filters, day:event.target.value})}/><select className="input" value={filters.category} onChange={event => setFilters({...filters, category:event.target.value})}><option value="">Semua kategori</option><option>{data.competition.category}</option></select><select className="input" value={filters.group} onChange={event => setFilters({...filters, group:event.target.value})}><option value="">Semua grup</option>{groups.map(group => <option key={group}>{group}</option>)}</select><input className="input" placeholder="Cari peserta..." value={filters.participant} onChange={event => setFilters({...filters, participant:event.target.value})}/><select className="input" value={filters.venue} onChange={event => setFilters({...filters, venue:event.target.value})}><option value="">Semua lapangan</option>{data.competition.venues.map(venue => <option key={venue}>{venue}</option>)}</select><select className="input" value={filters.round} onChange={event => setFilters({...filters, round:event.target.value})}><option value="">Semua babak</option>{rounds.map(round => <option key={round}>{round}</option>)}</select><select className="input" value={filters.status} onChange={event => setFilters({...filters, status:event.target.value})}><option value="">Semua status</option>{Object.entries(labels).map(([key, label]) => <option key={key} value={key}>{label}</option>)}</select><button type="button" className="btn-ghost" onClick={() => setFilters({day:'', category:'', group:'', participant:'', venue:'', round:'', status:''})}>Reset Filter</button></div>
    <div className="mt-6 grid gap-4">{matches.length ? matches.map(match => <article key={match.id} className="grid gap-4 rounded-3xl bg-white p-5 sm:grid-cols-[110px_1fr_auto] sm:items-center"><div><div className="text-xs font-black text-blue-600">MATCH {pad(match.match_number)}</div><div className="mt-1 text-xs text-slate-400">{match.round_label}</div></div><div><h2 className="font-display text-lg font-bold">{nameOf(match.participant_a)} <span className="text-slate-300">vs</span> {nameOf(match.participant_b)}</h2><div className="mt-2 flex flex-wrap gap-4 text-xs text-slate-500"><span className="flex gap-1"><Clock3 size={14}/>{displayDate(match.scheduled_at)} · {match.duration_minutes} menit</span><span className="flex gap-1"><MapPin size={14}/>{match.venue || 'Belum ditentukan'}</span></div></div><span className={`rounded-full px-3 py-2 text-xs font-bold ${colors[match.status]}`}>{labels[match.status] || match.status}</span></article>) : <div className="rounded-3xl bg-white p-12 text-center text-slate-500">Tidak ada pertandingan yang sesuai filter.</div>}</div>
  </div></section>
}
