import { useEffect, useMemo, useState } from 'react'
import { Award, Check, FileText, Pencil, Plus, Printer, Trash2, UploadCloud, X } from 'lucide-react'
import { Link } from 'react-router-dom'
import { api, STORAGE } from './api'

const rankLabels = {1: 'Juara 1', 2: 'Juara 2', 3: 'Juara 3', 4: 'Juara Harapan'}
const winnerName = result => result?.registration?.team_name || result?.registration?.full_name || 'Belum ditetapkan'

function TemplateModal({item, defaultBody, defaultAwards, placeholders, close, saved}) {
  const [form, setForm] = useState(item || {
    name: 'LOA Beasiswa BSI Flash', scholarship_name: 'Beasiswa Pendidikan BSI',
    body_template: defaultBody, number_pattern: '{{sequence}}/LOA-BEASISWA/BSI-FLASH/{{year}}',
    award_values: defaultAwards, signing_city: 'Jakarta', signatory_name: '', signatory_position: '', is_active: true,
  })
  const [files, setFiles] = useState({}), [busy, setBusy] = useState(false), [error, setError] = useState('')
  const update = (key, value) => setForm(current => ({...current, [key]: value}))
  const submit = async event => {
    event.preventDefault(); setBusy(true); setError('')
    try {
      const body = new FormData()
      ;['name','scholarship_name','body_template','number_pattern','signing_city','signatory_name','signatory_position'].forEach(key => body.append(key, form[key] || ''))
      ;[1,2,3,4].forEach(rank => body.append(`award_rank_${rank}`, form.award_values?.[rank] || defaultAwards?.[rank] || ''))
      body.append('is_active', form.is_active ? '1' : '0')
      Object.entries(files).forEach(([key, file]) => { if (file) body.append(key, file) })
      await api(`/manage/scholarship-loas/templates${item?.id ? `/${item.id}` : ''}`, {method: 'POST', body})
      saved()
    } catch (requestError) { setError(requestError.message) } finally { setBusy(false) }
  }
  return <div className="fixed inset-0 z-[80] overflow-y-auto bg-ink/75 p-3 sm:p-6"><form onSubmit={submit} className="mx-auto my-5 max-w-5xl rounded-[28px] bg-white p-5 sm:p-7">
    <div className="flex items-start justify-between gap-4"><div><div className="text-xs font-black uppercase tracking-wider text-blue-600">Format dokumen</div><h2 className="font-display text-2xl font-bold">{item?.id ? 'Edit template LOA' : 'Tambah template LOA'}</h2></div><button type="button" onClick={close} className="grid size-10 place-items-center rounded-full bg-slate-100"><X/></button></div>
    {error && <p className="mt-4 rounded-xl bg-rose-100 p-3 text-sm font-bold text-rose-700">{error}</p>}
    <div className="mt-6 grid gap-6 lg:grid-cols-[1fr_310px]"><div className="grid gap-4">
      <label><span className="label">Nama template *</span><input className="input" value={form.name} onChange={event => update('name', event.target.value)} required/></label>
      <label><span className="label">Nama program beasiswa *</span><input className="input" value={form.scholarship_name} onChange={event => update('scholarship_name', event.target.value)} required/></label>
      <fieldset className="rounded-2xl border border-blue-100 bg-blue-50/60 p-4"><legend className="px-2 text-sm font-black text-blue-900">Besaran beasiswa per peringkat</legend><p className="mb-4 text-xs leading-5 text-blue-700">Bisa berupa persentase atau nominal, misalnya 100%, 75%, atau Potongan Rp5.000.000.</p><div className="grid gap-3 sm:grid-cols-2">{[1,2,3,4].map(rank => <label key={rank}><span className="label">{rankLabels[rank]} *</span><input className="input bg-white" value={form.award_values?.[rank] || ''} onChange={event => update('award_values', {...(form.award_values || {}), [rank]: event.target.value})} placeholder={defaultAwards?.[rank]} required/></label>)}</div></fieldset>
      <label><span className="label">Pola nomor LOA *</span><input className="input" value={form.number_pattern} onChange={event => update('number_pattern', event.target.value)} required/><small className="mt-1 block text-slate-500">Contoh: {'{{sequence}}/LOA-BEASISWA/BSI-FLASH/{{year}}'}</small></label>
      <label><span className="label">Isi surat dan placeholder *</span><textarea className="input min-h-72 resize-y font-mono text-sm leading-6" value={form.body_template} onChange={event => update('body_template', event.target.value)} required/></label>
      <div><span className="label">Placeholder tersedia</span><div className="mt-2 flex flex-wrap gap-2">{placeholders.map(value => <button type="button" key={value} className="rounded-lg bg-blue-50 px-2 py-1 font-mono text-xs font-bold text-blue-700" onClick={() => update('body_template', `${form.body_template}${form.body_template.endsWith(' ') ? '' : ' '}${value}`)}>{value}</button>)}</div></div>
      <div className="grid gap-4 sm:grid-cols-3"><label><span className="label">Kota penandatanganan</span><input className="input" value={form.signing_city || ''} onChange={event => update('signing_city', event.target.value)}/></label><label><span className="label">Nama penandatangan</span><input className="input" value={form.signatory_name || ''} onChange={event => update('signatory_name', event.target.value)}/></label><label><span className="label">Jabatan</span><input className="input" value={form.signatory_position || ''} onChange={event => update('signatory_position', event.target.value)}/></label></div>
    </div><aside className="grid content-start gap-4">
      {[['reference_file','File contoh LOA sebelumnya','.pdf,.doc,.docx,.jpg,.jpeg,.png,.webp',item?.reference_name],['background_file','Gambar latar/letterhead','.jpg,.jpeg,.png,.webp',item?.background_path],['signature_file','Tanda tangan/stempel','.jpg,.jpeg,.png,.webp',item?.signature_path]].map(([key,label,accept,current]) => <label key={key} className="cursor-pointer rounded-2xl border-2 border-dashed border-slate-200 p-4 hover:border-blue-400"><UploadCloud className="text-blue-600"/><b className="mt-3 block text-sm">{label}</b><span className="mt-1 block break-all text-xs text-slate-500">{files[key]?.name || current || 'Pilih file'}</span><input type="file" accept={accept} className="hidden" onChange={event => setFiles({...files, [key]: event.target.files?.[0] || null})}/></label>)}
      {item?.reference_path && <a className="btn-ghost text-sm" href={`${STORAGE}/${item.reference_path}`} target="_blank" rel="noreferrer"><FileText size={16}/>Lihat format referensi</a>}
      <label className="flex items-center gap-3 rounded-2xl bg-emerald-50 p-4 text-sm font-bold text-emerald-800"><input type="checkbox" checked={!!form.is_active} onChange={event => update('is_active', event.target.checked)} className="size-4"/>Jadikan template aktif</label>
      <button className="btn-dark w-full" disabled={busy}>{busy ? 'Menyimpan…' : 'Simpan Template'}</button>
    </aside></div>
  </form></div>
}

export function ScholarshipLoaManager() {
  const [data, setData] = useState(null), [scopeValue, setScopeValue] = useState(''), [templateId, setTemplateId] = useState('')
  const [templateForm, setTemplateForm] = useState(null), [busy, setBusy] = useState(false), [message, setMessage] = useState('')
  const load = async (scope = scopeValue) => {
    const query = new URLSearchParams()
    if (scope) { const [competitionId, sessionId] = scope.split(':'); query.set('competition_id', competitionId); if (Number(sessionId)) query.set('competition_session_id', sessionId) }
    const result = await api(`/manage/scholarship-loas${query.size ? `?${query}` : ''}`)
    setData(result)
    const selected = `${result.scope?.competition_id || ''}:${result.scope?.competition_session_id || 0}`
    setScopeValue(selected)
    setTemplateId(current => current && result.templates.some(item => String(item.id) === String(current)) ? current : String(result.active_template_id || result.templates[0]?.id || ''))
  }
  useEffect(() => { load('') }, [])
  const resultByRank = useMemo(() => Object.fromEntries((data?.results || []).map(result => [result.rank, result])), [data?.results])
  if (!data) return <div className="p-10 text-center font-bold">Memuat LOA beasiswa…</div>

  const setWinner = async (rank, registrationId) => {
    if (!registrationId) return
    setBusy(true); setMessage('')
    try { await api('/manage/scholarship-loas/winners', {method: 'POST', body: JSON.stringify({competition_id: data.scope.competition_id, competition_session_id: data.scope.competition_session_id, rank, registration_id: Number(registrationId)})}); await load() }
    catch (error) { setMessage(error.message) } finally { setBusy(false) }
  }
  const generate = async () => {
    setBusy(true); setMessage('')
    try { const result = await api('/manage/scholarship-loas/generate', {method: 'POST', body: JSON.stringify({competition_id: data.scope.competition_id, competition_session_id: data.scope.competition_session_id, template_id: Number(templateId)})}); setMessage(result.message); await load() }
    catch (error) { setMessage(error.message) } finally { setBusy(false) }
  }
  const removeTemplate = async item => {
    if (!confirm(`Hapus template ${item.name}?`)) return
    try { await api(`/manage/scholarship-loas/templates/${item.id}`, {method: 'DELETE'}); await load() } catch (error) { setMessage(error.message) }
  }

  return <><div className="mb-7 flex flex-wrap items-end justify-between gap-4"><div><div className="text-xs font-black uppercase tracking-[.16em] text-blue-600">Scholarship administration</div><h1 className="mt-2 font-display text-3xl font-bold">LOA Beasiswa Pemenang</h1><p className="mt-2 text-sm text-slate-500">Terbitkan satu LOA pribadi untuk setiap pemain dan official dalam tim pemenang.</p></div><button className="btn-dark" onClick={() => setTemplateForm({})}><Plus size={17}/>Tambah Format LOA</button></div>

    {message && <div className="mb-5 rounded-2xl bg-blue-50 p-4 text-sm font-bold text-blue-800">{message}</div>}
    <section className="rounded-3xl bg-white p-5 sm:p-7"><div className="flex flex-wrap items-center justify-between gap-3"><div><h2 className="font-display text-xl font-bold">1. Format LOA</h2><p className="mt-1 text-sm text-slate-500">Unggah contoh lama sebagai referensi dan gambar letterhead sebagai latar cetak.</p></div><span className="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold">{data.templates.length} template</span></div>
      <div className="mt-5 grid gap-4 lg:grid-cols-2">{data.templates.length ? data.templates.map(item => <article key={item.id} className={`rounded-2xl border-2 p-4 ${item.is_active ? 'border-blue-500 bg-blue-50/50' : 'border-slate-100'}`}><div className="flex items-start justify-between gap-3"><div><div className="flex flex-wrap gap-2">{item.is_active && <span className="rounded-full bg-blue-600 px-2 py-1 text-[10px] font-black text-white">AKTIF</span>}<span className="rounded-full bg-slate-100 px-2 py-1 text-[10px] font-bold">{item.issuances_count} LOA</span></div><h3 className="mt-2 font-display font-bold">{item.name}</h3><p className="text-xs text-slate-500">{item.scholarship_name}</p><div className="mt-3 flex flex-wrap gap-1.5">{[1,2,3,4].map(rank => <span key={rank} className="rounded-lg bg-white px-2 py-1 text-[10px] font-bold text-slate-600">{rankLabels[rank]}: {item.award_values?.[rank] || data.default_awards?.[rank]}</span>)}</div></div><div className="flex gap-2"><button className="btn-ghost px-3 py-2" onClick={() => setTemplateForm(item)}><Pencil size={14}/></button><button className="btn-ghost px-3 py-2 text-rose-600" onClick={() => removeTemplate(item)}><Trash2 size={14}/></button></div></div></article>) : <div className="rounded-2xl bg-amber-50 p-5 text-sm font-bold text-amber-800 lg:col-span-2">Belum ada format LOA. Tambahkan template sebelum menerbitkan surat.</div>}</div>
    </section>

    <section className="mt-6 rounded-3xl bg-white p-5 sm:p-7"><div className="flex flex-wrap items-end justify-between gap-4"><div><h2 className="font-display text-xl font-bold">2. Pemenang Kejuaraan</h2><p className="mt-1 text-sm text-slate-500">Hasil resmi terisi otomatis. Admin dapat memperbaiki data lama melalui pilihan tim.</p></div><select className="input w-full sm:w-96" value={scopeValue} onChange={event => {setScopeValue(event.target.value); load(event.target.value)}}>{data.scopes.map(scope => <option key={`${scope.competition_id}:${scope.competition_session_id || 0}`} value={`${scope.competition_id}:${scope.competition_session_id || 0}`}>{scope.label}</option>)}</select></div>
      <div className="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-4">{[1,2,3,4].map(rank => { const result = resultByRank[rank]; const issuances = result?.scholarship_loa_issuances || []; const recipientCount = result?.loa_recipient_count || 0; const batchQuery = issuances.map(item => item.id).join(','); return <article key={rank} className={`rounded-2xl border p-4 ${result ? 'border-emerald-200 bg-emerald-50/50' : 'border-amber-200 bg-amber-50/50'}`}><div className="flex items-center justify-between"><span className="grid size-9 place-items-center rounded-xl bg-ink text-xs font-black text-white">{rank}</span>{issuances.length === recipientCount && recipientCount > 0 ? <Check className="text-emerald-600" size={18}/> : <span className="text-[10px] font-black text-amber-700">{issuances.length}/{recipientCount} LOA</span>}</div><h3 className="mt-4 font-display font-bold">{rankLabels[rank]}</h3><p className="mt-1 min-h-10 text-sm font-bold text-slate-700">{winnerName(result)}</p><p className="text-xs text-slate-500">{result?.registration?.school_name || 'Pilih tim/peserta pemenang'}</p><select aria-label={`Pemenang ${rankLabels[rank]}`} className="input mt-4 py-2 text-xs" value={result?.registration_id || ''} disabled={busy} onChange={event => setWinner(rank, event.target.value)}><option value="">Pilih pemenang…</option>{data.candidates.map(candidate => <option key={candidate.id} value={candidate.id}>{candidate.team_name || candidate.full_name} · {candidate.school_name}</option>)}</select>{!!issuances.length && <><Link className="btn-primary mt-3 w-full py-2 text-xs" target="_blank" to={`/panel/loa/${issuances[0].id}/cetak?ids=${batchQuery}`}><Printer size={14}/>Cetak Semua ({issuances.length})</Link><details className="mt-2 rounded-xl bg-white p-2 text-xs"><summary className="cursor-pointer font-bold text-slate-600">Cetak per nama</summary><div className="mt-2 grid gap-1">{issuances.map(issuance => <Link key={issuance.id} className="rounded-lg px-2 py-1.5 font-bold text-blue-700 hover:bg-blue-50" target="_blank" to={`/panel/loa/${issuance.id}/cetak`}>{issuance.snapshot?.recipient_name || issuance.snapshot?.participant_name}</Link>)}</div></details></>}</article>})}</div>
    </section>

    <section className="mt-6 rounded-3xl bg-ink p-5 text-white sm:p-7"><div className="grid gap-5 lg:grid-cols-[1fr_320px]"><div><Award className="text-electric"/><h2 className="mt-4 font-display text-2xl font-bold">3. Terbitkan LOA Individual</h2><p className="mt-2 max-w-2xl text-sm leading-6 text-slate-400">Sistem membuat satu LOA bernomor unik atas nama setiap pemain dan official. Penerbitan ulang memperbarui surat yang sama tanpa membuat duplikat.</p></div><div><label className="label text-slate-300">Gunakan template</label><select className="input bg-white text-ink" value={templateId} onChange={event => setTemplateId(event.target.value)}><option value="">Pilih template…</option>{data.templates.map(item => <option key={item.id} value={item.id}>{item.name}{item.is_active ? ' · Aktif' : ''}</option>)}</select><button className="btn-primary mt-3 w-full" onClick={generate} disabled={busy || !templateId || !data.results.length}><FileText size={17}/>{busy ? 'Menyiapkan…' : 'Buat LOA Semua Nama'}</button></div></div></section>
    {templateForm && <TemplateModal item={templateForm.id ? templateForm : null} defaultBody={data.default_body} defaultAwards={data.default_awards} placeholders={data.placeholders} close={() => setTemplateForm(null)} saved={() => {setTemplateForm(null); load()}}/>}
  </>
}
