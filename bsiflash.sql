-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Waktu pembuatan: 03 Agu 2026 pada 10.01
-- Versi server: 10.4.32-MariaDB
-- Versi PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `bsiflash`
--

-- --------------------------------------------------------

--
-- Struktur dari tabel `access_roles`
--

CREATE TABLE `access_roles` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(80) NOT NULL,
  `slug` varchar(80) NOT NULL,
  `permissions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`permissions`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `access_roles`
--

INSERT INTO `access_roles` (`id`, `name`, `slug`, `permissions`, `created_at`, `updated_at`) VALUES
(1, 'TIM REGISTRASI', 'tim_registrasi', '[\"dashboard.view\",\"registrations.view\",\"registrations.review\",\"registrations.export\",\"competitions.format\"]', '2026-06-27 22:43:13', '2026-06-27 22:43:13'),
(2, 'SPV', 'spv', '[\"dashboard.view\",\"registrations.view\",\"registrations.review\",\"registrations.export\",\"competitions.view\",\"competitions.edit\",\"competitions.format\",\"competitions.manage\",\"notifications.manage\",\"judging.manage\",\"judging.score\",\"tournaments.manage\"]', '2026-08-02 13:46:12', '2026-08-02 13:46:12');

-- --------------------------------------------------------

--
-- Struktur dari tabel `cache`
--

CREATE TABLE `cache` (
  `key` varchar(255) NOT NULL,
  `value` mediumtext NOT NULL,
  `expiration` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `cache_locks`
--

CREATE TABLE `cache_locks` (
  `key` varchar(255) NOT NULL,
  `owner` varchar(255) NOT NULL,
  `expiration` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `competitions`
--

CREATE TABLE `competitions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `event_edition_id` bigint(20) UNSIGNED DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `category` varchar(40) NOT NULL,
  `competition_type_id` bigint(20) UNSIGNED DEFAULT NULL,
  `participation_type` enum('individual','team') NOT NULL DEFAULT 'individual',
  `team_size` tinyint(3) UNSIGNED NOT NULL DEFAULT 1,
  `official_count` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `pic_slots` tinyint(3) UNSIGNED NOT NULL DEFAULT 1,
  `short_description` text NOT NULL,
  `description` longtext NOT NULL,
  `quota` int(10) UNSIGNED NOT NULL,
  `fee` decimal(12,2) NOT NULL DEFAULT 0.00,
  `bank_name` varchar(255) NOT NULL DEFAULT 'Bank Mandiri',
  `bank_account_number` varchar(80) NOT NULL DEFAULT '123 000 111 3804',
  `bank_account_holder` varchar(255) NOT NULL DEFAULT 'Yayasan Indonesia Nusa Mandiri',
  `payment_note` varchar(500) DEFAULT NULL,
  `registration_start` date NOT NULL,
  `registration_end` date NOT NULL,
  `team_update_deadline_at` datetime DEFAULT NULL,
  `document_upload_deadline_at` datetime DEFAULT NULL,
  `event_date` date NOT NULL,
  `submission_start_at` datetime DEFAULT NULL,
  `submission_end_at` datetime DEFAULT NULL,
  `judging_locked_at` timestamp NULL DEFAULT NULL,
  `results_announced_at` timestamp NULL DEFAULT NULL,
  `location` varchar(255) NOT NULL DEFAULT 'Online',
  `poster_url` varchar(255) DEFAULT NULL,
  `whatsapp_group_url` varchar(500) DEFAULT NULL,
  `requirements` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`requirements`)),
  `guides` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`guides`)),
  `downloadable_documents` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`downloadable_documents`)),
  `judging_guide` longtext DEFAULT NULL,
  `timeline` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`timeline`)),
  `schedule_venues` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`schedule_venues`)),
  `is_featured` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `competitions`
--

INSERT INTO `competitions` (`id`, `event_edition_id`, `title`, `slug`, `category`, `competition_type_id`, `participation_type`, `team_size`, `official_count`, `pic_slots`, `short_description`, `description`, `quota`, `fee`, `bank_name`, `bank_account_number`, `bank_account_holder`, `payment_note`, `registration_start`, `registration_end`, `team_update_deadline_at`, `document_upload_deadline_at`, `event_date`, `submission_start_at`, `submission_end_at`, `judging_locked_at`, `results_announced_at`, `location`, `poster_url`, `whatsapp_group_url`, `requirements`, `guides`, `downloadable_documents`, `judging_guide`, `timeline`, `schedule_venues`, `is_featured`, `created_at`, `updated_at`) VALUES
(7, 1, 'BOLA VOLI PUTRA', 'bola-voli-putra', 'Sport Competition', 1, 'individual', 1, 0, 1, 'Lomba Bola Voli Putra Tingkat SMA/SMK/MA', 'Kompetisi bola voli beregu khusus pelajar putra antarsekolah. Perlombaan ini menguji kekompakan tim, kemampuan teknik, strategi permainan, komunikasi, dan sportivitas para peserta.', 16, 200000.00, 'Bank Mandiri', '123 000 464 7683', 'Bina Sarana Informatika', 'Cantumkan nama peserta atau tim pada berita transfer.', '2026-08-26', '2026-08-25', '2026-08-25 16:00:00', '2026-08-25 16:00:00', '2026-09-06', NULL, NULL, NULL, NULL, 'Bogor', 'https://res.cloudinary.com/dishtnt6/image/upload/v1785706327/Voli_Putra_vs78ha.jpg', NULL, '[]', '[{\"title\":\"Ketentuan Umum\",\"content\":\"Siap mengikuti Turnamen BasketBall Competition BSI Flash 2026 s\\/d selesai dengan menjungjung sportivitas dan fair play.\\nKami menyetujui dan akan mematuhi semua peraturan yang sudah dijelaskan pada saat technical meeting online atau offline dan pada saat pertandingan berlangsung.\\nKami siap menerima sanksi tegas dari pihak penitia jika melakukan pelanggaran.\\nBersedia membayar BIAYA PENDAFTARAN ke rekening MANDIRI, No. Rek : 123 000 464 7683, a\\/n Bina Sarana Informatika, dengan nominal sebesar Rp 200.000.\\nCidera ringan maupun berat bahkan cidera yang berkepanjangan pada saat kejuaraan berlangsung dengan ketentuan yang berlaku bukan tanggung jawab panitia penyelenggara\\nJika Team sudah mendaftar dan membayar biaya pendaftaran lalu mengundurkan diri, maka uang biaya pendaftaran tidak dapat dikembalikan\\nWajib Hadir di Acara Opening Ceremony minimal 6 Pemain, Jika TIDAK HADIR dikenakan denda Rp. 100.000 sebelum jadwal pertandingan dimulai. Apabila belum membayar denda maka tim dinyatakan Gugur (WO)\"}]', NULL, NULL, '[{\"label\":\"Technical Meeting\",\"type\":\"single\",\"date\":\"2026-08-26\"},{\"label\":\"Jadwal Lomba\",\"type\":\"range\",\"start_date\":\"2026-09-03\",\"end_date\":\"2026-09-06\",\"date\":\"2026-09-03|2026-09-06\"}]', NULL, 0, '2026-08-02 14:18:54', '2026-08-03 00:48:47'),
(8, 1, 'BOLA VOLI PUTRI', 'bola-voli-putri', 'Sport Competition', 1, 'individual', 1, 0, 1, 'Lomba Bola Voli Putri Tingkat SMA/SMK/MA', 'Kompetisi bola voli beregu khusus pelajar putri yang menjadi wadah untuk menunjukkan kemampuan dalam melakukan servis, passing, smash, blocking, serta menyusun strategi permainan bersama tim.', 16, 200000.00, 'Bank Mandiri', '123 000 464 7683', 'Bina Sarana Informatika', 'Cantumkan nama peserta atau tim pada berita transfer.', '2026-08-26', '2026-08-25', '2026-08-25 16:00:00', '2026-08-25 16:00:00', '2026-09-06', NULL, NULL, NULL, NULL, 'Bogor', 'https://res.cloudinary.com/dishtnt6/image/upload/v1785706447/voliputri_etnrqh.jpg', NULL, '[]', '[{\"title\":\"Ketentuan Umum\",\"content\":\"Siap mengikuti Turnamen BasketBall Competition BSI Flash 2026 s\\/d selesai dengan menjungjung sportivitas dan fair play.\\nKami menyetujui dan akan mematuhi semua peraturan yang sudah dijelaskan pada saat technical meeting online atau offline dan pada saat pertandingan berlangsung.\\nKami siap menerima sanksi tegas dari pihak penitia jika melakukan pelanggaran.\\nBersedia membayar BIAYA PENDAFTARAN ke rekening MANDIRI, No. Rek : 123 000 464 7683, a\\/n Bina Sarana Informatika, dengan nominal sebesar Rp 200.000.\\nCidera ringan maupun berat bahkan cidera yang berkepanjangan pada saat kejuaraan berlangsung dengan ketentuan yang berlaku bukan tanggung jawab panitia penyelenggara\\nJika Team sudah mendaftar dan membayar biaya pendaftaran lalu mengundurkan diri, maka uang biaya pendaftaran tidak dapat dikembalikan\\nWajib Hadir di Acara Opening Ceremony minimal 6 Pemain, Jika TIDAK HADIR dikenakan denda Rp. 100.000 sebelum jadwal pertandingan dimulai. Apabila belum membayar denda maka tim dinyatakan Gugur (WO)\"}]', NULL, NULL, '[{\"label\":\"Technical Meeting\",\"type\":\"single\",\"date\":\"2026-08-26\"},{\"label\":\"Jadwal Lomba\",\"type\":\"range\",\"start_date\":\"2026-09-03\",\"end_date\":\"2026-09-06\",\"date\":\"2026-09-03|2026-09-06\"}]', NULL, 0, '2026-08-02 14:26:35', '2026-08-03 00:49:00'),
(9, 1, 'FUTSAL PUTRA', 'futsal-putra', 'Sport Competition', 1, 'individual', 1, 0, 1, 'Lomba Futsal Tingkat SMA/SMK/MA', 'Kompetisi futsal antarsekolah untuk kategori pelajar putra. Peserta ditantang menampilkan kemampuan teknik bermain, kerja sama tim, kecepatan, ketepatan strategi, serta menjunjung tinggi nilai fair play.', 16, 200000.00, 'Bank Mandiri', '123 000 464 7683', 'Bina Sarana Informatika', 'Cantumkan nama peserta atau tim pada berita transfer.', '2026-08-26', '2026-08-25', '2026-08-25 16:00:00', '2026-08-25 16:00:00', '2026-09-09', NULL, NULL, NULL, NULL, 'Bogor', 'https://res.cloudinary.com/dishtnt6/image/upload/v1785706554/futsalputra_z0nqbv.jpg', NULL, '[]', '[{\"title\":\"Ketentuan Umum\",\"content\":\"Siap mengikuti Turnamen Futsal Competition BSI Flash 2026 s\\/d selesai dengan menjunjung tinggi sportivitas dan fair play.\\nKami menyetujui dan akan mematuhi semua peraturan yang sudah dijelaskan pada saat technical meeting offline dan pada saat pertandingan berlangsung.\\nKami siap menerima sanksi tegas ataupun sanksi berat dari pihak penitia jika melakukan pelanggaran.\\nKami bersedia membayar BIAYA PENDAFTARAN ke rekening MANDIRI, No. Rek : 123 000 464 7683, a\\/n Bina Sarana Informatika dengan nominal sebesar Rp 200.000.\\nKami bersedia membayar UANG JAMINAN Sebesar Rp 200.000 Pada saat technical meeting secara tunai untuk keperluan terkena denda pada saat pertandingan.\\nCidera ringan maupun berat bahkan cidera yang berkepanjangan pada saat kejuaraan berlangsung dengan ketentuan yang berlaku bukan tanggung jawab panitia penyelenggara\\nJika Team sudah mendaftar dan membayar biaya pendaftaran lalu mengundurkan diri, maka uang biaya pendaftaran tidak dapat dikembalikan\\nWajib Hadir di Acara Opening Ceremony minimal 5 Pemain, Jika TIDAK HADIR dikenakan denda Rp. 100.000 sebelum jadwal pertandingan dimulai. Apabila belum membayar denda maka tim dinyatakan Gugur (WO)\"}]', NULL, NULL, '[{\"label\":\"Technical Meeting\",\"type\":\"single\",\"date\":\"2026-08-26\"},{\"label\":\"Jadwal Lomba\",\"type\":\"range\",\"start_date\":\"2026-09-07\",\"end_date\":\"2026-09-09\",\"date\":\"2026-09-07|2026-09-09\"}]', NULL, 0, '2026-08-02 14:28:50', '2026-08-03 00:48:17');

-- --------------------------------------------------------

--
-- Struktur dari tabel `competition_notifications`
--

CREATE TABLE `competition_notifications` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `event_edition_id` bigint(20) UNSIGNED DEFAULT NULL,
  `competition_id` bigint(20) UNSIGNED DEFAULT NULL,
  `author_id` bigint(20) UNSIGNED DEFAULT NULL,
  `title` varchar(160) NOT NULL,
  `message` text NOT NULL,
  `published_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `competition_results`
--

CREATE TABLE `competition_results` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `competition_id` bigint(20) UNSIGNED NOT NULL,
  `competition_session_id` bigint(20) UNSIGNED DEFAULT NULL,
  `registration_id` bigint(20) UNSIGNED NOT NULL,
  `rank` smallint(5) UNSIGNED NOT NULL,
  `title` varchar(120) NOT NULL,
  `source` enum('judging','tournament','manual') NOT NULL,
  `score` decimal(12,2) DEFAULT NULL,
  `announced_at` timestamp NULL DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `competition_sessions`
--

CREATE TABLE `competition_sessions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `competition_id` bigint(20) UNSIGNED NOT NULL,
  `venue_id` bigint(20) UNSIGNED DEFAULT NULL,
  `pic_user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `supervisor_user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `pic_slots` tinyint(3) UNSIGNED NOT NULL DEFAULT 1,
  `supervisor_slots` tinyint(3) UNSIGNED NOT NULL DEFAULT 1,
  `city` varchar(120) NOT NULL,
  `venue` varchar(180) NOT NULL,
  `activity_start_date` date NOT NULL,
  `activity_end_date` date NOT NULL,
  `competition_start_date` date NOT NULL,
  `competition_end_date` date NOT NULL,
  `information_label` varchar(120) DEFAULT NULL,
  `information_at` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `quota` int(10) UNSIGNED DEFAULT NULL,
  `fee` decimal(12,2) NOT NULL DEFAULT 0.00,
  `team_update_deadline_at` datetime DEFAULT NULL,
  `submission_start_at` datetime DEFAULT NULL,
  `submission_end_at` datetime DEFAULT NULL,
  `timeline` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`timeline`)),
  `whatsapp_number` varchar(30) DEFAULT NULL,
  `whatsapp_group_url` varchar(500) DEFAULT NULL,
  `sort_order` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `competition_sessions`
--

INSERT INTO `competition_sessions` (`id`, `competition_id`, `venue_id`, `pic_user_id`, `supervisor_user_id`, `pic_slots`, `supervisor_slots`, `city`, `venue`, `activity_start_date`, `activity_end_date`, `competition_start_date`, `competition_end_date`, `information_label`, `information_at`, `notes`, `quota`, `fee`, `team_update_deadline_at`, `submission_start_at`, `submission_end_at`, `timeline`, `whatsapp_number`, `whatsapp_group_url`, `sort_order`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 7, 1, 2, 10, 1, 1, 'Bogor', 'Sport Center UBSI Kampus Bogor A', '2026-09-03', '2026-09-09', '2026-08-26', '2026-09-06', NULL, NULL, NULL, 16, 200000.00, '2026-08-25 16:00:00', NULL, NULL, '[{\"label\":\"Technical Meeting\",\"type\":\"single\",\"date\":\"2026-08-26\"},{\"label\":\"Jadwal Lomba\",\"type\":\"range\",\"start_date\":\"2026-09-03\",\"end_date\":\"2026-09-06\",\"date\":\"2026-09-03|2026-09-06\"}]', '081234567890', NULL, 0, 1, '2026-08-02 14:18:54', '2026-08-03 00:48:47'),
(2, 8, 1, 2, 10, 1, 1, 'Bogor', 'Sport Center UBSI Kampus Bogor A', '2026-09-03', '2026-09-09', '2026-08-26', '2026-09-06', NULL, NULL, NULL, 16, 200000.00, '2026-08-25 16:00:00', NULL, NULL, '[{\"label\":\"Technical Meeting\",\"type\":\"single\",\"date\":\"2026-08-26\"},{\"label\":\"Jadwal Lomba\",\"type\":\"range\",\"start_date\":\"2026-09-03\",\"end_date\":\"2026-09-06\",\"date\":\"2026-09-03|2026-09-06\"}]', '081234567890', NULL, 0, 1, '2026-08-02 14:26:35', '2026-08-03 00:49:00'),
(3, 9, 1, 2, 10, 1, 1, 'Bogor', 'Sport Center UBSI Kampus Bogor A', '2026-09-03', '2026-09-09', '2026-08-26', '2026-09-09', NULL, NULL, NULL, 16, 200000.00, '2026-08-25 16:00:00', NULL, NULL, '[{\"label\":\"Technical Meeting\",\"type\":\"single\",\"date\":\"2026-08-26\"},{\"label\":\"Jadwal Lomba\",\"type\":\"range\",\"start_date\":\"2026-09-07\",\"end_date\":\"2026-09-09\",\"date\":\"2026-09-07|2026-09-09\"}]', '081234567890', NULL, 0, 1, '2026-08-02 14:28:50', '2026-08-03 00:48:17');

-- --------------------------------------------------------

--
-- Struktur dari tabel `competition_session_staff`
--

CREATE TABLE `competition_session_staff` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `competition_session_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `role` varchar(20) NOT NULL,
  `sort_order` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `competition_session_staff`
--

INSERT INTO `competition_session_staff` (`id`, `competition_session_id`, `user_id`, `role`, `sort_order`, `created_at`, `updated_at`) VALUES
(1, 1, 2, 'pic', 0, '2026-08-02 14:18:54', '2026-08-03 00:48:47'),
(2, 1, 10, 'spv', 0, '2026-08-02 14:18:54', '2026-08-03 00:48:47'),
(3, 2, 2, 'pic', 0, '2026-08-02 14:26:35', '2026-08-03 00:49:00'),
(4, 2, 10, 'spv', 0, '2026-08-02 14:26:35', '2026-08-03 00:49:00'),
(5, 3, 2, 'pic', 0, '2026-08-02 14:28:50', '2026-08-03 00:48:17'),
(6, 3, 10, 'spv', 0, '2026-08-02 14:28:50', '2026-08-03 00:48:17');

-- --------------------------------------------------------

--
-- Struktur dari tabel `competition_types`
--

CREATE TABLE `competition_types` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(120) NOT NULL,
  `slug` varchar(140) NOT NULL,
  `category_group` varchar(40) NOT NULL,
  `description` text DEFAULT NULL,
  `sort_order` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `competition_types`
--

INSERT INTO `competition_types` (`id`, `name`, `slug`, `category_group`, `description`, `sort_order`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Sport Competition', 'sport-competition', 'Sport Competition', 'Jenis lomba bawaan untuk kelompok Sport Competition.', 1, 1, '2026-08-02 14:07:05', '2026-08-02 14:07:05'),
(2, 'Talent Competition', 'talent-competition', 'Talent Competition', 'Jenis lomba bawaan untuk kelompok Talent Competition.', 2, 1, '2026-08-02 14:07:05', '2026-08-02 14:07:05');

-- --------------------------------------------------------

--
-- Struktur dari tabel `competition_venues`
--

CREATE TABLE `competition_venues` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `event_edition_id` bigint(20) UNSIGNED DEFAULT NULL,
  `slug` varchar(255) DEFAULT NULL,
  `name` varchar(180) NOT NULL,
  `city` varchar(120) NOT NULL,
  `address` text NOT NULL,
  `activity_start_date` date DEFAULT NULL,
  `activity_end_date` date DEFAULT NULL,
  `field_photo_url` varchar(1000) DEFAULT NULL,
  `pic_user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `supervisor_user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `maps_url` varchar(1000) DEFAULT NULL,
  `contact_name` varchar(120) DEFAULT NULL,
  `contact_phone` varchar(30) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `competition_venues`
--

INSERT INTO `competition_venues` (`id`, `event_edition_id`, `slug`, `name`, `city`, `address`, `activity_start_date`, `activity_end_date`, `field_photo_url`, `pic_user_id`, `supervisor_user_id`, `maps_url`, `contact_name`, `contact_phone`, `notes`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 1, 'bogor', 'Sport Center UBSI Kampus Bogor A', 'Bogor', 'Jl. Raya Cilebut No.3a, RT.01/RW.04, RTO1 RW04, Tanah Sareal, Kota Bogor, Jawa Barat 16165', '2026-09-03', '2026-09-09', 'https://res.cloudinary.com/dishtnt6/image/upload/v1785706739/cilebut_dryrwi.jpg', 2, 10, 'https://maps.app.goo.gl/pL8dgFodCF5EEiLE8', NULL, NULL, NULL, 1, '2026-08-02 06:57:02', '2026-08-02 14:39:18'),
(2, 1, 'pontianak', 'Sport Center UBSI Kampus Pontianak', 'Pontianak', 'Jl. Abdul Rahman Saleh No.18, Bangka Belitung Laut, Kec. Pontianak Tenggara, Kota Pontianak, Kalimantan Barat 78124', '2026-09-30', '2026-10-06', 'https://res.cloudinary.com/dishtnt6/image/upload/v1785707258/BSI-Sport-Center-UBSI-Pontianak_npbbit.jpg', 2, 10, 'https://maps.app.goo.gl/msL1EtvNdGTUxyVu8', NULL, NULL, NULL, 1, '2026-08-02 06:57:02', '2026-08-02 14:47:49'),
(3, 1, 'jakarta', 'Sport Center UBSI Kampus Cengkareng', 'Jakarta', 'Jl. Kamal Raya No.18, RT.6/RW.3, Cengkareng Tim., Kecamatan Cengkareng, Kota Jakarta Barat, Daerah Khusus Ibukota Jakarta 11730', '2026-10-15', '2026-10-20', 'https://res.cloudinary.com/dishtnt6/image/upload/v1785706998/sport-center-1_jxehvs.jpg', 2, 10, 'https://maps.app.goo.gl/EaHkf2nkbWa2w8v56', NULL, NULL, NULL, 1, '2026-08-02 06:57:02', '2026-08-02 14:43:33'),
(4, 1, 'tegal', 'Sport Center UBSI Kampus Tegal', 'Tegal', 'Jl. Sipelem No.22, Kraton, Kec. Tegal Bar., Kota Tegal, Jawa Tengah 52112', '2026-11-12', '2026-11-17', 'https://res.cloudinary.com/dishtnt6/image/upload/v1785707323/Sportcenter-Universitas-BSI-Tegal_nnrq1q.jpg', 2, 10, 'https://maps.app.goo.gl/hkKcjLF65GemBm5J6', NULL, NULL, NULL, 1, '2026-08-02 06:57:02', '2026-08-02 14:48:52'),
(5, 1, 'tangerang-raya', 'Sport Center UBSI Kampus BSD', 'Tangerang Raya', 'BSD Sektor XIV Blok C1/1, Jl. Letnan Sutopo BSD Serpong Lengkong Gudang Timur, Rw. Mekar Jaya, Kec. Serpong, Kota Tangerang Selatan, Banten 15311', '2026-12-10', '2026-12-16', 'https://res.cloudinary.com/dishtnt6/image/upload/v1785707124/bsibsd_omhttx.jpg', 2, 10, 'https://maps.app.goo.gl/2ZQrNnaqobGqKzwf6', NULL, NULL, NULL, 1, '2026-08-02 06:57:02', '2026-08-02 14:45:36'),
(6, 1, 'bekasi', 'Sport Center UBSI Bekasi', 'Bekasi', 'Jl. Cut Mutia No.88, RT.001/RW.002, Sepanjang Jaya, Kec. Rawalumbu, Kota Bks, Jawa Barat 17113', '2027-01-13', '2027-01-19', 'https://res.cloudinary.com/dishtnt6/image/upload/v1785706917/bekasi_yoxokj.png', 2, 10, 'https://maps.app.goo.gl/dpVcemgqYvvcmRip8', NULL, NULL, NULL, 1, '2026-08-02 06:57:02', '2026-08-02 14:42:10'),
(7, 1, 'kaliabang', 'BSI Convention Center', 'Kaliabang', 'Jl. Kali Abang Tengah No.8, Perwira, Kec. Bekasi Utara, Kota Bks, Jawa Barat 17122', '2027-02-06', '2027-02-06', 'https://res.cloudinary.com/dishtnt6/image/upload/v1785707398/bsi-convention-center-3_purvlt.jpg', 2, 10, 'https://maps.app.goo.gl/mU8GbHSmMZ9Gc96p6', NULL, NULL, NULL, 1, '2026-08-02 06:57:02', '2026-08-02 14:50:19');

-- --------------------------------------------------------

--
-- Struktur dari tabel `event_editions`
--

CREATE TABLE `event_editions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `year` smallint(5) UNSIGNED NOT NULL,
  `name` varchar(160) NOT NULL,
  `slug` varchar(180) NOT NULL,
  `status` enum('draft','active','archived') NOT NULL DEFAULT 'draft',
  `starts_at` date DEFAULT NULL,
  `ends_at` date DEFAULT NULL,
  `activated_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `event_editions`
--

INSERT INTO `event_editions` (`id`, `year`, `name`, `slug`, `status`, `starts_at`, `ends_at`, `activated_at`, `created_at`, `updated_at`) VALUES
(1, 2027, 'BSI Flash 2027', 'bsi-flash-2027', 'active', NULL, NULL, '2026-08-02 15:39:02', '2026-08-02 15:39:02', '2026-08-02 15:39:02');

-- --------------------------------------------------------

--
-- Struktur dari tabel `failed_jobs`
--

CREATE TABLE `failed_jobs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `uuid` varchar(255) NOT NULL,
  `connection` text NOT NULL,
  `queue` text NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `jobs`
--

CREATE TABLE `jobs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` tinyint(3) UNSIGNED NOT NULL,
  `reserved_at` int(10) UNSIGNED DEFAULT NULL,
  `available_at` int(10) UNSIGNED NOT NULL,
  `created_at` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `job_batches`
--

CREATE TABLE `job_batches` (
  `id` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `total_jobs` int(11) NOT NULL,
  `pending_jobs` int(11) NOT NULL,
  `failed_jobs` int(11) NOT NULL,
  `failed_job_ids` longtext NOT NULL,
  `options` mediumtext DEFAULT NULL,
  `cancelled_at` int(11) DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `finished_at` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `judge_assignments`
--

CREATE TABLE `judge_assignments` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `competition_id` bigint(20) UNSIGNED NOT NULL,
  `registration_id` bigint(20) UNSIGNED NOT NULL,
  `judge_id` bigint(20) UNSIGNED NOT NULL,
  `assigned_by` bigint(20) UNSIGNED DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'draft',
  `notes` text DEFAULT NULL,
  `submitted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `judge_scores`
--

CREATE TABLE `judge_scores` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `judge_assignment_id` bigint(20) UNSIGNED NOT NULL,
  `judging_criterion_id` bigint(20) UNSIGNED NOT NULL,
  `score` decimal(8,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `judging_criteria`
--

CREATE TABLE `judging_criteria` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `competition_id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(160) NOT NULL,
  `description` text DEFAULT NULL,
  `max_score` decimal(8,2) NOT NULL DEFAULT 100.00,
  `sort_order` smallint(5) UNSIGNED NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `migrations`
--

CREATE TABLE `migrations` (
  `id` int(10) UNSIGNED NOT NULL,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `migrations`
--

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
(1, '0001_01_01_000000_create_users_table', 1),
(2, '0001_01_01_000001_create_cache_table', 1),
(3, '0001_01_01_000002_create_jobs_table', 1),
(4, '2026_06_27_000001_create_event_management_tables', 1),
(5, '2026_06_27_000002_add_competition_format_and_members', 2),
(6, '2026_06_27_000003_add_participant_accounts', 3),
(7, '2026_06_27_000004_add_account_status', 4),
(8, '2026_06_27_000005_add_pic_capacity_and_whatsapp', 5),
(9, '2026_06_28_000006_add_team_officials', 6),
(10, '2026_06_28_000007_add_member_contacts', 7),
(11, '2026_06_28_000008_add_custom_roles', 8),
(12, '2026_06_28_000009_update_competition_categories', 9),
(13, '2026_06_28_000008_add_nisn_verification_to_registration_members', 10),
(14, '2026_06_28_000009_create_site_contents_table', 11),
(15, '2026_06_28_000010_rename_demo_accounts_for_kreasi_unm', 12),
(16, '2026_06_28_000011_add_payment_verification_to_registrations', 13),
(17, '2026_06_28_000012_add_guides_to_competitions', 14),
(18, '2026_06_28_000013_create_competition_notifications_table', 15),
(19, '2026_06_28_000014_add_work_submission_fields', 16),
(20, '2026_06_28_000015_add_school_location_to_registrations', 17),
(21, '2026_06_28_000016_create_judging_tables', 18),
(22, '2026_06_29_000017_create_tournament_drawing_tables', 19),
(23, '2026_06_29_000018_add_match_scheduling', 20),
(24, '2026_06_29_000019_add_staged_registration_fields', 21),
(25, '2026_06_29_000020_allow_document_slots_before_team_data', 22),
(26, '2026_06_29_000021_add_downloadable_documents_to_competitions', 23),
(27, '2026_06_30_000022_add_whatsapp_group_url_to_competitions', 24),
(28, '2026_06_30_000023_rename_nova_ticket_prefix_to_kreasi', 25),
(29, '2026_07_02_000024_add_payment_account_to_competitions', 26),
(30, '2026_07_31_000025_rebrand_to_bsi_flash_2027', 27),
(31, '2026_07_31_000026_rebrand_registration_ticket_prefix', 28),
(32, '2026_08_02_000027_add_competition_sessions', 29),
(33, '2026_08_02_000028_expand_home_hero_description', 30),
(34, '2026_08_02_000029_create_competition_venues', 31),
(35, '2026_08_02_000030_split_global_and_city_competition_data', 32),
(36, '2026_08_02_000031_seed_initial_bsi_flash_cities', 33),
(37, '2026_08_02_000032_ensure_initial_bsi_flash_cities', 34),
(38, '2026_08_02_000033_add_city_pic_and_supervisor_assignments', 35),
(39, '2026_08_02_000034_add_multiple_city_staff_assignments', 36),
(40, '2026_08_03_000035_create_competition_types', 37),
(41, '2026_08_03_000036_create_event_editions', 38),
(42, '2026_08_03_000037_create_competition_results', 38);

-- --------------------------------------------------------

--
-- Struktur dari tabel `password_reset_tokens`
--

CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `registrations`
--

CREATE TABLE `registrations` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `event_edition_id` bigint(20) UNSIGNED DEFAULT NULL,
  `competition_id` bigint(20) UNSIGNED NOT NULL,
  `competition_session_id` bigint(20) UNSIGNED DEFAULT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `ticket_code` varchar(255) NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `whatsapp` varchar(20) NOT NULL,
  `email` varchar(255) NOT NULL,
  `birth_place` varchar(255) DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `grade` enum('X','XI','XII') DEFAULT NULL,
  `nisn` varchar(10) DEFAULT NULL,
  `mother_name` text DEFAULT NULL,
  `school_name` varchar(255) DEFAULT NULL,
  `school_city` varchar(120) DEFAULT NULL,
  `school_address` text DEFAULT NULL,
  `school_logo_path` varchar(255) DEFAULT NULL,
  `teacher_name` varchar(255) DEFAULT NULL,
  `teacher_contact` varchar(20) DEFAULT NULL,
  `school_code` varchar(255) DEFAULT NULL,
  `team_name` varchar(255) DEFAULT NULL,
  `participant_category` varchar(255) DEFAULT NULL,
  `student_card_path` varchar(255) DEFAULT NULL,
  `delegation_letter_path` varchar(255) DEFAULT NULL,
  `statement_letter_path` varchar(255) DEFAULT NULL,
  `photo_path` varchar(255) DEFAULT NULL,
  `payment_proof_path` varchar(255) DEFAULT NULL,
  `work_submission_url` text DEFAULT NULL,
  `work_submitted_at` timestamp NULL DEFAULT NULL,
  `work_verification_status` varchar(20) NOT NULL DEFAULT 'pending',
  `work_verification_note` text DEFAULT NULL,
  `work_verified_by` bigint(20) UNSIGNED DEFAULT NULL,
  `work_verified_at` timestamp NULL DEFAULT NULL,
  `consent` tinyint(1) NOT NULL DEFAULT 0,
  `team_completed_at` timestamp NULL DEFAULT NULL,
  `documents_completed_at` timestamp NULL DEFAULT NULL,
  `status` enum('pending','approved','rejected','revision') NOT NULL DEFAULT 'pending',
  `review_note` text DEFAULT NULL,
  `reviewed_by` bigint(20) UNSIGNED DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `payment_verified_at` timestamp NULL DEFAULT NULL,
  `payment_verified_by` bigint(20) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `registration_members`
--

CREATE TABLE `registration_members` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `registration_id` bigint(20) UNSIGNED NOT NULL,
  `competition_id` bigint(20) UNSIGNED NOT NULL,
  `member_order` tinyint(3) UNSIGNED NOT NULL,
  `full_name` varchar(255) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `whatsapp` varchar(20) DEFAULT NULL,
  `nisn` varchar(10) DEFAULT NULL,
  `birth_place` varchar(255) DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `grade` enum('X','XI','XII') DEFAULT NULL,
  `mother_name` text DEFAULT NULL,
  `student_card_path` varchar(255) DEFAULT NULL,
  `photo_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `nisn_verified_at` timestamp NULL DEFAULT NULL,
  `nisn_verified_by` bigint(20) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `registration_officials`
--

CREATE TABLE `registration_officials` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `registration_id` bigint(20) UNSIGNED NOT NULL,
  `official_order` tinyint(3) UNSIGNED NOT NULL,
  `full_name` varchar(120) NOT NULL,
  `position` varchar(80) NOT NULL,
  `whatsapp` varchar(20) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `sessions`
--

CREATE TABLE `sessions` (
  `id` varchar(255) NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` longtext NOT NULL,
  `last_activity` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `sessions`
--

INSERT INTO `sessions` (`id`, `user_id`, `ip_address`, `user_agent`, `payload`, `last_activity`) VALUES
('hqjTJe4IA6jlZXBVm5d7i28SfdFk4EMTPPFttuRF', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:153.0) Gecko/20100101 Firefox/153.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiSWxMVWhMemxES2hSTFNDSzd6a2hiOWlyelB6S3ZUdnlIZ3JZeXFBWiI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6OTY6Imh0dHA6Ly8xMjcuMC4wLjE6ODAwMC9zdG9yYWdlL3NpdGUtY29udGVudC9zcG9uc29ycy9YaDJTeVY1MmVKWXdyTk9mQXVtNFdFY29nQ25PdEs3VHQ5VWhXZm5TLnBuZyI7czo1OiJyb3V0ZSI7Tjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==', 1785741539),
('KsocQ2svhaZP4fZSnMBGLXsSGZ4iH8ui0aKtgRud', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:153.0) Gecko/20100101 Firefox/153.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiRUwxVG5Ta3JmSVlYSXNtdGxCdzNDcHpGRENZSnU1Unc0dW1lSUNFNiI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6OTY6Imh0dHA6Ly8xMjcuMC4wLjE6ODAwMC9zdG9yYWdlL3NpdGUtY29udGVudC9zcG9uc29ycy9ISndSTUFxdWFjeXY3RUFUWEhHdzcwV21URXA2ODV6Y2RxaTBBR1VGLmpwZyI7czo1OiJyb3V0ZSI7Tjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==', 1785741538),
('OR9xQwIquEPzrYbgWIOv53CrqggSisnHweLgXS0G', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:153.0) Gecko/20100101 Firefox/153.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoicVVheGUyZVZ6Z2xoUXNVd0dsZ2EzQzV0UDQ0MVBrQjZnbHJudzFMVyI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6OTY6Imh0dHA6Ly8xMjcuMC4wLjE6ODAwMC9zdG9yYWdlL3NpdGUtY29udGVudC9zcG9uc29ycy9ISndSTUFxdWFjeXY3RUFUWEhHdzcwV21URXA2ODV6Y2RxaTBBR1VGLmpwZyI7czo1OiJyb3V0ZSI7Tjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==', 1785703811),
('VsO2BwULXDKZFR5D4iPWWcllArF0Tz2uglXJbyg8', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:153.0) Gecko/20100101 Firefox/153.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiY1Q4ZDBDS0NJeklaQ0g1UjRTM2pNQnFnblFYSDVqZWJOQzhDOWtnZyI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6OTY6Imh0dHA6Ly8xMjcuMC4wLjE6ODAwMC9zdG9yYWdlL3NpdGUtY29udGVudC9zcG9uc29ycy9YaDJTeVY1MmVKWXdyTk9mQXVtNFdFY29nQ25PdEs3VHQ5VWhXZm5TLnBuZyI7czo1OiJyb3V0ZSI7Tjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==', 1785710708);

-- --------------------------------------------------------

--
-- Struktur dari tabel `site_contents`
--

CREATE TABLE `site_contents` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `event_edition_id` bigint(20) UNSIGNED DEFAULT NULL,
  `key` varchar(255) NOT NULL,
  `content` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`content`)),
  `updated_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `site_contents`
--

INSERT INTO `site_contents` (`id`, `event_edition_id`, `key`, `content`, `updated_by`, `created_at`, `updated_at`) VALUES
(1, 1, 'home_hero', '{\"badge\":\"FESTIVAL & LIGA ANTAR SEKOLAH\",\"title_primary\":\"BSI FLASH 2027\",\"title_accent\":\"UNLEASH YOUR POTENTIAL\",\"description\":\"BSI FLASH merupakan Festival & Liga Antar Sekolah SMA\\/SMK\\/MA Sederajat. Event ini diselenggarakan oleh Kampus UBSI sebagai wadah positif bagi generasi muda untuk menunjukan bakat, skill serta kemampuan dalam bidang Olahraga dan Talenta seperti Futsal, Volleyball, Esport, BSI Star, Kpop Dance dan LKBB. Universitas Bina Sarana Informatika berkolaborasi dengan pemerintah Kota\\/Kabupaten dalam menyelenggarakan event ini, selain itu event ini juga mendukung kreativitas serta kontribusi generasi muda bertalenta digital untuk Indonesia yang lebih baik\",\"primary_button_label\":\"Temukan Lomba\",\"primary_button_url\":\"\\/lomba\",\"secondary_button_label\":\"Login\",\"secondary_button_url\":\"\\/login\",\"hashtag\":\"#BSIFLASH2027\"}', 1, '2026-06-27 23:44:04', '2026-08-01 17:03:34'),
(2, 1, 'landing_extras', '{\"activity_title\":\"Cerita dari kegiatan sebelumnya\",\"activity_description\":\"Lihat kembali semangat, karya, dan momen terbaik para peserta.\",\"activity_interval\":5,\"activity_slides\":[{\"image_url\":\"https:\\/\\/res.cloudinary.com\\/di1ec1jxv\\/image\\/upload\\/v1782890373\\/7950AD49-7608-4E76-A287-3A3A76237E6B_wemazl.png\"},{\"image_url\":\"https:\\/\\/res.cloudinary.com\\/di1ec1jxv\\/image\\/upload\\/v1783169803\\/66d5c112-70ab-41ce-a140-3aafe5b26c67_eykmlt.png\"}],\"sponsor_title\":\"Didukung oleh\",\"sponsors\":[{\"name\":\"KIAN\",\"logo_url\":\"\\/storage\\/site-content\\/sponsors\\/HJwRMAquacyv7EATXHGw70WmTEp685zcdqi0AGUF.jpg\",\"website_url\":null},{\"name\":\"DICO\",\"logo_url\":\"\\/storage\\/site-content\\/sponsors\\/Xh2SyV52eJYwrNOfAum4WEcogCnOtK7Tt9UhWfnS.png\",\"website_url\":null}],\"media_partner_title\":\"Media Partners\",\"media_partners\":[]}', 1, '2026-06-28 02:45:27', '2026-07-04 05:57:15');

-- --------------------------------------------------------

--
-- Struktur dari tabel `tournament_draws`
--

CREATE TABLE `tournament_draws` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `competition_id` bigint(20) UNSIGNED NOT NULL,
  `operator_id` bigint(20) UNSIGNED DEFAULT NULL,
  `version` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `mode` varchar(20) NOT NULL,
  `format` varchar(40) NOT NULL,
  `settings` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`settings`)),
  `status` varchar(20) NOT NULL DEFAULT 'draft',
  `drawn_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `locked_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `tournament_draw_entries`
--

CREATE TABLE `tournament_draw_entries` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tournament_draw_id` bigint(20) UNSIGNED NOT NULL,
  `registration_id` bigint(20) UNSIGNED DEFAULT NULL,
  `slot_number` int(10) UNSIGNED NOT NULL,
  `seed_number` int(10) UNSIGNED DEFAULT NULL,
  `group_name` varchar(20) DEFAULT NULL,
  `is_bye` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `tournament_matches`
--

CREATE TABLE `tournament_matches` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tournament_draw_id` bigint(20) UNSIGNED NOT NULL,
  `stage` varchar(30) NOT NULL DEFAULT 'main',
  `round_number` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `round_label` varchar(80) NOT NULL,
  `match_number` int(10) UNSIGNED NOT NULL,
  `group_name` varchar(20) DEFAULT NULL,
  `participant_a_id` bigint(20) UNSIGNED DEFAULT NULL,
  `participant_b_id` bigint(20) UNSIGNED DEFAULT NULL,
  `source_a_match_id` bigint(20) UNSIGNED DEFAULT NULL,
  `source_a_outcome` varchar(10) DEFAULT NULL,
  `source_b_match_id` bigint(20) UNSIGNED DEFAULT NULL,
  `source_b_outcome` varchar(10) DEFAULT NULL,
  `score_a` decimal(8,2) DEFAULT NULL,
  `score_b` decimal(8,2) DEFAULT NULL,
  `winner_id` bigint(20) UNSIGNED DEFAULT NULL,
  `scheduled_at` datetime DEFAULT NULL,
  `duration_minutes` smallint(5) UNSIGNED NOT NULL DEFAULT 60,
  `venue` varchar(160) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'scheduled',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `tournament_schedule_blocks`
--

CREATE TABLE `tournament_schedule_blocks` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `competition_id` bigint(20) UNSIGNED NOT NULL,
  `tournament_draw_id` bigint(20) UNSIGNED DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `title` varchar(120) NOT NULL,
  `venue` varchar(160) NOT NULL,
  `starts_at` datetime NOT NULL,
  `duration_minutes` smallint(5) UNSIGNED NOT NULL DEFAULT 60,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `users`
--

CREATE TABLE `users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `whatsapp` varchar(20) DEFAULT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `role` varchar(80) NOT NULL DEFAULT 'participant',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `api_token` varchar(64) DEFAULT NULL,
  `competition_id` bigint(20) UNSIGNED DEFAULT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `whatsapp`, `email_verified_at`, `password`, `role`, `is_active`, `api_token`, `competition_id`, `remember_token`, `created_at`, `updated_at`) VALUES
(1, 'Super Admin', 'admin@bsiflash2027.id', NULL, NULL, '$2y$12$2HnkSsRuGC.xV/V9pKE8XeIRcr.z127e1zmDyc9BxJoRWi8cE8Qoe', 'super_admin', 1, 'af0542c274435987600b5ef730ea157cd07068d8b96b973096f2a712db3f2994', NULL, NULL, '2026-06-27 03:02:06', '2026-08-03 00:43:14'),
(2, 'Ade Kurniawan', 'pic@bsiflash2027.id', '081234567890', NULL, '$2y$12$qajOzla6Lzz5FvPGdQxdT.HjgzcpXql4y9hW9zU7D2fpsI/187MCO', 'pic', 1, NULL, NULL, NULL, '2026-06-27 03:02:06', '2026-07-31 05:47:54'),
(3, 'Perwakilan Basket QA', 'peserta.dashboard.qa@example.com', NULL, NULL, '$2y$12$..9RlOEOp5eF4VzvdGrtGefDNaEaQdtMqV8.hTYsARuip.ndb5b.q', 'participant', 1, '0fbba90f5a17c51aca10b7d2d2907c1a474c7043a31f1200637e537cf523fd52', NULL, NULL, '2026-06-27 03:44:23', '2026-06-27 03:44:37'),
(7, 'Syaifur Rahmatullah', 'syaifur.syl@bsi.ac.id', NULL, NULL, '$2y$12$N3WdKeCRopEmVPsXs6w22u2Kz928rJCJN8HLSGrPfW4bQ1DIZUtzW', 'participant', 1, NULL, NULL, NULL, '2026-06-27 04:58:24', '2026-06-27 05:00:43'),
(8, 'Syaifur Rahmatullah', 'syaifur.syl@gmail.com', NULL, NULL, '$2y$12$NyrlupVtleOde2VeediJjemRYgzGzflLShA.wNgk83v/4HfCMlWSq', 'participant', 1, NULL, NULL, NULL, '2026-06-27 18:41:52', '2026-06-28 21:13:18'),
(9, 'Syaifur Rahmatullah', 'syaifur1.syl@gmail.com', NULL, NULL, '$2y$12$ArKQcZv2S2zS.6Z1l8Lus.BcVjSlCjSQrZBD66dyvI0XpSkANwtle', 'participant', 1, NULL, NULL, NULL, '2026-06-29 00:27:45', '2026-06-29 01:13:10'),
(10, 'Yanto', 'yanto.ytx@bsi.ac.id', '083151779925', NULL, '$2y$12$3tNcBm5mi7yhiscoItKPmur9zaFVZWxNf98FkrXeoK4FNcjuo4Kca', 'spv', 1, NULL, NULL, NULL, '2026-08-02 14:15:50', '2026-08-02 14:15:50');

--
-- Indexes for dumped tables
--

--
-- Indeks untuk tabel `access_roles`
--
ALTER TABLE `access_roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `access_roles_slug_unique` (`slug`);

--
-- Indeks untuk tabel `cache`
--
ALTER TABLE `cache`
  ADD PRIMARY KEY (`key`),
  ADD KEY `cache_expiration_index` (`expiration`);

--
-- Indeks untuk tabel `cache_locks`
--
ALTER TABLE `cache_locks`
  ADD PRIMARY KEY (`key`),
  ADD KEY `cache_locks_expiration_index` (`expiration`);

--
-- Indeks untuk tabel `competitions`
--
ALTER TABLE `competitions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `competitions_slug_unique` (`slug`),
  ADD KEY `competitions_competition_type_id_foreign` (`competition_type_id`),
  ADD KEY `competitions_event_edition_id_is_featured_index` (`event_edition_id`,`is_featured`);

--
-- Indeks untuk tabel `competition_notifications`
--
ALTER TABLE `competition_notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `competition_notifications_competition_id_foreign` (`competition_id`),
  ADD KEY `competition_notifications_author_id_foreign` (`author_id`),
  ADD KEY `competition_notifications_event_edition_id_published_at_index` (`event_edition_id`,`published_at`);

--
-- Indeks untuk tabel `competition_results`
--
ALTER TABLE `competition_results`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `competition_results_rank_unique` (`competition_id`,`competition_session_id`,`rank`,`source`),
  ADD KEY `competition_results_competition_session_id_foreign` (`competition_session_id`),
  ADD KEY `competition_results_registration_id_foreign` (`registration_id`),
  ADD KEY `competition_results_competition_id_source_rank_index` (`competition_id`,`source`,`rank`);

--
-- Indeks untuk tabel `competition_sessions`
--
ALTER TABLE `competition_sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `competition_sessions_competition_id_is_active_sort_order_index` (`competition_id`,`is_active`,`sort_order`),
  ADD KEY `competition_sessions_venue_id_foreign` (`venue_id`),
  ADD KEY `competition_sessions_pic_user_id_foreign` (`pic_user_id`),
  ADD KEY `competition_sessions_supervisor_user_id_foreign` (`supervisor_user_id`);

--
-- Indeks untuk tabel `competition_session_staff`
--
ALTER TABLE `competition_session_staff`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `competition_session_staff_competition_session_id_user_id_unique` (`competition_session_id`,`user_id`),
  ADD KEY `competition_session_staff_user_id_foreign` (`user_id`),
  ADD KEY `competition_session_staff_competition_session_id_role_index` (`competition_session_id`,`role`);

--
-- Indeks untuk tabel `competition_types`
--
ALTER TABLE `competition_types`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `competition_types_name_unique` (`name`),
  ADD UNIQUE KEY `competition_types_slug_unique` (`slug`),
  ADD KEY `competition_types_is_active_sort_order_index` (`is_active`,`sort_order`);

--
-- Indeks untuk tabel `competition_venues`
--
ALTER TABLE `competition_venues`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `competition_venues_slug_unique` (`slug`),
  ADD KEY `competition_venues_is_active_city_index` (`is_active`,`city`),
  ADD KEY `competition_venues_pic_user_id_foreign` (`pic_user_id`),
  ADD KEY `competition_venues_supervisor_user_id_foreign` (`supervisor_user_id`),
  ADD KEY `competition_venues_event_edition_id_is_active_city_index` (`event_edition_id`,`is_active`,`city`);

--
-- Indeks untuk tabel `event_editions`
--
ALTER TABLE `event_editions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `event_editions_year_unique` (`year`),
  ADD UNIQUE KEY `event_editions_slug_unique` (`slug`);

--
-- Indeks untuk tabel `failed_jobs`
--
ALTER TABLE `failed_jobs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`);

--
-- Indeks untuk tabel `jobs`
--
ALTER TABLE `jobs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `jobs_queue_index` (`queue`);

--
-- Indeks untuk tabel `job_batches`
--
ALTER TABLE `job_batches`
  ADD PRIMARY KEY (`id`);

--
-- Indeks untuk tabel `judge_assignments`
--
ALTER TABLE `judge_assignments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `judge_assignments_registration_id_judge_id_unique` (`registration_id`,`judge_id`),
  ADD KEY `judge_assignments_competition_id_foreign` (`competition_id`),
  ADD KEY `judge_assignments_judge_id_foreign` (`judge_id`),
  ADD KEY `judge_assignments_assigned_by_foreign` (`assigned_by`);

--
-- Indeks untuk tabel `judge_scores`
--
ALTER TABLE `judge_scores`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `judge_scores_judge_assignment_id_judging_criterion_id_unique` (`judge_assignment_id`,`judging_criterion_id`),
  ADD KEY `judge_scores_judging_criterion_id_foreign` (`judging_criterion_id`);

--
-- Indeks untuk tabel `judging_criteria`
--
ALTER TABLE `judging_criteria`
  ADD PRIMARY KEY (`id`),
  ADD KEY `judging_criteria_competition_id_foreign` (`competition_id`);

--
-- Indeks untuk tabel `migrations`
--
ALTER TABLE `migrations`
  ADD PRIMARY KEY (`id`);

--
-- Indeks untuk tabel `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD PRIMARY KEY (`email`);

--
-- Indeks untuk tabel `registrations`
--
ALTER TABLE `registrations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `registrations_ticket_code_unique` (`ticket_code`),
  ADD UNIQUE KEY `registrations_competition_id_nisn_unique` (`competition_id`,`nisn`),
  ADD KEY `registrations_reviewed_by_foreign` (`reviewed_by`),
  ADD KEY `registrations_competition_id_status_index` (`competition_id`,`status`),
  ADD KEY `registrations_user_id_status_index` (`user_id`,`status`),
  ADD KEY `registrations_payment_verified_by_foreign` (`payment_verified_by`),
  ADD KEY `registrations_work_verified_by_foreign` (`work_verified_by`),
  ADD KEY `registrations_competition_session_id_foreign` (`competition_session_id`),
  ADD KEY `registrations_event_edition_id_status_index` (`event_edition_id`,`status`);

--
-- Indeks untuk tabel `registration_members`
--
ALTER TABLE `registration_members`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `registration_members_registration_id_member_order_unique` (`registration_id`,`member_order`),
  ADD UNIQUE KEY `registration_members_competition_id_nisn_unique` (`competition_id`,`nisn`),
  ADD KEY `registration_members_nisn_verified_by_foreign` (`nisn_verified_by`);

--
-- Indeks untuk tabel `registration_officials`
--
ALTER TABLE `registration_officials`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `registration_officials_registration_id_official_order_unique` (`registration_id`,`official_order`);

--
-- Indeks untuk tabel `sessions`
--
ALTER TABLE `sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sessions_user_id_index` (`user_id`),
  ADD KEY `sessions_last_activity_index` (`last_activity`);

--
-- Indeks untuk tabel `site_contents`
--
ALTER TABLE `site_contents`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `site_contents_event_edition_id_key_unique` (`event_edition_id`,`key`),
  ADD KEY `site_contents_updated_by_foreign` (`updated_by`);

--
-- Indeks untuk tabel `tournament_draws`
--
ALTER TABLE `tournament_draws`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tournament_draws_competition_id_version_unique` (`competition_id`,`version`),
  ADD KEY `tournament_draws_operator_id_foreign` (`operator_id`);

--
-- Indeks untuk tabel `tournament_draw_entries`
--
ALTER TABLE `tournament_draw_entries`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tournament_draw_entries_tournament_draw_id_slot_number_unique` (`tournament_draw_id`,`slot_number`),
  ADD KEY `tournament_draw_entries_registration_id_foreign` (`registration_id`);

--
-- Indeks untuk tabel `tournament_matches`
--
ALTER TABLE `tournament_matches`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tournament_matches_tournament_draw_id_match_number_unique` (`tournament_draw_id`,`match_number`),
  ADD KEY `tournament_matches_participant_a_id_foreign` (`participant_a_id`),
  ADD KEY `tournament_matches_participant_b_id_foreign` (`participant_b_id`),
  ADD KEY `tournament_matches_source_a_match_id_foreign` (`source_a_match_id`),
  ADD KEY `tournament_matches_source_b_match_id_foreign` (`source_b_match_id`),
  ADD KEY `tournament_matches_winner_id_foreign` (`winner_id`);

--
-- Indeks untuk tabel `tournament_schedule_blocks`
--
ALTER TABLE `tournament_schedule_blocks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tournament_schedule_blocks_competition_id_foreign` (`competition_id`),
  ADD KEY `tournament_schedule_blocks_tournament_draw_id_foreign` (`tournament_draw_id`),
  ADD KEY `tournament_schedule_blocks_created_by_foreign` (`created_by`);

--
-- Indeks untuk tabel `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `users_email_unique` (`email`),
  ADD UNIQUE KEY `users_api_token_unique` (`api_token`),
  ADD KEY `users_competition_id_foreign` (`competition_id`);

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `access_roles`
--
ALTER TABLE `access_roles`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT untuk tabel `competitions`
--
ALTER TABLE `competitions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT untuk tabel `competition_notifications`
--
ALTER TABLE `competition_notifications`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `competition_results`
--
ALTER TABLE `competition_results`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `competition_sessions`
--
ALTER TABLE `competition_sessions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT untuk tabel `competition_session_staff`
--
ALTER TABLE `competition_session_staff`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT untuk tabel `competition_types`
--
ALTER TABLE `competition_types`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT untuk tabel `competition_venues`
--
ALTER TABLE `competition_venues`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT untuk tabel `event_editions`
--
ALTER TABLE `event_editions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT untuk tabel `failed_jobs`
--
ALTER TABLE `failed_jobs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `jobs`
--
ALTER TABLE `jobs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `judge_assignments`
--
ALTER TABLE `judge_assignments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `judge_scores`
--
ALTER TABLE `judge_scores`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `judging_criteria`
--
ALTER TABLE `judging_criteria`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `migrations`
--
ALTER TABLE `migrations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

--
-- AUTO_INCREMENT untuk tabel `registrations`
--
ALTER TABLE `registrations`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT untuk tabel `registration_members`
--
ALTER TABLE `registration_members`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT untuk tabel `registration_officials`
--
ALTER TABLE `registration_officials`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT untuk tabel `site_contents`
--
ALTER TABLE `site_contents`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT untuk tabel `tournament_draws`
--
ALTER TABLE `tournament_draws`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `tournament_draw_entries`
--
ALTER TABLE `tournament_draw_entries`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `tournament_matches`
--
ALTER TABLE `tournament_matches`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `tournament_schedule_blocks`
--
ALTER TABLE `tournament_schedule_blocks`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- Ketidakleluasaan untuk tabel pelimpahan (Dumped Tables)
--

--
-- Ketidakleluasaan untuk tabel `competitions`
--
ALTER TABLE `competitions`
  ADD CONSTRAINT `competitions_competition_type_id_foreign` FOREIGN KEY (`competition_type_id`) REFERENCES `competition_types` (`id`),
  ADD CONSTRAINT `competitions_event_edition_id_foreign` FOREIGN KEY (`event_edition_id`) REFERENCES `event_editions` (`id`);

--
-- Ketidakleluasaan untuk tabel `competition_notifications`
--
ALTER TABLE `competition_notifications`
  ADD CONSTRAINT `competition_notifications_author_id_foreign` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `competition_notifications_competition_id_foreign` FOREIGN KEY (`competition_id`) REFERENCES `competitions` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `competition_notifications_event_edition_id_foreign` FOREIGN KEY (`event_edition_id`) REFERENCES `event_editions` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `competition_results`
--
ALTER TABLE `competition_results`
  ADD CONSTRAINT `competition_results_competition_id_foreign` FOREIGN KEY (`competition_id`) REFERENCES `competitions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `competition_results_competition_session_id_foreign` FOREIGN KEY (`competition_session_id`) REFERENCES `competition_sessions` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `competition_results_registration_id_foreign` FOREIGN KEY (`registration_id`) REFERENCES `registrations` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `competition_sessions`
--
ALTER TABLE `competition_sessions`
  ADD CONSTRAINT `competition_sessions_competition_id_foreign` FOREIGN KEY (`competition_id`) REFERENCES `competitions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `competition_sessions_pic_user_id_foreign` FOREIGN KEY (`pic_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `competition_sessions_supervisor_user_id_foreign` FOREIGN KEY (`supervisor_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `competition_sessions_venue_id_foreign` FOREIGN KEY (`venue_id`) REFERENCES `competition_venues` (`id`) ON DELETE SET NULL;

--
-- Ketidakleluasaan untuk tabel `competition_session_staff`
--
ALTER TABLE `competition_session_staff`
  ADD CONSTRAINT `competition_session_staff_competition_session_id_foreign` FOREIGN KEY (`competition_session_id`) REFERENCES `competition_sessions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `competition_session_staff_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `competition_venues`
--
ALTER TABLE `competition_venues`
  ADD CONSTRAINT `competition_venues_event_edition_id_foreign` FOREIGN KEY (`event_edition_id`) REFERENCES `event_editions` (`id`),
  ADD CONSTRAINT `competition_venues_pic_user_id_foreign` FOREIGN KEY (`pic_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `competition_venues_supervisor_user_id_foreign` FOREIGN KEY (`supervisor_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Ketidakleluasaan untuk tabel `judge_assignments`
--
ALTER TABLE `judge_assignments`
  ADD CONSTRAINT `judge_assignments_assigned_by_foreign` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `judge_assignments_competition_id_foreign` FOREIGN KEY (`competition_id`) REFERENCES `competitions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `judge_assignments_judge_id_foreign` FOREIGN KEY (`judge_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `judge_assignments_registration_id_foreign` FOREIGN KEY (`registration_id`) REFERENCES `registrations` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `judge_scores`
--
ALTER TABLE `judge_scores`
  ADD CONSTRAINT `judge_scores_judge_assignment_id_foreign` FOREIGN KEY (`judge_assignment_id`) REFERENCES `judge_assignments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `judge_scores_judging_criterion_id_foreign` FOREIGN KEY (`judging_criterion_id`) REFERENCES `judging_criteria` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `judging_criteria`
--
ALTER TABLE `judging_criteria`
  ADD CONSTRAINT `judging_criteria_competition_id_foreign` FOREIGN KEY (`competition_id`) REFERENCES `competitions` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `registrations`
--
ALTER TABLE `registrations`
  ADD CONSTRAINT `registrations_competition_id_foreign` FOREIGN KEY (`competition_id`) REFERENCES `competitions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `registrations_competition_session_id_foreign` FOREIGN KEY (`competition_session_id`) REFERENCES `competition_sessions` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `registrations_event_edition_id_foreign` FOREIGN KEY (`event_edition_id`) REFERENCES `event_editions` (`id`),
  ADD CONSTRAINT `registrations_payment_verified_by_foreign` FOREIGN KEY (`payment_verified_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `registrations_reviewed_by_foreign` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `registrations_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `registrations_work_verified_by_foreign` FOREIGN KEY (`work_verified_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Ketidakleluasaan untuk tabel `registration_members`
--
ALTER TABLE `registration_members`
  ADD CONSTRAINT `registration_members_competition_id_foreign` FOREIGN KEY (`competition_id`) REFERENCES `competitions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `registration_members_nisn_verified_by_foreign` FOREIGN KEY (`nisn_verified_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `registration_members_registration_id_foreign` FOREIGN KEY (`registration_id`) REFERENCES `registrations` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `registration_officials`
--
ALTER TABLE `registration_officials`
  ADD CONSTRAINT `registration_officials_registration_id_foreign` FOREIGN KEY (`registration_id`) REFERENCES `registrations` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `site_contents`
--
ALTER TABLE `site_contents`
  ADD CONSTRAINT `site_contents_event_edition_id_foreign` FOREIGN KEY (`event_edition_id`) REFERENCES `event_editions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `site_contents_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Ketidakleluasaan untuk tabel `tournament_draws`
--
ALTER TABLE `tournament_draws`
  ADD CONSTRAINT `tournament_draws_competition_id_foreign` FOREIGN KEY (`competition_id`) REFERENCES `competitions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tournament_draws_operator_id_foreign` FOREIGN KEY (`operator_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Ketidakleluasaan untuk tabel `tournament_draw_entries`
--
ALTER TABLE `tournament_draw_entries`
  ADD CONSTRAINT `tournament_draw_entries_registration_id_foreign` FOREIGN KEY (`registration_id`) REFERENCES `registrations` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tournament_draw_entries_tournament_draw_id_foreign` FOREIGN KEY (`tournament_draw_id`) REFERENCES `tournament_draws` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `tournament_matches`
--
ALTER TABLE `tournament_matches`
  ADD CONSTRAINT `tournament_matches_participant_a_id_foreign` FOREIGN KEY (`participant_a_id`) REFERENCES `registrations` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `tournament_matches_participant_b_id_foreign` FOREIGN KEY (`participant_b_id`) REFERENCES `registrations` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `tournament_matches_source_a_match_id_foreign` FOREIGN KEY (`source_a_match_id`) REFERENCES `tournament_matches` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `tournament_matches_source_b_match_id_foreign` FOREIGN KEY (`source_b_match_id`) REFERENCES `tournament_matches` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `tournament_matches_tournament_draw_id_foreign` FOREIGN KEY (`tournament_draw_id`) REFERENCES `tournament_draws` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tournament_matches_winner_id_foreign` FOREIGN KEY (`winner_id`) REFERENCES `registrations` (`id`) ON DELETE SET NULL;

--
-- Ketidakleluasaan untuk tabel `tournament_schedule_blocks`
--
ALTER TABLE `tournament_schedule_blocks`
  ADD CONSTRAINT `tournament_schedule_blocks_competition_id_foreign` FOREIGN KEY (`competition_id`) REFERENCES `competitions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tournament_schedule_blocks_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `tournament_schedule_blocks_tournament_draw_id_foreign` FOREIGN KEY (`tournament_draw_id`) REFERENCES `tournament_draws` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_competition_id_foreign` FOREIGN KEY (`competition_id`) REFERENCES `competitions` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
