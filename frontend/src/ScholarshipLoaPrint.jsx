import { useEffect, useState } from 'react'
import { ArrowLeft, Printer } from 'lucide-react'
import { useNavigate, useParams, useSearchParams } from 'react-router-dom'
import { api, STORAGE } from './api'
import './scholarship-loa-print.css'

function LoaSheet({data}) {
  const template = data.template, winner = data.snapshot
  return <article className={`loa-sheet ${template.background_path ? 'has-background' : ''}`}>
    {template.background_path && <img className="loa-background" src={`${STORAGE}/${template.background_path}`} alt=""/>}
    <div className="loa-content">
      <header className="loa-header"><img src="/bsi-flash-logo.png" alt="BSI Flash"/><div><span>LETTER OF ACCEPTANCE</span><h1>{template.scholarship_name}</h1><p>Program Beasiswa Pemenang BSI Flash</p></div></header>
      <div className="loa-number">Nomor: <b>{data.document_number}</b></div>
      <section className="loa-award">
        <div><span>{winner.rank_title}</span><h2>{winner.recipient_name || winner.participant_name}</h2><p>{winner.recipient_role || winner.recipient_type || 'Peserta'} · {winner.team_name}</p><strong>{winner.school_name} · {winner.competition_title} · {winner.city}</strong></div>
        <aside><span>Besaran Beasiswa</span><b>{winner.scholarship_award || '-'}</b></aside>
      </section>
      <section className="loa-recipient-details"><div><span>Nama penerima</span><b>{winner.recipient_name || winner.participant_name}</b></div><div><span>Status dalam tim</span><b>{winner.recipient_role || winner.recipient_type || 'Peserta'}</b></div>{winner.recipient_identifier && <div><span>NISN</span><b>{winner.recipient_identifier}</b></div>}</section>
      <section className="loa-body">{data.rendered_body}</section>
      <section className="loa-signature"><div><span>{template.signing_city || winner.city}, {new Intl.DateTimeFormat('id-ID', {dateStyle: 'long', timeZone: 'Asia/Jakarta'}).format(new Date(data.issued_at))}</span><b>{template.signatory_position || 'Panitia BSI Flash'}</b>{template.signature_path ? <img src={`${STORAGE}/${template.signature_path}`} alt="Tanda tangan"/> : <i/>}<strong>{template.signatory_name || 'Penanggung Jawab BSI Flash'}</strong></div></section>
      <footer><span>LOA ini diterbitkan khusus atas nama {winner.recipient_name || winner.participant_name} melalui sistem BSI Flash.</span><b>{data.document_number}</b></footer>
    </div>
  </article>
}

export function ScholarshipLoaPrint() {
  const {issuanceId} = useParams(), navigate = useNavigate(), [searchParams] = useSearchParams()
  const requestedIds = searchParams.get('ids') || issuanceId
  const [documents, setDocuments] = useState([]), [error, setError] = useState('')
  useEffect(() => {
    setError('')
    const ids = [...new Set(requestedIds.split(',').map(value => Number(value)).filter(Boolean))]
    Promise.all(ids.map(id => api(`/manage/scholarship-loas/issuances/${id}`)))
      .then(setDocuments)
      .catch(requestError => setError(requestError.message))
  }, [requestedIds])
  if (error) return <main className="loa-print-state"><div><h1>LOA tidak dapat dibuka</h1><p>{error}</p><button onClick={() => navigate(-1)}>Kembali</button></div></main>
  if (!documents.length) return <main className="loa-print-state"><div><h1>Menyiapkan LOA…</h1><p>Data penerima sedang dimuat.</p></div></main>
  return <main className="loa-print-page">
    <div className="loa-print-toolbar"><button onClick={() => window.close()}><ArrowLeft/>Tutup</button><div><b>Pratinjau {documents.length} LOA Individual</b><span>Setiap penerima dicetak pada satu halaman A4 tersendiri.</span></div><button className="primary" onClick={() => window.print()}><Printer/>Cetak / Simpan PDF</button></div>
    {documents.map(document => <LoaSheet key={document.id} data={document}/>)}
  </main>
}
