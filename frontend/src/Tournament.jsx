import { useCallback, useEffect, useState } from 'react'
import { Download, GripVertical, LockKeyhole, Play, Printer, ShieldCheck, Trophy } from 'lucide-react'
import { Link, useParams } from 'react-router-dom'
import { api } from './api'

const nameOf = (participant) => participant?.team_name || participant?.full_name || 'BYE'
const jakartaDate = (value) =>
  value
    ? new Intl.DateTimeFormat('en-CA', {
        timeZone: 'Asia/Jakarta',
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
      }).format(new Date(value))
    : ''
const jakartaTime = (value) =>
  value
    ? new Intl.DateTimeFormat('en-GB', {
        timeZone: 'Asia/Jakarta',
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
      }).format(new Date(value))
    : ''
const localDateTime = (value) => (value ? `${jakartaDate(value)}T${jakartaTime(value)}` : '')
const dateTime = (value) =>
  value
    ? new Intl.DateTimeFormat('id-ID', {
        dateStyle: 'medium',
        timeStyle: 'short',
        timeZone: 'Asia/Jakarta',
      }).format(new Date(value)) + ' WIB'
    : 'Belum dijadwalkan'
const formatLabels = {
  single_elimination: 'Single Elimination',
  double_elimination: 'Double Elimination',
  round_robin: 'Round Robin · Setengah Kompetisi',
  round_robin_full: 'Round Robin · Kompetisi Penuh',
  groups_knockout: 'Grup → Knockout',
}

function MatchEditor({ match, reload }) {
  const locked = false
  const [scoreA, setScoreA] = useState(match.score_a ?? ''),
    [scoreB, setScoreB] = useState(match.score_b ?? ''),
    [scheduled, setScheduled] = useState(localDateTime(match.scheduled_at)),
    [venue, setVenue] = useState(match.venue || ''),
    [busy, setBusy] = useState(false)
  const save = async (status) => {
    setBusy(true)
    try {
      await api(`/manage/tournaments/matches/${match.id}`, {
        method: 'PUT',
        body: JSON.stringify({
          score_a: scoreA === '' ? null : Number(scoreA),
          score_b: scoreB === '' ? null : Number(scoreB),
          scheduled_at: scheduled || null,
          venue,
          status,
        }),
      })
      reload()
    } catch (e) {
      alert(e.message)
    } finally {
      setBusy(false)
    }
  }
  return (
    <article className="rounded-2xl border bg-white p-4">
      <div className="flex items-center justify-between">
        <b className="text-xs text-blue-600">
          MATCH {String(match.match_number).padStart(2, '0')} · {match.round_label}
        </b>
        <span className="rounded-full bg-slate-100 px-2 py-1 text-[11px] font-bold">{match.status}</span>
      </div>
      <div className="mt-3 grid grid-cols-[1fr_70px] items-center gap-2 text-sm">
        <span className={match.winner_id === match.participant_a_id ? 'font-black text-emerald-700' : 'font-bold'}>{nameOf(match.participant_a)}</span>
        <input className="input py-2 text-center" type="number" min="0" value={scoreA} onChange={(e) => setScoreA(e.target.value)} disabled={locked || !match.participant_a_id} />
        <span className={match.winner_id === match.participant_b_id ? 'font-black text-emerald-700' : 'font-bold'}>{nameOf(match.participant_b)}</span>
        <input className="input py-2 text-center" type="number" min="0" value={scoreB} onChange={(e) => setScoreB(e.target.value)} disabled={locked || !match.participant_b_id} />
      </div>
      <div className="mt-3 grid gap-2 sm:grid-cols-2">
        <label>
          <span className="label">Waktu (WIB)</span>
          <input className="input py-2 text-xs" type="datetime-local" value={scheduled} onChange={(e) => setScheduled(e.target.value)} disabled={locked} />
        </label>
        <input className="input py-2 text-xs" value={venue} onChange={(e) => setVenue(e.target.value)} placeholder="Lapangan / lokasi" disabled={locked} />
      </div>
      {!locked && (
        <div className="mt-3 flex justify-end gap-2">
          <button className="btn-ghost py-2 text-xs" disabled={busy} onClick={() => save('ongoing')}>
            Berlangsung
          </button>
          <button className="btn-dark py-2 text-xs" disabled={busy || !match.participant_a_id || !match.participant_b_id} onClick={() => save('completed')}>
            Konfirmasi Skor
          </button>
        </div>
      )}
    </article>
  )
}

function GroupStandings({ groups }) {
  if (!groups?.length) return null
  return (
    <section className="mt-6 rounded-3xl bg-white p-5 sm:p-7">
      <div className="flex flex-wrap items-end justify-between gap-3">
        <div>
          <div className="text-xs font-black uppercase tracking-[.16em] text-emerald-700">Hasil Group Stage</div>
          <h2 className="mt-1 font-display text-2xl font-bold">Klasemen Grup</h2>
        </div>
        <p className="text-xs text-slate-500">Urutan: poin, selisih gol, gol memasukkan, lalu jumlah kemenangan.</p>
      </div>
      <div className="mt-5 grid gap-5 xl:grid-cols-2">
        {groups.map((group) => (
          <article key={group.name} className="overflow-hidden rounded-2xl border">
            <div className="flex items-center justify-between bg-slate-50 px-4 py-3">
              <b className="font-display">{group.name}</b>
              <span className={`rounded-full px-2 py-1 text-[10px] font-black ${group.completed ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'}`}>{group.completed ? 'Final' : `${group.played_matches}/${group.total_matches} laga`}</span>
            </div>
            <div className="overflow-x-auto">
              <table className="w-full min-w-[620px] text-xs">
              <thead className="bg-ink text-[#fff]">
                  <tr>
                    <th className="px-3 py-2 text-center">#</th>
                    <th className="px-3 py-2 text-left">Tim</th>
                    <th title="Main" className="px-2 py-2">
                      MP
                    </th>
                    <th title="Menang" className="px-2 py-2">
                      M
                    </th>
                    <th title="Seri" className="px-2 py-2">
                      S
                    </th>
                    <th title="Kalah" className="px-2 py-2">
                      K
                    </th>
                    <th title="Gol memasukkan" className="px-2 py-2">
                      GM
                    </th>
                    <th title="Gol kemasukan" className="px-2 py-2">
                      GK
                    </th>
                    <th title="Selisih gol" className="px-2 py-2">
                      SG
                    </th>
                    <th className="px-3 py-2">Poin</th>
                  </tr>
                </thead>
                <tbody>
                  {group.rows.map((row) => (
                    <tr key={row.registration_id} className={`border-t ${row.qualified ? 'bg-emerald-50' : ''}`}>
                      <td className="px-3 py-3 text-center font-black">{row.position}</td>
                      <td className="px-3 py-3">
                        <b>{nameOf(row.participant)}</b>
                        {row.qualified && <span className="ml-2 rounded-full bg-emerald-600 px-2 py-1 text-[9px] font-black text-white">LOLOS</span>}
                        <span className="mt-1 block text-[10px] text-slate-400">{row.participant?.school_name}</span>
                      </td>
                      <td className="px-2 py-3 text-center">{row.played}</td>
                      <td className="px-2 py-3 text-center">{row.won}</td>
                      <td className="px-2 py-3 text-center">{row.drawn}</td>
                      <td className="px-2 py-3 text-center">{row.lost}</td>
                      <td className="px-2 py-3 text-center">{row.goals_for}</td>
                      <td className="px-2 py-3 text-center">{row.goals_against}</td>
                      <td className="px-2 py-3 text-center">{row.goal_difference}</td>
                      <td className="px-3 py-3 text-center font-black">{row.points}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </article>
        ))}
      </div>
    </section>
  )
}

export function TournamentManager() {
  const [data, setData] = useState(null),
    [mode, setMode] = useState('random'),
    [format, setFormat] = useState('single_elimination'),
    [manual, setManual] = useState([]),
    [seeds, setSeeds] = useState([]),
    [hosts, setHosts] = useState([]),
    [rules, setRules] = useState({
      avoid_same_school: true,
      separate_seeds: true,
      host_policy: 'random',
      group_count: 2,
      third_place: true,
    }),
    [reveal, setReveal] = useState(999),
    [dragged, setDragged] = useState(null),
    [busy, setBusy] = useState(false)
  const load = (id) =>
    api(`/manage/tournaments${id ? `?competition_id=${id}` : ''}`).then((value) => {
      setData(value)
      if (!manual.length || value.competition?.id !== data?.competition?.id) setManual(value.participants.map((x) => x.id))
    })
  useEffect(() => {
    api('/manage/tournaments').then((value) => {
      setData(value)
      setManual(value.participants.map((x) => x.id))
    })
  }, [])
  useEffect(() => {
    if (!data?.draw || reveal >= data.draw.entries.length) return
    const timer = setTimeout(() => setReveal((x) => x + 1), 260)
    return () => clearTimeout(timer)
  }, [data?.draw, reveal])
  if (!data) return <div className="p-10 text-center font-bold">Memuat drawing...</div>
  if (!data.competition) return <div className="rounded-3xl bg-white p-10 text-center">Belum ada lomba yang dapat dikelola.</div>
  const start = async () => {
    setBusy(true)
    try {
      const draw = await api(`/manage/tournaments/competitions/${data.competition.id}/draw`, {
        method: 'POST',
        body: JSON.stringify({
          mode,
          format,
          manual_order: mode === 'manual' ? manual : undefined,
          seeded_ids: seeds,
          host_ids: hosts,
          ...rules,
          group_count: Number(rules.group_count),
        }),
      })
      setData({ ...data, draw })
      setReveal(0)
      await load(data.competition.id)
    } catch (e) {
      alert(e.message)
    } finally {
      setBusy(false)
    }
  }
  const lock = async () => {
    if (!confirm('Kunci drawing? Drawing ulang dan perubahan bagan tidak dapat dilakukan setelah dikunci.')) return
    try {
      await api(`/manage/tournaments/draws/${data.draw.id}/lock`, {
        method: 'POST',
      })
      load(data.competition.id)
    } catch (e) {
      alert(e.message)
    }
  }
  const download = () => {
    const blob = new Blob([JSON.stringify(data.draw, null, 2)], {
        type: 'application/json',
      }),
      url = URL.createObjectURL(blob),
      anchor = document.createElement('a')
    anchor.href = url
    anchor.download = `drawing-${data.competition.slug}-v${data.draw.version}.json`
    anchor.click()
    URL.revokeObjectURL(url)
  }
  const toggle = (setter, values, id) => setter(values.includes(id) ? values.filter((x) => x !== id) : [...values, id])
  const drop = (id) => {
    if (dragged === null || dragged === id) return
    const next = [...manual],
      from = next.indexOf(dragged),
      to = next.indexOf(id)
    next.splice(from, 1)
    next.splice(to, 0, dragged)
    setManual(next)
    setDragged(null)
  }
  const participantsById = Object.fromEntries(data.participants.map((x) => [x.id, x]))
  const locked = data.draw?.status === 'locked'
  return (
    <div className="print:bg-white">
      <div className="mb-7 flex flex-wrap items-end justify-between gap-4">
        <div>
          <div className="text-xs font-black uppercase tracking-[.18em] text-blue-600">Tournament desk</div>
          <h1 className="mt-2 font-display text-3xl font-bold">Drawing & Bagan</h1>
        </div>
        <select
          className="input w-72"
          value={data.competition.id}
          onChange={(e) => {
            setManual([])
            load(e.target.value)
          }}
        >
          {data.competitions.map((item) => (
            <option key={item.id} value={item.id}>
              {item.title}
            </option>
          ))}
        </select>
      </div>
      <section className="rounded-3xl bg-ink p-6 text-white">
        <ShieldCheck className="text-electric" />
        <p className="mt-4 max-w-3xl leading-7 text-slate-300">Drawing akan dilakukan berdasarkan peserta yang telah diverifikasi. Hasil drawing bersifat final setelah dikunci oleh panitia.</p>
        <div className="mt-5 text-sm font-bold text-electric">{data.participants.length} peserta terverifikasi</div>
      </section>
      {!locked && (
        <section className="mt-6 rounded-3xl bg-white p-5 sm:p-7">
          <h2 className="font-display text-xl font-bold">1. Pilih Mode Drawing</h2>
          <div className="mt-4 grid gap-3 md:grid-cols-3">
            {[
              ['random', 'Random Drawing', 'Seluruh peserta diacak otomatis.'],
              ['seeded', 'Seeded Drawing', 'Peserta unggulan ditempatkan terpisah.'],
              ['manual', 'Manual Drawing', 'Susun peserta dengan drag-and-drop.'],
            ].map(([value, label, text]) => (
              <button key={value} onClick={() => setMode(value)} className={`rounded-2xl border-2 p-4 text-left ${mode === value ? 'border-ink bg-electric' : 'border-slate-200'}`}>
                <b>{label}</b>
                <p className="mt-1 text-xs text-slate-500">{text}</p>
              </button>
            ))}
          </div>
          <div className="mt-6 grid gap-5 lg:grid-cols-2">
            <div>
              <label className="label">Format Kompetisi</label>
              <select className="input" value={format} onChange={(e) => setFormat(e.target.value)}>
                {Object.entries(formatLabels).map(([value, label]) => (
                  <option key={value} value={value}>
                    {label}
                  </option>
                ))}
              </select>
            </div>
            {format === 'groups_knockout' && (
              <div>
                <label className="label">Jumlah Grup</label>
                <input className="input" type="number" min="2" max="16" value={rules.group_count} onChange={(e) => setRules({ ...rules, group_count: e.target.value })} />
              </div>
            )}
          </div>
          <h3 className="mt-6 font-display font-bold">Aturan Drawing</h3>
          <div className="mt-3 grid gap-3 sm:grid-cols-2">
            <label className="rounded-xl bg-slate-50 p-3 text-sm font-bold">
              <input className="mr-2" type="checkbox" checked={rules.avoid_same_school} onChange={(e) => setRules({ ...rules, avoid_same_school: e.target.checked })} />
              Pisahkan sekolah/klub di babak pertama
            </label>
            <label className="rounded-xl bg-slate-50 p-3 text-sm font-bold">
              <input className="mr-2" type="checkbox" checked={rules.separate_seeds} onChange={(e) => setRules({ ...rules, separate_seeds: e.target.checked })} />
              Pisahkan peserta unggulan
            </label>
            <label className="rounded-xl bg-slate-50 p-3 text-sm font-bold">
              <input className="mr-2" type="checkbox" checked={rules.third_place} onChange={(e) => setRules({ ...rules, third_place: e.target.checked })} />
              Perebutan juara ketiga
            </label>
            <select className="input" value={rules.host_policy} onChange={(e) => setRules({ ...rules, host_policy: e.target.value })}>
              <option value="random">Tuan rumah: acak</option>
              <option value="first">Tuan rumah: posisi awal</option>
              <option value="last">Tuan rumah: posisi akhir</option>
            </select>
          </div>
          <h3 className="mt-6 font-display font-bold">2. Peserta</h3>
          <div className="mt-3 grid gap-2">
            {(mode === 'manual' ? manual : data.participants.map((x) => x.id)).map((id, index) => {
              const participant = participantsById[id]
              return (
                <div key={id} draggable={mode === 'manual'} onDragStart={() => setDragged(id)} onDragOver={(e) => e.preventDefault()} onDrop={() => drop(id)} className="flex items-center gap-3 rounded-xl border bg-white p-3">
                  <span className="grid size-8 place-items-center rounded-full bg-ink text-xs font-bold text-white">{index + 1}</span>
                  {mode === 'manual' && <GripVertical className="cursor-grab text-slate-400" size={17} />}
                  <div className="min-w-0 flex-1">
                    <b className="block truncate">{nameOf(participant)}</b>
                    <span className="text-xs text-slate-400">{participant.school_name}</span>
                  </div>
                  {mode === 'seeded' && (
                    <label className="text-xs font-bold">
                      <input type="checkbox" checked={seeds.includes(id)} onChange={() => toggle(setSeeds, seeds, id)} className="mr-1" />
                      Unggulan
                    </label>
                  )}
                  <label className="text-xs font-bold">
                    <input type="checkbox" checked={hosts.includes(id)} onChange={() => toggle(setHosts, hosts, id)} className="mr-1" />
                    Tuan rumah
                  </label>
                </div>
              )
            })}
          </div>
          <button className="btn-primary mt-6 w-full py-4" onClick={start} disabled={busy || data.participants.length < 2}>
            <Play size={18} />
            {busy ? 'Mengundi...' : 'Mulai Drawing'}
          </button>
        </section>
      )}
      {data.draw && (
        <>
          <section className="mt-6 rounded-3xl bg-white p-5 sm:p-7">
            <div className="flex flex-wrap items-start justify-between gap-4">
              <div>
                <div className="text-xs font-bold uppercase tracking-wider text-blue-600">Hasil Drawing · Versi {data.draw.version}</div>
                <h2 className="mt-1 font-display text-2xl font-bold">{formatLabels[data.draw.format]}</h2>
                <p className="mt-1 text-xs text-slate-400">
                  {dateTime(data.draw.drawn_at)} · Operator {data.draw.operator?.name}
                </p>
              </div>
              <div className="flex flex-wrap gap-2">
                <button className="btn-ghost" onClick={download}>
                  <Download size={15} />
                  Unduh
                </button>
                <button className="btn-ghost" onClick={() => window.print()}>
                  <Printer size={15} />
                  Cetak
                </button>
                {!locked && (
                  <button className="btn-dark" onClick={lock}>
                    <LockKeyhole size={15} />
                    Kunci Drawing
                  </button>
                )}
              </div>
            </div>
            <div className="mt-6 grid gap-2 sm:grid-cols-2">
              {data.draw.entries.slice(0, reveal).map((entry) => (
                <div key={entry.id} className="animate-pulse rounded-xl border p-3">
                  <span className="mr-3 inline-grid size-7 place-items-center rounded-full bg-blue-600 text-xs font-bold text-white">{entry.slot_number}</span>
                  <b>{entry.is_bye ? 'BYE' : nameOf(entry.registration)}</b>
                  <span className="ml-2 text-xs text-slate-400">{entry.group_name || entry.registration?.school_name}</span>
                </div>
              ))}
            </div>
          </section>
          <GroupStandings groups={data.draw.group_standings} />
          <section className="mt-6">
            <div className="mb-4 flex items-center justify-between">
              <h2 className="font-display text-2xl font-bold">Bagan Pertandingan</h2>
              {data.draw.format === 'groups_knockout' && !data.draw.matches.some((x) => x.stage === 'knockout') && (
                <button
                  className="btn-dark"
                  onClick={async () => {
                    try {
                      await api(`/manage/tournaments/draws/${data.draw.id}/knockout`, { method: 'POST' })
                      load(data.competition.id)
                    } catch (e) {
                      alert(e.message)
                    }
                  }}
                >
                  Buat Babak Knockout
                </button>
              )}
            </div>
            <div className="grid gap-5 xl:grid-cols-2">
              {data.draw.matches.map((match) => (
                <MatchEditor key={match.id} match={match} reload={() => load(data.competition.id)} locked={locked} />
              ))}
            </div>
          </section>
          <section className="mt-6 rounded-3xl bg-white p-5">
            <h2 className="font-display font-bold">Riwayat Drawing Ulang</h2>
            <div className="mt-3 flex flex-wrap gap-2">
              {data.history.map((item) => (
                <span key={item.id} className="rounded-full bg-slate-100 px-3 py-2 text-xs font-bold">
                  Versi {item.version} · {item.mode} · {dateTime(item.drawn_at)} · {item.status}
                </span>
              ))}
            </div>
          </section>
        </>
      )}
    </div>
  )
}

function PublicMatch({ match }) {
  return (
    <article className="min-w-[260px] rounded-2xl border bg-white p-4 shadow-sm">
      <div className="text-xs font-black uppercase tracking-wider text-blue-600">
        MATCH {String(match.match_number).padStart(2, '0')} — {match.round_label}
      </div>
      <div className="mt-4 grid grid-cols-[1fr_auto] gap-2">
        <div>
          <b>{nameOf(match.participant_a)}</b>
          <p className="text-xs text-slate-400">{match.participant_a?.school_name}</p>
        </div>
        <b>{match.score_a ?? '-'}</b>
        <div>
          <b>{nameOf(match.participant_b)}</b>
          <p className="text-xs text-slate-400">{match.participant_b?.school_name}</p>
        </div>
        <b>{match.score_b ?? '-'}</b>
      </div>
      <div className="mt-4 border-t pt-3 text-xs text-slate-500">
        <p>{match.venue || 'Lokasi belum ditentukan'}</p>
        <p>{dateTime(match.scheduled_at)}</p>
        <p className="mt-1 font-bold">Status: {match.status}</p>
      </div>
    </article>
  )
}

export function TournamentPublic() {
  const { slug } = useParams(),
    [data, setData] = useState(null)
  const loadSession = useCallback(sessionId => api(`/competitions/${slug}/tournament${sessionId ? `?session_id=${sessionId}` : ''}`).then(setData), [slug])
  useEffect(() => {
    loadSession()
  }, [loadSession])
  if (!data) return <div className="min-h-96 p-20 text-center font-bold">Memuat bagan...</div>
  const sessionPicker = data.sessions?.length > 0 && <label className="mt-6 block w-full max-w-sm rounded-2xl border bg-white p-4 text-left shadow-sm"><span className="label">Pilih Kota Pelaksanaan</span><select aria-label="Pilih kota bagan" className="input" value={data.session?.id || ''} onChange={event => loadSession(event.target.value)}>{data.sessions.map(session => <option key={session.id} value={session.id}>{session.city} · {session.venue}</option>)}</select><span className="mt-2 block text-xs text-slate-500">Bagan, klasemen, dan hasil pertandingan mengikuti kota yang dipilih.</span></label>
  if (!data.draw)
    return (
      <section className="min-h-[60vh] px-5 py-16">
        <div className="mx-auto max-w-3xl rounded-3xl bg-white p-10 text-center">
          <Trophy className="mx-auto text-blue-600" />
          <h1 className="mt-4 font-display text-3xl font-bold">Bagan belum dipublikasikan</h1>
          <p className="mt-2 text-slate-500">{data.session?.city ? `${data.session.city}: ` : ''}Drawing akan tampil setelah disahkan dan dikunci panitia.</p>
          {sessionPicker}
          <Link to={`/lomba/${slug}`} className="btn-dark mt-6">
            Kembali ke Lomba
          </Link>
        </div>
      </section>
    )
  const sections = Object.entries(
    data.draw.matches.reduce((groups, match) => {
      const key = `${match.stage}-${match.round_label}`
      ;(groups[key] ??= []).push(match)
      return groups
    }, {}),
  )
  return (
    <section className="min-h-screen bg-slate-50 px-5 py-12">
      <div className="mx-auto max-w-7xl">
        <Link to={`/lomba/${slug}`} className="text-sm font-bold text-slate-500">
          ← Kembali ke detail lomba
        </Link>
        <div className="mt-6 flex flex-wrap items-end justify-between gap-4">
          <div>
            <div className="text-xs font-black uppercase tracking-[.18em] text-blue-600">Drawing Resmi · Versi {data.draw.version}</div>
            <h1 className="mt-2 font-display text-4xl font-bold">{data.competition.title}</h1>
            {data.session && <p className="mt-2 font-bold text-blue-700">{data.session.city} · {data.session.venue}</p>}
            <p className="mt-2 text-sm text-slate-500">
              {formatLabels[data.draw.format]} · {dateTime(data.draw.locked_at)} · Operator {data.draw.operator?.name}
            </p>
          </div>
          {['single_elimination', 'groups_knockout'].includes(data.draw.format) ? <Link className="btn-ghost" target="_blank" to={`/lomba/${slug}/bagan/cetak${data.session?.id ? `?session_id=${data.session.id}` : ''}`}>
            <Printer size={16} />
            Cetak Bagan
          </Link> : <button className="btn-ghost" onClick={() => window.print()}>
            <Printer size={16} />
            Cetak Bagan
          </button>}
        </div>
        {sessionPicker}
        <GroupStandings groups={data.draw.group_standings} />
        <div className="mt-8 overflow-x-auto pb-6">
          <div className="flex min-w-max items-start gap-6">
            {sections.map(([key, matches]) => (
              <div key={key} className="w-[280px]">
                <h2 className="mb-4 font-display font-bold">{matches[0].round_label}</h2>
                <div className="grid gap-5">
                  {matches.map((match) => (
                    <PublicMatch key={match.id} match={match} />
                  ))}
                </div>
              </div>
            ))}
          </div>
        </div>
      </div>
    </section>
  )
}
