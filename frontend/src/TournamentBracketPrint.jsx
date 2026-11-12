import { useEffect, useMemo, useState } from 'react'
import { ArrowLeft, Printer } from 'lucide-react'
import { useLocation, useNavigate, useParams } from 'react-router-dom'
import { api, STORAGE } from './api'
import './tournament-bracket-print.css'

const nameOf = participant => participant?.team_name || participant?.full_name || ''
const scoreOf = score => score === null || score === undefined ? '' : score
const logoOf = participant => participant?.school_logo_path ? `${STORAGE}/${participant.school_logo_path}` : null

function PrintTeam({participant, name, align = 'left'}) {
  const logo = logoOf(participant)
  return <span className={`print-bracket-team ${align === 'right' ? 'right' : ''}`} title={name}>
    {logo && <img src={logo} alt="" onError={event => { event.currentTarget.style.display = 'none' }}/>}<em>{name}</em>
  </span>
}

function printableRounds(draw) {
  if (!draw?.matches) return []
  const stage = draw.format === 'groups_knockout' ? 'knockout' : 'main'
  const matches = draw.matches.filter(match => match.stage === stage)
  return Object.values(matches.reduce((groups, match) => {
    ;(groups[match.round_number] ??= []).push(match)
    return groups
  }, {})).map(round => round.sort((a, b) => a.match_number - b.match_number))
}

function splitRounds(allRounds) {
  if (!allRounds || allRounds.length === 0) return { top: [], bottom: [] }
  const top = []
  const bottom = []
  for (let r = 0; r < allRounds.length; r++) {
    const round = allRounds[r]
    if (round.length > 1) {
      const half = Math.ceil(round.length / 2)
      top.push(round.slice(0, half))
      bottom.push(round.slice(half))
    } else {
      // Final round dimasukkan ke kedua lembar agar cabang bagan bermuara jelas
      top.push(round)
      bottom.push(round)
    }
  }
  return { top, bottom }
}

function computeLayout(rounds) {
  if (!rounds || !rounds.length) return null
  const cardWidth = 196, cardHeight = 56, columnStep = 244
  const rowStep = Math.min(96, Math.max(68, 560 / Math.max(1, rounds[0].length)))

  const centers = []
  for (let r = 0; r < rounds.length; r++) {
    // Jika babak ini 1 match dan babak sebelumnya juga 1 match (misal Final menyambung Semifinal di sub-bagan)
    if (r > 0 && rounds[r].length === 1 && rounds[r - 1].length === 1) {
      centers.push([centers[r - 1][0]])
    } else {
      centers.push(rounds[r].map((_, m) => (m + 0.5) * rowStep * (2 ** r)))
    }
  }

  const width = (rounds.length - 1) * columnStep + cardWidth
  const height = Math.max(rowStep * rounds[0].length, (centers.at(-1)?.[0] || 0) + cardHeight)

  const paths = []
  rounds.slice(0, -1).forEach((round, roundIndex) => {
    round.forEach((match, matchIndex) => {
      const nextRound = rounds[roundIndex + 1]
      let targetIndex = nextRound.findIndex(next => next.source_a_match_id === match.id || next.source_b_match_id === match.id)
      if (targetIndex < 0) targetIndex = Math.min(Math.floor(matchIndex / 2), nextRound.length - 1)
      const x1 = roundIndex * columnStep + cardWidth
      const x2 = (roundIndex + 1) * columnStep
      const y1 = centers[roundIndex][matchIndex]
      const y2 = centers[roundIndex + 1][targetIndex]
      const middle = x1 + (x2 - x1) / 2
      paths.push(`M ${x1} ${y1} H ${middle} V ${y2} H ${x2}`)
    })
  })

  return { cardHeight, columnStep, centers, width, height, paths }
}

function slotName(match, side, roundIndex, matchesById) {
  const participant = side === 'a' ? match.participant_a : match.participant_b
  if (participant) return nameOf(participant)
  const sourceId = side === 'a' ? match.source_a_match_id : match.source_b_match_id
  const source = matchesById[sourceId]
  if (source) return `Pemenang Match ${source.match_number}`
  return roundIndex === 0 ? 'BYE' : 'Menunggu pemenang'
}

function BracketMatch({match, roundIndex, style, matchesById}) {
  const firstName = slotName(match, 'a', roundIndex, matchesById)
  const secondName = slotName(match, 'b', roundIndex, matchesById)
  return <article className="print-bracket-match" style={style}>
    <div className="print-bracket-match-label">{match.round_label} · Game {match.match_number}</div>
    <div className={match.winner_id && match.winner_id === match.participant_a_id ? 'winner' : ''}>
      <PrintTeam participant={match.participant_a} name={firstName}/><b>{scoreOf(match.score_a)}</b>
    </div>
    <div className={match.winner_id && match.winner_id === match.participant_b_id ? 'winner' : ''}>
      <PrintTeam participant={match.participant_b} name={secondName}/><b>{scoreOf(match.score_b)}</b>
    </div>
  </article>
}

function PrintHeader({data, section}) {
  const format = data.draw.format === 'groups_knockout' ? 'Group Stage → Knockout' : 'Single Elimination'
  return <header className="bracket-print-header">
    <img src="/bsi-flash-logo.png" alt="BSI Flash"/>
    <div><span>{section}</span><h1>{data.competition.title}</h1><p>{format} · {data.session ? `${data.session.city} · ${data.session.venue}` : 'Seluruh lokasi'}</p></div>
    <aside><span>VERSI DRAWING</span><b>V{data.draw.version}</b><small>{data.draw.status === 'locked' ? 'TERKUNCI' : 'DRAFT'}</small></aside>
  </header>
}

function PrintFooter({printedAt, label}) {
  return <footer><span>Dicetak {printedAt}</span><span>BSI Flash · {label}</span></footer>
}

function GroupStageSheets({data, printedAt}) {
  const groups = data.draw.group_standings || []
  const groupMatches = data.draw.matches.filter(match => match.stage === 'group')
  const pages = Array.from({length: Math.ceil(groups.length / 4)}, (_, index) => groups.slice(index * 4, index * 4 + 4))
  return pages.map((pageGroups, pageIndex) => <article className="bracket-print-sheet bracket-group-sheet" key={pageIndex}>
    <PrintHeader data={data} section={`Babak Grup${pages.length > 1 ? ` · Halaman ${pageIndex + 1}/${pages.length}` : ''}`}/>
    <section className="bracket-group-grid">
      {pageGroups.map(group => {
        const matches = groupMatches.filter(match => (match.group_name || match.round_label) === group.name).sort((a, b) => a.match_number - b.match_number)
        return <article className="bracket-group-card" key={group.name}>
          <div className="bracket-group-title"><h2>{group.name}</h2><span>{group.completed ? 'SELESAI' : `${group.played_matches}/${group.total_matches} LAGA`}</span></div>
          <table><thead><tr><th>#</th><th>Tim</th><th>M</th><th>Mn</th><th>S</th><th>K</th><th>SG</th><th>P</th></tr></thead>
            <tbody>{group.rows.map(row => <tr key={row.registration_id} className={row.qualified ? 'qualified' : ''}><td>{row.position}</td><td><PrintTeam participant={row.participant} name={nameOf(row.participant)}/>{row.qualified && <small>LOLOS</small>}</td><td>{row.played}</td><td>{row.won}</td><td>{row.drawn}</td><td>{row.lost}</td><td>{row.goal_difference}</td><td><strong>{row.points}</strong></td></tr>)}</tbody>
          </table>
          <div className="bracket-group-matches"><h3>Pertandingan Grup</h3>{matches.map(match => <div key={match.id}>
            <PrintTeam participant={match.participant_a} name={nameOf(match.participant_a) || 'Menunggu tim'}/><b>{match.score_a ?? '–'}</b><i>vs</i><b>{match.score_b ?? '–'}</b><PrintTeam participant={match.participant_b} name={nameOf(match.participant_b) || 'Menunggu tim'} align="right"/>
          </div>)}</div>
        </article>
      })}
    </section>
    <PrintFooter printedAt={printedAt} label="Klasemen dan pertandingan babak grup"/>
  </article>)
}

function KnockoutSheet({
  data,
  rounds,
  section,
  footerLabel,
  printedAt,
  orientation,
  autoFit,
  customScale,
  thirdPlace = null,
  matchesById,
}) {
  const layout = useMemo(() => computeLayout(rounds), [rounds])

  const treeWidth = layout?.width || 0
  const hasThirdPlace = Boolean(thirdPlace)
  const treeHeight = layout ? 34 + layout.height + (hasThirdPlace ? 45 : 0) : 0

  // Estimasi ruang kanvas usable di dalam sheet A4 (1mm = 3.78px)
  // Landscape: 297mm x 210mm (1122.5px x 793.7px) -> usable ~ 1070px x 635px
  // Portrait:  210mm x 297mm (793.7px x 1122.5px) -> usable ~ 740px x 960px
  const availWidth = orientation === 'landscape' ? 1070 : 740
  const availHeight = orientation === 'landscape' ? (hasThirdPlace ? 600 : 635) : (hasThirdPlace ? 925 : 960)

  const autoScale = useMemo(() => {
    if (!treeWidth || !treeHeight) return 1
    const s = Math.min(availWidth / treeWidth, availHeight / treeHeight, 1)
    return Math.max(0.15, Math.floor(s * 1000) / 1000)
  }, [treeWidth, treeHeight, availWidth, availHeight])

  const currentScale = autoFit || customScale === null ? autoScale : customScale

  return (
    <article className="bracket-print-sheet bracket-knockout-sheet">
      <PrintHeader data={data} section={section} />
      {layout ? (
        <div className="bracket-scalable-area" style={{ overflow: autoFit ? 'hidden' : 'auto' }}>
          <div
            className="bracket-scaled-box"
            style={{
              width: Math.round(treeWidth * currentScale),
              height: Math.round(treeHeight * currentScale),
            }}
          >
            <div
              className="bracket-tree-canvas"
              style={{
                width: treeWidth,
                height: treeHeight,
                transform: `scale(${currentScale})`,
                transformOrigin: 'top left',
              }}
            >
              <div className="bracket-round-headings" style={{ width: layout.width }}>
                {rounds.map((round, index) => (
                  <div key={round[0].round_number || index} style={{ left: index * layout.columnStep }}>
                    {round[0].round_label}
                  </div>
                ))}
              </div>
              <section className="print-bracket-canvas" style={{ width: layout.width, height: layout.height }}>
                <svg aria-hidden="true" viewBox={`0 0 ${layout.width} ${layout.height}`} preserveAspectRatio="none">
                  {layout.paths.map((path, index) => (
                    <path key={index} d={path} />
                  ))}
                </svg>
                {rounds.flatMap((round, roundIndex) =>
                  round.map((match, matchIndex) => (
                    <BracketMatch
                      key={match.id}
                      match={match}
                      roundIndex={roundIndex}
                      matchesById={matchesById}
                      style={{
                        left: roundIndex * layout.columnStep,
                        top: layout.centers[roundIndex][matchIndex] - layout.cardHeight / 2,
                      }}
                    />
                  ))
                )}
              </section>
              {thirdPlace && (
                <section className="bracket-third-place">
                  <span>Perebutan Juara Ketiga</span>
                  <div>
                    <b>{slotName(thirdPlace, 'a', 1, matchesById)}</b>
                    <strong>{scoreOf(thirdPlace.score_a)}</strong>
                    <b>{slotName(thirdPlace, 'b', 1, matchesById)}</b>
                    <strong>{scoreOf(thirdPlace.score_b)}</strong>
                  </div>
                </section>
              )}
            </div>
          </div>
        </div>
      ) : (
        <section className="bracket-knockout-empty">
          <b>Babak knockout belum dibuat</b>
          <p>Selesaikan seluruh pertandingan grup, lalu buat babak knockout dari panel drawing.</p>
        </section>
      )}
      <PrintFooter printedAt={printedAt} label={footerLabel} />
    </article>
  )
}

export function TournamentBracketPrint({managed = false}) {
  const params = useParams(), location = useLocation(), navigate = useNavigate()
  const [data, setData] = useState(null), [error, setError] = useState('')
  const [orientation, setOrientation] = useState('landscape')
  const [pageMode, setPageMode] = useState('single') // 'single' atau 'split' (2 lembar)
  const [autoFit, setAutoFit] = useState(true)
  const [customScale, setCustomScale] = useState(null)

  useEffect(() => {
    const query = new URLSearchParams(location.search)
    let endpoint
    if (managed) {
      const managerQuery = new URLSearchParams({competition_id: params.competitionId})
      if (Number(params.sessionId)) managerQuery.set('session_id', params.sessionId)
      endpoint = `/manage/tournaments?${managerQuery}`
    } else {
      endpoint = `/competitions/${params.slug}/tournament${query.get('session_id') ? `?session_id=${query.get('session_id')}` : ''}`
    }
    api(endpoint).then(result => {
      setData(result)
      const r = printableRounds(result?.draw)
      // Jika pertandingan awal >= 16 laga (misal 32/64 slot bagan), otomatis gunakan mode 2 lembar agar proporsional dan mudah dibaca
      if (r[0]?.length >= 16) {
        setPageMode('split')
      }
    }).catch(requestError => setError(requestError.message))
  }, [location.search, managed, params.competitionId, params.sessionId, params.slug])

  const rounds = useMemo(() => printableRounds(data?.draw), [data?.draw])
  const canSplit = useMemo(() => (rounds[0]?.length || 0) >= 8, [rounds])
  const splitData = useMemo(() => splitRounds(rounds), [rounds])

  const thirdPlace = data?.draw?.matches?.find(match => match.stage === 'third_place')
  const matchesById = useMemo(() => Object.fromEntries(data?.draw?.matches?.map(match => [match.id, match]) || []), [data?.draw?.matches])
  const printedAt = useMemo(() => new Intl.DateTimeFormat('id-ID', {dateStyle: 'long', timeZone: 'Asia/Jakarta'}).format(new Date()), [])

  if (error) return <main className="bracket-print-state"><div><h1>Bagan tidak dapat dibuka</h1><p>{error}</p><button onClick={() => navigate(-1)}>Kembali</button></div></main>
  if (!data) return <main className="bracket-print-state"><div><h1>Menyiapkan bagan…</h1><p>Data pertandingan sedang dimuat.</p></div></main>
  const supported = ['single_elimination', 'groups_knockout'].includes(data.draw?.format)
  if (!data.draw || !supported) return <main className="bracket-print-state"><div><h1>Bagan belum siap dicetak</h1><p>Fitur ini tersedia untuk Single Elimination dan Group Stage → Knockout.</p><button onClick={() => navigate(-1)}>Kembali</button></div></main>

  return <main className={`bracket-print-page is-${orientation}`}>
    <style>{`@media print { @page { size: A4 ${orientation}; margin: 0; } }`}</style>
    <div className="bracket-print-toolbar">
      <button type="button" className="btn-back" onClick={() => window.close()}><ArrowLeft/>Tutup</button>
      
      <div className="bracket-print-toolbar-info">
        <b>Pratinjau Cetak Bagan</b>
        <span>
          A4 {orientation === 'landscape' ? 'Lanskap' : 'Potret'} · {pageMode === 'split' ? '2 Lembar (Bagan Atas & Bawah)' : '1 Lembar Utuh'} · {autoFit ? 'Skala otomatis proporsional' : `Skala manual ${Math.round((customScale || 1) * 100)}%`}
        </span>
      </div>

      <div className="bracket-toolbar-controls">
        {canSplit && (
          <div className="bracket-pill-group" role="group" aria-label="Format lembar cetak">
            <button
              type="button"
              className={pageMode === 'single' ? 'selected' : ''}
              onClick={() => { setPageMode('single'); setCustomScale(null); }}
            >
              1 Lembar
            </button>
            <button
              type="button"
              className={pageMode === 'split' ? 'selected' : ''}
              onClick={() => { setPageMode('split'); setCustomScale(null); }}
            >
              2 Lembar (Atas & Bawah)
            </button>
          </div>
        )}

        <div className="bracket-pill-group" role="group" aria-label="Orientasi cetak">
          <button
            type="button"
            className={orientation === 'landscape' ? 'selected' : ''}
            onClick={() => setOrientation('landscape')}
          >
            Lanskap
          </button>
          <button
            type="button"
            className={orientation === 'portrait' ? 'selected' : ''}
            onClick={() => setOrientation('portrait')}
          >
            Potret
          </button>
        </div>

        <div className="bracket-pill-group" role="group" aria-label="Skala tampilan bagan">
          <button
            type="button"
            className={autoFit ? 'selected' : ''}
            onClick={() => { setAutoFit(true); setCustomScale(null); }}
          >
            Pas Lembar
          </button>
          <button
            type="button"
            title="Perkecil skala"
            onClick={() => { setAutoFit(false); setCustomScale(s => Math.max(0.2, (s ?? 1) - 0.05)); }}
          >
            –
          </button>
          <button
            type="button"
            title="Perbesar skala"
            onClick={() => { setAutoFit(false); setCustomScale(s => Math.min(2.0, (s ?? 1) + 0.05)); }}
          >
            +
          </button>
          <button
            type="button"
            className={!autoFit && customScale === 1 ? 'selected' : ''}
            onClick={() => { setAutoFit(false); setCustomScale(1); }}
          >
            100%
          </button>
        </div>

        <button type="button" className="primary" onClick={() => window.print()}>
          <Printer/>Cetak / Simpan PDF
        </button>
      </div>
    </div>

    {data.draw.format === 'groups_knockout' && <GroupStageSheets data={data} printedAt={printedAt}/>} 

    {pageMode === 'split' && canSplit ? (
      <>
        <KnockoutSheet
          data={data}
          rounds={splitData.top}
          section={data.draw.format === 'groups_knockout' ? 'Bagan Knockout · Pool Atas' : 'Bagan Pertandingan Resmi · Pool Atas'}
          footerLabel="Pool Atas (Lembar 1 dari 2)"
          printedAt={printedAt}
          orientation={orientation}
          autoFit={autoFit}
          customScale={customScale}
          thirdPlace={null}
          matchesById={matchesById}
        />
        <KnockoutSheet
          data={data}
          rounds={splitData.bottom}
          section={data.draw.format === 'groups_knockout' ? 'Bagan Knockout · Pool Bawah & Final' : 'Bagan Pertandingan Resmi · Pool Bawah & Final'}
          footerLabel="Pool Bawah & Final (Lembar 2 dari 2)"
          printedAt={printedAt}
          orientation={orientation}
          autoFit={autoFit}
          customScale={customScale}
          thirdPlace={thirdPlace}
          matchesById={matchesById}
        />
      </>
    ) : (
      <KnockoutSheet
        data={data}
        rounds={rounds}
        section={data.draw.format === 'groups_knockout' ? 'Bagan Babak Knockout' : 'Bagan Pertandingan Resmi'}
        footerLabel="Bagan pertandingan resmi (1 lembar utuh)"
        printedAt={printedAt}
        orientation={orientation}
        autoFit={autoFit}
        customScale={customScale}
        thirdPlace={thirdPlace}
        matchesById={matchesById}
      />
    )}
  </main>
}
