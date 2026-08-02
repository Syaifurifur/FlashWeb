<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccessRole extends Model
{
    protected $guarded = [];

    protected function casts(): array { return ['permissions'=>'array']; }

    public const PERMISSIONS = [
        'dashboard.view' => 'Melihat ringkasan dashboard',
        'registrations.view' => 'Melihat data pendaftar',
        'registrations.review' => 'Memverifikasi dan memberi keputusan',
        'registrations.export' => 'Mengekspor data pendaftar',
        'competitions.view' => 'Melihat manajemen lomba',
        'competitions.edit' => 'Mengubah lomba yang ditugaskan',
        'competitions.format' => 'Mengatur format peserta dan official',
        'competitions.manage' => 'Membuat, mengubah, dan menghapus lomba',
        'notifications.manage' => 'Mengirim notifikasi kepada peserta',
        'judging.manage' => 'Mengelola verifikasi dan proses penilaian',
        'judging.score' => 'Menilai karya yang ditugaskan',
        'tournaments.manage' => 'Mengelola drawing dan bagan pertandingan',
        'accounts.manage' => 'Mengelola akun pengguna',
        'roles.manage' => 'Membuat dan mengubah role',
        'content.manage' => 'Mengubah konten halaman website',
    ];
}
