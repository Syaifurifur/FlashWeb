# BSI Flash 2027

Platform manajemen promosi dan pendaftaran lomba SMA berbasis React, Tailwind CSS, Laravel, dan MySQL.

## Menjalankan aplikasi

Prasyarat: PHP 8.2+, Composer, Node.js 20+, npm, dan MySQL.

```powershell
# Backend
cd backend
copy .env.example .env
composer install
php artisan key:generate
php artisan storage:link
php artisan migrate:fresh --seed
php artisan serve --host=127.0.0.1 --port=8000

# Frontend (terminal lain)
cd frontend
copy .env.example .env
npm install
npm run dev -- --host 127.0.0.1 --port 5173
```

Database default: `bsiflash`, user MySQL `root`, tanpa password. Sesuaikan `backend/.env` bila konfigurasi lokal berbeda.

## Akun demo

- Super Admin: `admin@bsiflash2027.id` / `password123`
- PIC: `pic@bsiflash2027.id` / `password123`

## Fitur utama

- Landing page, katalog, pencarian/filter, dan detail lomba
- Pendaftaran mobile-first dengan validasi NISN dan file maksimal 2 MB
- Format lomba individu atau tim; untuk tim cukup satu peserta perwakilan yang mendaftarkan tim
- Akun peserta dibuat saat pendaftaran dan digunakan untuk login
- Lupa password dengan token reset sekali pakai yang berlaku selama 60 menit
- Dashboard peserta menampilkan status serta catatan verifikasi
- Edit data hanya aktif ketika panitia menetapkan status Butuh Revisi
- Dashboard agregat Super Admin, CRUD lomba, dan manajemen PIC
- Edisi tahunan terpisah untuk peserta, penilaian, drawing, jadwal, pertandingan, notifikasi, dan pemenang
- Pembuatan tahun berikutnya dapat menyalin konfigurasi kota/lomba/timeline tanpa membawa hasil operasional tahun lama
- Satu lomba dapat memiliki banyak sesi kota, venue, rentang kegiatan, jadwal lomba, agenda tambahan, dan kuota per lokasi
- Peserta memilih lokasi saat pendaftaran; pilihan tampil pada API peserta/panitia dan ekspor Excel
- Kelola seluruh akun oleh Super Admin: role, penugasan PIC, reset password, serta aktivasi akun
- Jumlah slot dan penugasan banyak PIC dapat diatur per lomba
- Tombol WhatsApp publik dibuat otomatis untuk setiap PIC aktif yang ditugaskan
- Meja verifikasi PIC, pembatasan data per lomba, dan ekspor CSV
- Enkripsi nama ibu di database serta masking penuh dari akun PIC

## Kebijakan Dokumentasi

Setiap perubahan fitur, skema database, API, hak akses, atau alur pengguna wajib dicatat pada bagian **Changelog** di bawah. Entri terbaru ditempatkan paling atas dan memuat tanggal serta ringkasan perubahan.

## Changelog

- Mengubah pelengkapan data tim besar menjadi proses bertahap: data teks disimpan lebih dahulu, kemudian dokumen diunggah per anggota secara berurutan dengan indikator progres dan dukungan coba lagi tanpa mengulang upload yang sudah berhasil.
- Mengganti menu Informasi menjadi Download Dokumen dan menambahkan halaman publik yang otomatis menampilkan serta mengunduh dokumen global hasil unggahan admin.
- Mengganti kontak email pada footer menjadi tautan resmi `www.bsi.ac.id` yang terbuka di tab baru.
- Memperjelas warna teks kartu pembayaran detail lomba agar nominal, label, dan batas pendaftaran tetap kontras di atas background gelap pada light maupun dark mode.
- Mengembalikan pengaturan rekening pembayaran pada formulir lomba multi-kota; rekening selalu dapat diisi dan otomatis wajib ketika salah satu kota menetapkan biaya pendaftaran.
- Memperjelas hero detail lomba dengan panel background gelap berlapis, aksen biru, serta warna teks dan metadata yang tetap kontras pada light maupun dark mode.
- Menambahkan manajemen Tahun Pelaksanaan dengan edisi aktif, draft, dan arsip; pemilih tahun pada panel; isolasi seluruh data operasional; snapshot pemenang; serta proses duplikasi konfigurasi untuk tahun berikutnya dengan pergeseran tanggal otomatis.
- Mengubah feature lomba pada hero menjadi slider manual berisi lomba dengan pendaftaran aktif saja, tanpa duplikasi kartu, lengkap dengan tombol navigasi, indikator, keyboard, dan gestur geser di layar sentuh.
- Menambahkan pengelolaan testimoni pada panel konten dan slider testimoni responsif di landing page, lengkap dengan foto, identitas, status aktif, urutan, navigasi, serta durasi perpindahan otomatis.
- Menjadikan kategori landing page dan filter katalog mengikuti master Jenis Lomba aktif, dengan urutan popularitas berdasarkan jumlah pendaftaran serta statistik jumlah lomba dan pendaftar aktual.
- Memperbaiki hero landing page agar tinggi mengikuti panjang konten dan seluruh kartu feature, judul, serta harga tidak terpotong oleh bagian halaman berikutnya.
- Menambahkan CRUD master Jenis Lomba dengan kelompok olahraga, bakat, atau sains; status aktif, urutan tampilan, pencarian, proteksi penghapusan saat masih digunakan, serta integrasi ke formulir pembuatan lomba.
- Menambahkan pencarian berdasarkan nama, email, atau WhatsApp serta paging mandiri pada daftar pilihan PIC dan SPV di setiap kota pelaksanaan lomba.
- Menambahkan field nomor WhatsApp langsung pada modal Tambah/Edit Akun untuk semua role; wajib bagi PIC/SPV, opsional bagi role lain, serta memastikan SPV tidak menampilkan penugasan lomba global.
- Menambahkan ringkasan hasil per kota pada dashboard panel, mencakup jumlah lomba, pendaftar, peserta disetujui, peserta menunggu, jadwal kegiatan, dan tautan kartu menuju detail masing-masing kota sesuai cakupan akun.
- Meningkatkan keterbacaan hero halaman kota dengan warna teks putih yang tidak terpengaruh light mode, bayangan teks, font venue lebih tegas, dan overlay foto yang lebih kontras.
- Menambahkan jumlah slot PIC dan SPV per kota pelaksanaan, pemilihan banyak akun sesuai slot, penyimpanan relasi petugas per lomba–kota, serta tombol WhatsApp untuk setiap PIC dan SPV pada landing page kota dan detail lomba.
- Menambahkan role SPV Kota serta penugasan PIC dan SPV pada setiap kombinasi lomba–kota; nomor WhatsApp publik kini mengikuti PIC/SPV lomba di kota yang dipilih peserta.
- Memisahkan data global lomba dari detail pelaksanaan per kota; menambahkan kuota, biaya, batas pelengkapan, pengumpulan karya, timeline, dan WhatsApp per kota, halaman publik kota dengan foto lapangan, serta pendaftaran yang mempertahankan pilihan kota.
- Menambahkan CRUD master tempat pelaksanaan lomba, meliputi nama venue, kota, alamat, tautan peta, kontak, catatan, status aktif, pencarian, dan pemilihan venue otomatis pada sesi lomba.
- Memastikan setiap pembukaan atau pemuatan ulang website selalu dimulai dalam light mode; dark mode tetap dapat diaktifkan sementara melalui tombol tema.
### 2026-08-02

- Mengubah seluruh teks pada panel CTA biru menjadi putih dan menyesuaikan tombol sekundernya agar tetap terbaca pada kondisi normal maupun hover.
- Mengganti seluruh aksen lime pada teks, tombol, ikon, fokus input, dan panel CTA menjadi biru royal, sekaligus menetapkan light mode sebagai tema awal bagi pengunjung baru.
- Menambahkan galeri foto responsif di antara kategori populer dan katalog lomba, dengan komposisi mosaik desktop, dua kolom tablet, satu kolom ponsel, serta tautan menuju arsip kegiatan terkait.
- Mengganti koleksi kategori pada landing page menjadi arsip hasil kegiatan BSI Flash 2023–2026, lengkap dengan kartu dokumentasi, tautan rekap tiap tahun, dan menu Galeri pada navigasi desktop maupun seluler.
- Menyamakan ukuran poster lomba dengan frame feature landing page menggunakan rasio 5:6 dan rekomendasi resolusi 1.000 × 1.200 piksel pada seluruh ukuran layar.
- Memperlebar area deskripsi hero secara responsif agar teks menjangkau lebih jauh ke kiri dan kanan tanpa kehilangan proporsi pada layar kecil.
- Memperpanjang deskripsi hero menjadi lebih dari 500 karakter dan menaikkan batas pengelolaan deskripsi halaman depan dari 500 menjadi 5.000 karakter.
- Mengganti seluruh ikon merek BSI Flash pada header publik, panel, halaman autentikasi, favicon browser, dan ikon perangkat menggunakan logo biru-oranye baru dengan latar transparan.
- Mengubah bagian step-by-step menjadi alur empat langkah bergaya Open9 dengan ikon, konektor garis putus-putus, deskripsi terpusat, serta susunan vertikal responsif pada perangkat seluler.
- Mengganti blok Kompetisi Unggulan pada landing page menjadi daftar kota pelaksanaan BSI Flash; kota diambil otomatis dari sesi lomba dan memakai jadwal Bogor, Pontianak, Jakarta, Tegal, Tangerang Raya, Bekasi, serta Kaliabang sebagai data awal.
- Menghapus slideshow gambar hero dan slider foto kegiatan dari halaman publik, panel pengelola konten, serta API konten; pengelolaan sponsor dan media partner tetap tersedia.
- Menghentikan seluruh animasi otomatis pada bagian landing page setelah Kompetisi Unggulan agar bagian bawah halaman tampil statis dan lebih nyaman dibaca.
- Menambahkan fitur lomba multi-lokasi dan multi-waktu melalui sesi pelaksanaan per kota, mencakup venue, rentang kegiatan, rentang lomba, Technical Meeting/agenda tambahan, catatan, status aktif, serta kuota lokasi.
- Menambahkan pilihan lokasi wajib pada pendaftaran, validasi kepemilikan dan kuota sesi, tampilan jadwal per kota pada detail lomba, relasi sesi pada dashboard/API, serta kolom lokasi dan jadwal pada ekspor Excel.
- Menjaga kompatibilitas lomba lama yang hanya memiliki satu lokasi dan mencegah sesi yang sudah memiliki pendaftar terhapus permanen.
- Menambahkan animasi landing page bergaya Open9: reveal hero bertahap, kartu lomba berbentuk kipas yang masuk berurutan, ornamen melayang, grid bergerak, reveal berbasis viewport, dan fallback `prefers-reduced-motion`.
- Menyamakan tipografi website dengan referensi Open9 menggunakan Manrope untuk teks utama dan Azeret Mono untuk label/deskripsi, dengan file font lokal agar tampil konsisten.
- Mengubah sumber kartu feature pada hero halaman depan agar hanya menggunakan data lomba dan tidak lagi mencampurkan gambar slideshow konten website.

### 2026-07-31

- Mengubah identitas website menjadi BSI Flash 2027, termasuk konten publik, email akun demo, email reset password, dan prefix tiket `BSIFLASH-`.
- Menerapkan tema visual Open9 dengan latar charcoal, aksen biru, tipografi Manrope, kartu kompetisi gelap, serta penyegaran halaman beranda, katalog, detail lomba, login, dan komponen dashboard.
- Menyusun ulang viewport utama landing page dengan headline terpusat, navigasi Open9, ornamen mengambang, dan kartu lomba berbentuk kipas yang responsif.
- Melengkapi landing page mengikuti ritme Open9 dengan bagian kompetisi unggulan, ranking kategori, katalog berfilter, koleksi mingguan, galeri, langkah pendaftaran, CTA, dan footer multi-kolom.
- Menambahkan tombol dark mode/light mode di navigasi publik dengan pilihan tema yang tersimpan otomatis pada perangkat pengguna.
- Menambahkan migrasi untuk memperbarui branding dan kode tiket pada data yang sudah tersimpan.

### 2026-07-01

- Menyederhanakan slider foto kegiatan menjadi hanya menggunakan link foto dan menghapus integrasi Instagram.

### 2026-06-27

- Menambahkan jumlah slot PIC ketika membuat lomba dan pengaturan `PIC & Slot` ketika mengedit lomba.
- Menambahkan penugasan beberapa PIC untuk satu lomba.
- Mewajibkan nomor WhatsApp aktif pada akun PIC.
- Menambahkan tombol WhatsApp untuk setiap PIC pada halaman detail lomba.
- Mengembalikan tombol pengaturan format lomba Individu/Tim pada Manajemen Lomba.
- Menambahkan modul Kelola Akun khusus Super Admin.
- Menambahkan fitur lupa dan reset password dengan token sekali pakai selama 60 menit.
- Menambahkan login dan dashboard peserta beserta alur revisi data berdasarkan permintaan panitia.
- Mengubah pendaftaran tim agar cukup dilakukan oleh satu peserta perwakilan.
- Menambahkan format lomba Individu/Tim serta konfigurasi jumlah anggota tim.
