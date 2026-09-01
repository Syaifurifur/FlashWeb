<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class ApiErrorResponse
{
    public static function make(
        Request $request,
        string $message,
        int $status,
        ?string $code = null,
        array $errors = [],
        ?Throwable $exception = null,
        array $extra = [],
    ): JsonResponse {
        return response()->json(self::payload($request, $message, $status, $code, $errors, $exception, $extra), $status);
    }

    public static function payload(
        Request $request,
        string $message,
        int $status,
        ?string $code = null,
        array $errors = [],
        ?Throwable $exception = null,
        array $extra = [],
    ): array {
        $errorId = 'ERR-'.now()->format('Ymd-His').'-'.Str::upper(Str::random(6));
        $path = '/'.$request->path();
        $error = [
            'id' => $errorId,
            'code' => $code ?: self::codeForStatus($status),
            'status' => $status,
            'detected_at' => now()->toIso8601String(),
            'location' => [
                'module' => self::moduleForPath($path),
                'endpoint' => $request->method().' '.$path,
                'path' => $path,
            ],
            'fields' => self::fields($errors),
        ];

        if ($exception && config('app.debug')) {
            $basePath = str_replace('\\', '/', base_path()).'/';
            $file = str_replace('\\', '/', $exception->getFile());
            $error['technical'] = [
                'exception' => class_basename($exception),
                'file' => str_starts_with($file, $basePath) ? substr($file, strlen($basePath)) : basename($file),
                'line' => $exception->getLine(),
                'message' => $exception->getMessage(),
            ];
        }

        if ($status >= 500) {
            $context = [
                'error_id' => $errorId,
                'endpoint' => $error['location']['endpoint'],
            ];
            if ($exception) $context['exception'] = $exception;
            Log::error("[{$errorId}] {$message}", $context);
        }

        return array_filter([
            'message' => $message,
            'errors' => $errors ?: null,
            'error' => $error,
            ...$extra,
        ], fn ($value) => $value !== null);
    }

    public static function codeForStatus(int $status): string
    {
        return match ($status) {
            400 => 'BAD_REQUEST',
            401 => 'AUTHENTICATION_REQUIRED',
            403 => 'ACCESS_DENIED',
            404 => 'NOT_FOUND',
            405 => 'METHOD_NOT_ALLOWED',
            408 => 'REQUEST_TIMEOUT',
            409 => 'DATA_CONFLICT',
            419 => 'SESSION_EXPIRED',
            422 => 'UNPROCESSABLE_DATA',
            429 => 'RATE_LIMITED',
            500 => 'SERVER_ERROR',
            503 => 'SERVICE_UNAVAILABLE',
            default => 'REQUEST_FAILED',
        };
    }

    private static function fields(array $errors): array
    {
        return collect($errors)->map(fn ($messages, $field) => [
            'key' => $field,
            'label' => self::fieldLabel($field),
            'messages' => array_values((array) $messages),
        ])->values()->all();
    }

    private static function fieldLabel(string $field): string
    {
        $labels = [
            'name' => 'Nama', 'email' => 'Email', 'password' => 'Kata sandi',
            'password_confirmation' => 'Konfirmasi kata sandi', 'whatsapp' => 'Nomor WhatsApp',
            'role' => 'Role', 'competition_id' => 'Lomba', 'competition_session_id' => 'Lokasi dan jadwal',
            'venue_id' => 'Tempat', 'competition_venue_id' => 'Tempat BSI Flash', 'pic_ids' => 'PIC', 'supervisor_ids' => 'SPV',
            'supporter_ticket_price' => 'Harga tiket supporter', 'supporter_bank_name' => 'Nama bank',
            'supporter_bank_account_number' => 'Nomor rekening', 'supporter_bank_account_holder' => 'Nama pemilik rekening',
            'supporter_payment_note' => 'Catatan pembayaran',
            'title' => 'Judul', 'message' => 'Pesan', 'full_name' => 'Nama lengkap',
            'school_name' => 'Asal sekolah', 'team_name' => 'Nama tim', 'nisn' => 'NISN',
            'student_card' => 'Kartu pelajar', 'delegation_letter' => 'Surat delegasi',
            'photo' => 'Pas foto', 'payment_proof' => 'Bukti pembayaran', 'sessions' => 'Sesi kota',
            'assignments' => 'Penugasan', 'members' => 'Anggota tim', 'officials' => 'Official',
        ];

        return collect(explode('.', $field))->map(function ($part) use ($labels) {
            if (ctype_digit($part)) return 'Data '.((int) $part + 1);
            return $labels[$part] ?? Str::headline($part);
        })->implode(' › ');
    }

    private static function moduleForPath(string $path): string
    {
        return match (true) {
            preg_match('#^/api/(login|logout|forgot-password|reset-password)#', $path) === 1 => 'Autentikasi akun',
            str_contains($path, '/manage/accounts') || str_contains($path, '/manage/roles') => 'Kelola akun dan akses',
            str_contains($path, '/manage/venues') || str_contains($path, '/manage/city-staff') || str_contains($path, '/api/venues') => 'Tempat dan kota',
            str_contains($path, '/manage/registrations') || str_contains($path, '/participant/') || $path === '/api/registrations' => 'Pendaftaran peserta',
            str_contains($path, '/manage/notifications') => 'Notifikasi',
            str_contains($path, '/manage/judging') || str_contains($path, '/judge/') => 'Penilaian',
            str_contains($path, '/manage/tournaments') => 'Drawing dan bagan',
            str_contains($path, '/manage/schedules') => 'Jadwal pertandingan',
            str_contains($path, '/manage/content') || str_contains($path, '/api/content') => 'Konten website',
            str_contains($path, '/manage/competitions') || str_contains($path, '/api/competitions') => 'Manajemen lomba',
            str_contains($path, '/manage/editions') || str_contains($path, '/api/editions') => 'Tahun kegiatan',
            default => 'Layanan aplikasi',
        };
    }
}
