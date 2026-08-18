import { useEffect, useState } from 'react'
import { ChevronLeft, ChevronRight } from 'lucide-react'

const asset = (year, file) => `/gallery/${year}/${file}`

const gallery2023 = [
  'talent-3-1.png', 'talent-2-1.png', 'talent-5-1.png', 'talent-4-1.png',
  'talent-7-1.png', 'talent-8-1.png', 'talent-9-1.png', 'talent-11-1.png',
  'talent-10-1.png', 'talent-6-1.png',
]
const gallery2024 = [
  'galeri-2024-bsi-flash-7.jpg', 'galeri-2024-bsi-flash-6.jpg', 'galeri-2024-bsi-flash-5.jpg',
  'galeri-2024-bsi-flash-4.jpg', 'galeri-2024-bsi-flash-3.jpg', 'galeri-2024-bsi-flash-1.jpg', 'galeri-2024-bsi-flash.jpg',
]
const gallery2025 = [
  ['BSI Star', ['Juara-1-BSI-Star.jpeg', 'Juara-2-BSI-Star.jpeg', 'Juara-3-BSI-Star.jpeg']],
  ['KPOP Dance Cover', ['Juara-1-KPOP-DANCE-COVER.jpeg', 'Juara-2-KPOP-DANCE-COVER.jpeg', 'Juara-3-KPOP-DANCE-COVER.jpeg']],
  ['Esport Mobile Legend', ['Juara-1-ESPORT-MOBILE-LEGEND.jpeg', 'Juara-2-ESPORT-MOBILE-LEGEND.jpeg', 'Juara-3-ESPORT-MOBILE-LEGEND.jpeg']],
  ['LKBB SMA', ['Juara-1-LKBB-SMA.jpeg', 'Juara-2-LKBB-SMA.jpeg', 'Juara-3-LKBB-SMA.jpeg']],
  ['LKBB SMP', ['Juara-Utama-1-LKBB-SMP.jpeg', 'Juara-Utama-2-LKBB-SMP.jpeg', 'Juara-Utama-3-LKBB-SMP.jpeg']],
]

const sport2024 = [
  ['BEKASI', [['Basket', 14, 210, ['SMAN 9 Bekasi', 'SMAN 5 Tambun Selatan', 'SMA PGRI 1 Bekasi', 'SMAI Darussalam']], ['Voli', 15, 225, ['SMK Garuda Nusantara', 'SMKN 1 Cikarang Barat', 'SMAN 10 Kota Bekasi', 'SMA Martia Bhakti']], ['Futsal', 24, 360, ['SMKN 3 Bekasi', 'SMAN 14 Bekasi', 'SMKN 5 Bekasi', 'SMA Pusaka Nusantara 2']]]],
  ['TANGERANG', [['Basket', 15, 225, ['SMAN 28 Kab. Tangerang', 'SMKN 7 Kab. Tangerang', 'SMKN 1 Kota Tangerang', 'SMA An Nurmaniyah']], ['Voli', 14, 210, ['SMKS PGRI 109 Kota Tangerang', 'SMAN 8 Kota Tangerang', 'SMAN 22 Kabupaten Tangerang', 'SMAN 11 Kabupaten Tangerang']], ['Futsal', 21, 315, ['SMK Yapia Parung', 'SMA An Nurmaniyah', 'SMAN 3 Kab. Tangerang', 'SMKN 1 Tangerang Selatan']]]],
  ['JAKARTA', [['Basket', 16, 240, ['SMK YADIKA 1', 'SMK CINTA KASIH TZU CHI', 'SMA YADIKA 2', 'SMAN 23 JAKARTA']], ['Voli', 17, 255, ['SMK Nuurul Bayan Kalapanunggal', 'SMAN 102 Jakarta', 'SMA YP Karya', 'SMK Cengkareng 1']], ['Futsal', 24, 360, ['SMK Jakarta Barat 1', 'SMK Cengkareng 2', 'SMK Nusantara Kedoya', 'SMK PGRI 35']]]],
  ['DEPOK', [['Basket', 11, 165, ['SMA YAPEMRI', 'SMAN 5 Kota Depok', 'SMAN 6 Kota Depok', 'SMK Kesuma Bangsa 2 Kota Depok']], ['Voli', 17, 255, ['SMA Al-Nur Cibinong', 'SMAN 67 Jakarta', 'SMAN 13 Kota Depok', 'SMK Citra Negara']], ['Futsal', 25, 375, ['SMK Citra Negara', 'SMA Bunda Kandung', 'SMA Sejahtera 1 Depok', 'SMA IT Rahmaniyah']]]],
]
const talent2024 = [
  ['Menulis', ['SMA Islam Nurul Fikri Boarding School Serang · M. Haikal Noviandri', 'SMA NEGERI 1 TANJUNG BATU · RIRIN ARIYANI', 'Thursina IIBS Malang · Ayasha Nabila Saquila', 'SMK Binong Permai Tangerang · Irma Suci Utami']],
  ['BSI Star', ['SMA NEGERI 1 PURWOKERTO · Evluna Ibel Pristy', 'SMAN 10 Tangerang · Jessica Chloe', 'SMAN 1 Kota Sukabumi · Artfil Kenanga Madewi', 'SMA NEGERI 1 PURWOKERTO · Aufaa Fedora Lalita']],
  ['Vlog', ['SMAN 28 Kab. Tangerang · My Kinekami', 'SMK Nida El-Adabi Bogor · Puspita Putri & Erika Ramadani', 'SMA Yos Sudarso Karawang · Yosuka Production', 'SMA Yos Sudarso Karawang · Yosuka Production']],
  ['KPOP Dance', ['SMAN 1 Parung Bogor · D1VERSITY', 'SMK Setia Negara Depok · Finale Groove', 'SMAN 1 Cikampek · Kulkas Dancer']],
  ['Esport MLBB', ['SMA XAVERIUS 1 PALEMBANG', 'SMK ABDI BANGSA SUKABUMI', 'SMA METHODIST 2 PALEMBANG']],
  ['LKBB SMP', ['SMPN 2 SUKAWANGI BEKASI', 'SMPN 4 TAMBUN UTARA', 'SMPN 21 BEKASI']],
  ['LKBB SMA', ['MA ATTAQWA PUTRA BEKASI', 'SMKN 3 BEKASI', 'SMK MALAKA JAKARTA']],
]
const talent2025 = [
  ['BSI STAR', ['SMA Islam Asysyakirin Tangerang · Ulya Wahidatul Karima', 'SMKN 1 Rengasdengklok Karawang · Mega Lerviana', 'SMA Iman Karawang · Michelline Keysha Elisabet Situmorang']],
  ['K-POP DANCE', ['SMK Widya Nusantara Kota Bekasi · Lieveth', 'SMK Setia Negara Depok · Finale Groove', 'Sekolah Pelita Harapan Lippo Cikarang · Loons']],
  ['E-SPORT MLBB', ['SMK 3 Perguruan Cikini', 'SMAN 2 Cianjur B', 'SMAN 2 Cianjur A']],
  ['LKBB SMA', ['SMKN 3 DEPOK', 'SMAN 2 BABELAN B', 'SMA GALAJUARA KOTA BEKASI']],
  ['LKBB SMP', ['SMPN 1 CABANGBUNGIN KAB. BEKASI', 'SMPN 4 TAMBUN UTARA A KAB. BEKASI', 'SMPN 2 SUKAWANGI B KAB. BEKASI']],
]

function Counter({ value, label }) { return <div className="gallery-counter"><strong>{value.toLocaleString('id-ID')}</strong><span>{label}</span></div> }
function PhotoCarousel({ year, images, title }) {
  const [index, setIndex] = useState(0)
  const next = () => setIndex(value => (value + 1) % images.length)
  const previous = () => setIndex(value => (value - 1 + images.length) % images.length)
  useEffect(() => {
    const timer = setInterval(() => setIndex(value => (value + 1) % images.length), 5000)
    return () => clearInterval(timer)
  }, [images.length])
  return <section className="gallery-carousel">
    {title && <div className="gallery-carousel-title"><span>{title}</span><b>{String(index + 1).padStart(2, '0')} / {String(images.length).padStart(2, '0')}</b></div>}
    <div className="gallery-carousel-frame">
      <img src={asset(year, images[index])} alt={title || `Galeri BSI Flash ${year}`} />
      <button type="button" onClick={previous} aria-label="Foto sebelumnya"><ChevronLeft size={20} /></button>
      <button type="button" onClick={next} aria-label="Foto berikutnya"><ChevronRight size={20} /></button>
    </div>
    <div className="gallery-dots">{images.map((image, dot) => <button key={image} type="button" className={dot === index ? 'active' : ''} onClick={() => setIndex(dot)} aria-label={`Foto ${dot + 1}`} />)}</div>
  </section>
}
function WinnerTable({ rows }) { return <div className="gallery-table-wrap"><table className="gallery-table"><thead><tr><th>Kategori Lomba</th><th>Juara</th></tr></thead><tbody>{rows.map(([category, winners]) => winners.map((winner, index) => <tr key={`${category}-${winner}`}><td>{index === 0 ? category : ''}</td><td><b>Juara {index + 1}</b> : {winner}</td></tr>))}</tbody></table></div> }
function Accordion({ rows }) { const [open, setOpen] = useState(''); return <div className="gallery-accordion">{rows.map(([location, categories]) => <article key={location}><button type="button" onClick={() => setOpen(open === location ? '' : location)}><span>{location}</span><span>{open === location ? '−' : '+'}</span></button>{open === location && <div className="gallery-accordion-body">{categories.map(([category, teams, participants, winners]) => <div key={category} className="gallery-sport-item"><h3>{category}</h3><p>Jumlah Tim: {teams} · Total Peserta: {participants || '-'}</p>{winners.map((winner, index) => <div key={winner}>Juara {index + 1}: {winner}</div>)}</div>)}</div>}</article>)}</div> }

function BaseGallery({ year, tagline, account, provinces, videos, children }) {
  return <div className="gallery-page">
    <section className="gallery-hero"><div className="gallery-hero-grid" /><div className="gallery-kicker">RECAP · BSI FLASH {year}</div><h1>BSI FLASH <span>{year}</span></h1><p>“{tagline}”</p></section>
    <section className="gallery-section gallery-stats"><div className="gallery-section-head"><span>Archive / {year}</span><h2>Data Peserta BSI Flash {year}</h2></div><div className="gallery-counter-grid"><Counter value={account} label="Akun Peserta" /><Counter value={provinces} label="Provinsi Peserta" /></div></section>
    {videos?.length > 0 && <section className="gallery-section gallery-dark"><div className="gallery-section-head"><span>Watch the highlights</span><h2>Highlight Video</h2></div><div className="gallery-video-grid">{videos.map(id => <div className="gallery-video" key={id}><iframe src={`https://www.youtube-nocookie.com/embed/${id}?rel=0&modestbranding=1&playsinline=1`} title="BSI Flash highlight" loading="lazy" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" referrerPolicy="strict-origin-when-cross-origin" allowFullScreen /></div>)}</div></section>}
    {children}
  </div>
}

function Gallery2023() { return <BaseGallery year={2023} tagline="Generasi Juara dan Bertalenta Digital" account={1344} provinces={30} videos={['vyO2cfECTeU', 'i_8gEqAg2Sc', '8TdM6zYy8-U', 'Hp5DtX--tDk']}><section className="gallery-section"><div className="gallery-section-head"><span>Talent competition</span><h2>Kategori Talent Competition</h2></div><div className="gallery-summary-grid"><div><h3>Jumlah Per Kategori</h3>{[['Menulis', 426], ['Fotografi', 322], ['BSI Star', 231], ['Esport (PUBG)', 109], ['Dance Cover', 103], ['LKBB', 87], ['Vlog', 66]].map(row => <p key={row[0]}><span>{row[0]}</span><b>{row[1]}</b></p>)}</div><div><h3>Jumlah Per Provinsi</h3>{[['Jawa Barat', 483], ['DKI Jakarta', 272], ['Banten', 116], ['Jawa Timur', 108], ['Jawa Tengah', 87], ['D.I Yogyakarta', 70], ['Sumatera Utara', 37], ['Lainnya', 171]].map(row => <p key={row[0]}><span>{row[0]}</span><b>{row[1]}</b></p>)}</div></div><PhotoCarousel year={2023} images={gallery2023} title="Galeri talent BSI Flash 2023" /></section><section className="gallery-section gallery-dark"><div className="gallery-section-head"><span>Winners archive</span><h2>Juara Talent Competition</h2></div><div className="gallery-winner-grid">{[['LKBB', ['juara-lkbb-1.jpg', 'juara-lkbb-2.jpg', 'juara-lkbb-3.jpg']], ['Esport PUBG', ['juara-pubg-1.jpg', 'juara-pubg-2.jpg', 'juara-pubg-3.jpg']]].map(([title, images]) => <div className="gallery-winner-group" key={title}><h3>{title}</h3><div>{images.map((image, index) => <figure key={image}><img src={asset(2023, image)} alt={`${title} juara ${index + 1}`} /><figcaption>Juara {index + 1}</figcaption></figure>)}</div></div>)}</div></section></BaseGallery> }
function Gallery2024() { return <BaseGallery year={2024} tagline="Explore Your Energy" account={1183} provinces={21} videos={['indItyl_YUE', 'mVXUdJ5FTEU', 'aWIY4C4cBK0']}><section className="gallery-section"><div className="gallery-section-head"><span>Sport + talent competition</span><h2>Ringkasan Kompetisi</h2></div><div className="gallery-summary-grid"><div><h3>SPORT Competition</h3>{[['Futsal', 301], ['Voli', 193], ['Basket', 148]].map(row => <p key={row[0]}><span>{row[0]}</span><b>{row[1]}</b></p>)}</div><div><h3>TALENT Competition</h3>{[['Vlog', 26], ['KPop Dance', 38], ['LKBB', 96], ['Menulis', 101], ['BSI Star', 132], ['Esport MLBB', 148]].map(row => <p key={row[0]}><span>{row[0]}</span><b>{row[1]}</b></p>)}</div></div><PhotoCarousel year={2024} images={gallery2024} title="Galeri foto BSI Flash 2024" /></section><section className="gallery-section gallery-dark"><div className="gallery-section-head"><span>Sport competition</span><h2>SPORT COMPETITION</h2></div><Accordion rows={sport2024} /></section><section className="gallery-section"><div className="gallery-section-head"><span>Talent competition</span><h2>TALENT COMPETITION</h2></div><WinnerTable rows={talent2024} /></section></BaseGallery> }
function Gallery2025() { return <BaseGallery year={2025} tagline="Explore Your Energy" account={0} provinces={0} videos={['GqqsEoXCNhw', 'Kx0i6n38kWc', 'B_JyoQ-DiO8']}><section className="gallery-section gallery-dark"><div className="gallery-section-head"><span>Talent competition</span><h2>TALENT COMPETITION</h2></div><WinnerTable rows={talent2025} /></section><section className="gallery-section"><div className="gallery-section-head"><span>Winner archive</span><h2>Galeri Juara</h2></div><div className="gallery-2025-grid">{gallery2025.map(([title, images]) => <PhotoCarousel key={title} year={2025} images={images} title={title} />)}</div></section></BaseGallery> }

export function GalleryPage({ year }) { return year === '2023' ? <Gallery2023 /> : year === '2024' ? <Gallery2024 /> : <Gallery2025 /> }
