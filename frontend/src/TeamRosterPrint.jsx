import { useEffect, useMemo, useState } from 'react'
import { ArrowLeft, Printer, ShieldCheck, UserRound } from 'lucide-react'
import { useNavigate, useParams } from 'react-router-dom'
import { api, STORAGE } from './api'
import './team-roster-print.css'

const assetUrl = path => path ? `${STORAGE}/${path}` : ''
const display = value => value || '—'

function PhotoPlaceholder({label}) {
  return <div className="roster-photo-placeholder"><UserRound/><span>{label}</span></div>
}

export function TeamRosterPrint() {
  const {id}=useParams(),navigate=useNavigate()
  const [registration,setRegistration]=useState(null),[error,setError]=useState('')
  useEffect(()=>{api(`/manage/registrations/${id}`).then(setRegistration).catch(requestError=>setError(requestError.message))},[id])
  const players=useMemo(()=>{
    if(!registration)return []
    if(registration.competition?.participation_type==='team')return registration.members||[]
    return [{id:registration.id,member_order:1,full_name:registration.full_name,nisn:registration.nisn,whatsapp:registration.whatsapp,grade:registration.grade,photo_path:registration.photo_path}]
  },[registration])
  const officials=useMemo(()=>{
    if(!registration)return []
    const required=Number(registration.competition?.official_count||registration.officials?.length||0)
    return Array.from({length:required},(_,index)=>registration.officials?.find(item=>Number(item.official_order)===index+1)||{official_order:index+1})
  },[registration])

  if(error)return <main className="roster-state"><div><h1>Roster tidak dapat dibuka</h1><p>{error}</p><button onClick={()=>navigate(-1)}>Kembali</button></div></main>
  if(!registration)return <main className="roster-state"><div><h1>Menyiapkan roster tim…</h1><p>Data pemain dan official sedang dimuat.</p></div></main>

  const location=registration.competition_session
  const printedAt=new Intl.DateTimeFormat('id-ID',{dateStyle:'long',timeZone:'Asia/Jakarta'}).format(new Date())
  return <main className="roster-print-page">
    <div className="roster-toolbar">
      <button type="button" onClick={()=>window.close()}><ArrowLeft/>Tutup</button>
      <div><b>Pratinjau roster tim</b><span>Gunakan ukuran A4 dan orientasi landscape.</span></div>
      <button type="button" className="primary" onClick={()=>window.print()}><Printer/>Cetak / Simpan PDF</button>
    </div>
    <article className="roster-sheet">
      <header className="roster-header">
        <img src="/bsi-flash-logo.png" alt="BSI Flash"/>
        <div><span>LEMBAR VERIFIKASI PESERTA</span><h1>{registration.competition?.title}</h1><p>{location?`${location.city} · ${location.venue}`:'Tempat pelaksanaan belum dipilih'}</p></div>
        <div className="roster-ticket"><span>KODE PENDAFTARAN</span><b>{registration.ticket_code}</b><small>{registration.status==='approved'?'PESERTA DITERIMA':String(registration.status||'pending').toUpperCase()}</small></div>
      </header>

      <section className="roster-team-meta">
        <div><span>Nama Tim</span><b>{display(registration.team_name)}</b></div>
        <div><span>Sekolah</span><b>{display(registration.school_name)}</b></div>
        <div><span>Kota/Kabupaten</span><b>{display(registration.school_city)}</b></div>
        <div><span>Koordinator</span><b>{display(registration.full_name)} · {display(registration.whatsapp)}</b></div>
      </section>

      <div className="roster-main-grid">
        <section>
          <div className="roster-section-title"><span>FOTO PEMAIN</span><b>{players.length} pemain terdata</b></div>
          <div className="roster-player-grid">{players.map((player,index)=><article className="roster-player-card" key={player.id||index}>
            <div className="roster-player-photo">{player.photo_path?<img src={assetUrl(player.photo_path)} alt={`Foto ${player.full_name||`pemain ${index+1}`}`}/>:<PhotoPlaceholder label={`Pemain ${index+1}`}/>}</div>
            <div className="roster-player-name"><span>{player.member_order||index+1}</span><b>{display(player.full_name)}</b></div>
            <div className="roster-player-number">No. punggung: <span/></div>
          </article>)}</div>
        </section>

        <section className="roster-data-column">
          <div className="roster-section-title"><span>DATA PEMAIN</span><b>Untuk pemeriksaan panitia</b></div>
          <table className="roster-table"><thead><tr><th>No</th><th>Nama</th><th>NISN</th><th>No. Telepon</th><th>Kelas</th><th>Valid</th></tr></thead><tbody>{players.map((player,index)=><tr key={player.id||index}><td>{index+1}</td><td>{display(player.full_name)}</td><td>{display(player.nisn)}</td><td>{display(player.whatsapp)}</td><td>{display(player.grade)}</td><td className="check-cell">□</td></tr>)}</tbody></table>

          <div className="roster-official-heading"><div className="roster-section-title"><span>DATA OFFICIAL</span><b>{officials.filter(item=>item.id).length}/{officials.length} terdata</b></div></div>
          <div className="roster-official-list">{officials.length?officials.map((official,index)=><article key={official.id||index}>
            <span className="roster-official-avatar"><ShieldCheck/></span>
            <div><small>OFFICIAL {index+1} · {display(official.position)}</small><b>{display(official.full_name)}</b><p>{display(official.whatsapp)}</p></div>
            <span className="roster-official-check">□</span>
          </article>):<p className="roster-empty">Lomba ini tidak menetapkan slot official.</p>}</div>
        </section>
      </div>

      <footer className="roster-footer">
        <div><span>Catatan verifikator</span><div className="roster-note-lines"><i/><i/><i/></div></div>
        <div className="roster-signature"><span>{location?.city||'Kota pelaksanaan'}, {printedAt}</span><b>Verifikator / Panitia</b><i/><small>( ........................................................ )</small></div>
      </footer>
    </article>
  </main>
}
