import { useState } from 'react'
import { api } from './api'

const teamName = participant => participant?.team_name || participant?.full_name || 'Tim'

export function KnockoutDrawing({ draw, reload }) {
  const [mode, setMode] = useState(draw.settings?.knockout?.mode || 'random')
  const [selection, setSelection] = useState({})
  const [busy, setBusy] = useState(false)
  const [editing, setEditing] = useState(false)
  const existing = draw.matches.some(match => match.stage === 'knockout')
  const groups = draw.group_standings || []
  const completed = groups.length >= 2 && groups.every(group => group.completed)
  const qualifiers = completed ? groups.flatMap(group => group.rows.filter(row => row.position <= 2).map(row => ({ ...row, group: group.name }))) : []
  const size = 2 ** Math.ceil(Math.log2(Math.max(2, qualifiers.length)))
  const slots = Array.from({ length: size }, (_, index) => selection[index] || null)
  const selected = slots.filter(Boolean)
  const pairs = Array.from({ length: size / 2 }, (_, index) => slots.slice(index * 2, index * 2 + 2))
  const valid = selected.length === qualifiers.length && new Set(selected).size === qualifiers.length
    && qualifiers.every(row => selected.includes(row.registration_id)) && pairs.every(pair => pair.some(Boolean))
  const audit = draw.settings?.knockout

  const create = async () => {
    if (busy || !completed || (existing && !draw.can_redraw_knockout) || (mode === 'manual' && !valid)) return
    setBusy(true)
    try {
      await api(`/manage/tournaments/draws/${draw.id}/knockout`, {
        method: 'POST', body: JSON.stringify({ mode, ...(mode === 'manual' ? { manual_slots: slots } : {}),
          ...(existing ? { redraw: true, knockout_version: audit?.version || 1 } : {}),
        }),
      })
      setEditing(false)
      await reload()
    } catch {
      // Pusat pesan aplikasi menampilkan kesalahan API.
    } finally {
      setBusy(false)
    }
  }

  if (existing && (!editing || !draw.can_redraw_knockout)) return <section className="mt-6 rounded-2xl border bg-white p-4 sm:p-7">
    <h2 className="font-display text-xl font-bold">Drawing Babak Knockout · Versi {audit?.version || 1}</h2>
    {audit && <p className="mt-2 text-sm text-slate-600">Bagan dibuat {audit.mode === 'manual' ? 'manual oleh panitia' : 'melalui drawing otomatis'} · {audit.created_by_name}.</p>}
    <p className="mt-3 text-sm text-slate-500">{draw.can_redraw_knockout ? 'Drawing dapat diulang secara otomatis atau manual sebelum pertandingan knockout dimulai. BYE otomatis tidak menghalangi drawing ulang.' : 'Drawing ulang ditutup karena pertandingan knockout sudah dimulai atau memiliki hasil.'}</p>
    <button className="btn-ghost mt-4" disabled={!draw.can_redraw_knockout} onClick={() => { setSelection({}); setEditing(true) }}>Drawing Ulang Knockout</button>
  </section>

  return <section className="mt-6 rounded-3xl border border-blue-200 bg-white p-4 sm:p-7">
    <h2 className="font-display text-2xl font-bold">{existing ? 'Drawing Ulang Knockout' : 'Drawing Babak Knockout'}</h2>
    {existing && <p className="mt-3 rounded-xl bg-amber-50 p-4 text-sm text-amber-800">Bagan dan jadwal knockout lama, termasuk perebutan juara ketiga, akan diganti saat disimpan. Hasil fase grup tetap tersimpan.</p>}
    <p className="mt-2 text-sm text-slate-500">Dua tim teratas setiap grup lolos setelah seluruh pertandingan fase grup selesai.</p>
    {!completed ? <p className="mt-4 rounded-xl bg-amber-50 p-4 text-sm font-bold text-amber-800">Selesaikan seluruh pertandingan grup untuk membuka drawing otomatis dan manual.</p> : <>
      <label className="mt-5 block"><span className="label">Metode pembuatan bagan knockout</span><select className="input" value={mode} disabled={busy} onChange={event => { setMode(event.target.value); setSelection({}) }}>
        <option value="random">Otomatis — Undi silang grup</option>
        <option value="manual">Manual — Keputusan panitia</option>
      </select></label>
      <p className="mt-3 text-sm text-blue-800">{mode === 'random' ? 'Sistem mengundi juara grup melawan peringkat 2 dari grup lain.' : 'Tentukan pasangan dan posisi bagan. Hanya peringkat 1 dan 2 setiap grup yang dapat dipilih; semua tim wajib dimasukkan tepat satu kali.'}</p>
      <div className="mt-4 flex flex-wrap gap-2">{qualifiers.map(row => <span key={row.registration_id} className="rounded-xl bg-emerald-50 px-3 py-2 text-xs text-emerald-900"><b>{row.group} · Peringkat {row.position}</b> — {teamName(row.participant)}</span>)}</div>
      {size > qualifiers.length && <p className="mt-3 text-xs text-slate-500">Bagan {size} slot membutuhkan {size - qualifiers.length} BYE. Tim dengan BYE langsung lolos ke ronde berikutnya.</p>}
      {mode === 'manual' && <>
        <div className="mt-5 grid gap-4 lg:grid-cols-2">{pairs.map((pair, matchIndex) => <article key={matchIndex} className="rounded-2xl border bg-slate-50 p-4">
          <h3 className="mb-3 text-sm font-bold">Pertandingan {matchIndex + 1} · Ronde pertama</h3>
          {pair.map((id, side) => <label key={side} className="mt-3 block"><span className="mb-1 block text-xs font-bold">Tim {side === 0 ? 'A' : 'B'}</span><select className="input" aria-label={`Pertandingan ${matchIndex + 1} tim ${side === 0 ? 'A' : 'B'}`} value={id || ''} disabled={busy} onChange={event => setSelection(previous => ({ ...previous, [matchIndex * 2 + side]: Number(event.target.value) || null }))}>
            <option value="">Pilih Tim</option>
            {qualifiers.map(row => <option key={row.registration_id} value={row.registration_id} disabled={row.registration_id !== id && selected.includes(row.registration_id)}>{row.group} · Peringkat {row.position} — {teamName(row.participant)}</option>)}
          </select></label>)}
        </article>)}</div>
        <p className={`mt-3 text-sm ${valid ? 'text-emerald-700' : 'text-amber-800'}`}>{selected.length}/{qualifiers.length} tim ditempatkan. {!valid && 'Lengkapi semua tim; pertandingan tidak boleh berisi dua BYE.'}</p>
      </>}
      <div className="mt-5 flex flex-wrap gap-3"><button className="btn-primary w-full sm:w-auto" disabled={busy || (mode === 'manual' && !valid)} onClick={create}>{busy ? 'Membuat bagan...' : existing ? mode === 'manual' ? 'Simpan Drawing Ulang Manual' : 'Undi Ulang & Ganti Bagan' : mode === 'manual' ? 'Buat Bagan Manual' : 'Undi & Buat Bagan Otomatis'}</button>
      {existing && <button className="btn-ghost" disabled={busy} onClick={() => setEditing(false)}>Batal</button>}</div>
    </>}
  </section>
}
