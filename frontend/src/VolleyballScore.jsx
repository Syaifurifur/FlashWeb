import { normalizeVolleyballSets, volleyballBestOfOptions, volleyballSummary } from './volleyball-score-utils'

export function VolleyballScoreEditor({bestOf, onBestOfChange, scores, onScoresChange, participantA, participantB, chooseFormat = false, disabled = false, scoresDisabled = false}) {
  const rows = normalizeVolleyballSets(bestOf, scores)
  const summary = volleyballSummary(bestOf, rows)
  const changeSet = (index, field, value) => {
    const next = normalizeVolleyballSets(bestOf, rows)
    next[index] = {...next[index], [field]:value}
    onScoresChange(next)
  }

  return <section className="mt-3 rounded-2xl border border-blue-100 bg-blue-50/60 p-4">
    <div className="flex flex-wrap items-end justify-between gap-3">
      <div><span className="label">Format skor voli</span><p className="mt-1 text-xs text-slate-500">Pemenang ditentukan dari jumlah kemenangan set.</p></div>
      {chooseFormat ? <select aria-label="Jumlah set pertandingan" className="input w-full bg-white py-2 sm:w-44" value={bestOf} onChange={event => {onBestOfChange(Number(event.target.value)); onScoresChange(normalizeVolleyballSets(Number(event.target.value), rows))}} disabled={disabled}>{volleyballBestOfOptions.map(value => <option key={value} value={value}>{value === 1 ? '1 Set' : `Best of ${value}`}</option>)}</select> : <span className="rounded-full bg-blue-600 px-3 py-2 text-xs font-black text-white">{bestOf === 1 ? '1 SET' : `BEST OF ${bestOf}`}</span>}
    </div>
    <div className="mt-4 grid grid-cols-[minmax(0,1fr)_70px] items-center gap-2 text-sm"><b>{participantA}</b><strong className="rounded-xl bg-white px-3 py-2 text-center text-lg text-blue-700">{summary.winsA}</strong><b>{participantB}</b><strong className="rounded-xl bg-white px-3 py-2 text-center text-lg text-blue-700">{summary.winsB}</strong></div>
    <div className="mt-4 grid gap-2">{rows.map((set, index) => {
      const previousComplete = rows.slice(0,index).every(item => item.completed)
      const previousDecision = volleyballSummary(bestOf, rows.slice(0,index)).decided
      const editable = !disabled && !scoresDisabled && previousComplete && !previousDecision
      const completable = set.score_a !== '' && set.score_b !== '' && Number(set.score_a) !== Number(set.score_b)
      return <div key={index} className={`grid grid-cols-[54px_1fr_20px_1fr_auto] items-center gap-2 rounded-xl border bg-white p-2 ${editable || set.completed ? '' : 'opacity-45'}`}>
        <b className="text-xs text-slate-500">SET {index + 1}</b>
        <input aria-label={`Skor set ${index + 1} ${participantA}`} className="input px-2 py-2 text-center" type="number" min="0" max="999" value={set.score_a} onChange={event => changeSet(index,'score_a',event.target.value)} disabled={!editable}/>
        <span className="text-center font-bold text-slate-300">–</span>
        <input aria-label={`Skor set ${index + 1} ${participantB}`} className="input px-2 py-2 text-center" type="number" min="0" max="999" value={set.score_b} onChange={event => changeSet(index,'score_b',event.target.value)} disabled={!editable}/>
        <label className="flex items-center gap-2 whitespace-nowrap px-1 text-[11px] font-bold text-slate-600"><input type="checkbox" checked={set.completed} onChange={event => changeSet(index,'completed',event.target.checked)} disabled={!editable || (!set.completed && !completable)}/>Set selesai</label>
      </div>
    })}</div>
    <p className={`mt-3 text-xs font-bold ${summary.decided ? 'text-emerald-700' : 'text-blue-700'}`}>{summary.decided ? `Pertandingan siap diselesaikan · ${summary.winsA}–${summary.winsB} set` : `Target kemenangan: ${summary.requiredWins} set`}</p>
  </section>
}
