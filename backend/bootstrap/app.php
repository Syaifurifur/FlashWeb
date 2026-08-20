<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\ApiTokenAuth;
use App\Http\Middleware\NormalizeApiErrors;
use App\Http\Middleware\RoleMiddleware;
use App\Http\Middleware\PermissionMiddleware;
use App\Support\ApiErrorResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'api.auth' => ApiTokenAuth::class,
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
        ]);
        $middleware->appendToGroup('api', NormalizeApiErrors::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $isApi = fn (Request $request): bool => $request->is('api/*') || $request->expectsJson();

        $exceptions->render(function (ModelNotFoundException $exception, Request $request) use ($isApi) {
            if ($isApi($request)) {
                return ApiErrorResponse::make($request, 'Data yang dicari tidak ditemukan.', 404, 'DATA_NOT_FOUND', exception: $exception);
            }
        });

        $exceptions->render(function (AuthorizationException $exception, Request $request) use ($isApi) {
            if ($isApi($request)) {
                return ApiErrorResponse::make($request, 'Anda tidak memiliki izin untuk melakukan tindakan ini.', 403, 'ACCESS_DENIED', exception: $exception);
            }
        });

        $exceptions->render(function (ValidationException $exception, Request $request) use ($isApi) {
            if ($isApi($request)) {
                return ApiErrorResponse::make(
                    $request,
                    'Data belum dapat diproses. Periksa kolom yang ditandai.',
                    422,
                    'VALIDATION_ERROR',
                    $exception->errors(),
                    $exception,
                );
            }
        });

        $exceptions->render(function (HttpExceptionInterface $exception, Request $request) use ($isApi) {
            if (! $isApi($request)) return null;

            $status = $exception->getStatusCode();
            $message = trim($exception->getMessage());
            $genericMessages = [
                400 => 'Permintaan tidak dapat diproses.',
                401 => 'Silakan masuk untuk melanjutkan.',
                403 => 'Anda tidak memiliki akses ke fitur ini.',
                404 => 'Halaman atau data yang dicari tidak ditemukan.',
                405 => 'Metode permintaan tidak didukung.',
                408 => 'Waktu permintaan telah habis. Silakan coba lagi.',
                409 => 'Permintaan bertentangan dengan data yang tersedia.',
                419 => 'Sesi telah berakhir. Silakan muat ulang halaman.',
                422 => 'Data yang dikirim tidak dapat diproses.',
                429 => 'Terlalu banyak permintaan. Silakan coba lagi beberapa saat.',
                500 => 'Terjadi kesalahan pada server. Silakan coba lagi.',
                503 => 'Layanan sedang tidak tersedia. Silakan coba lagi nanti.',
            ];
            $defaultEnglish = ['Bad Request', 'Unauthorized', 'Forbidden', 'Not Found', 'Method Not Allowed', 'Unprocessable Content', 'Too Many Requests', 'Server Error', 'Service Unavailable'];
            if (str_starts_with($message, 'No query results for model')) {
                $message = 'Data yang dicari tidak ditemukan.';
            } elseif ($message === '' || in_array($message, $defaultEnglish, true) || preg_match('/^The route .+ could not be found\.$/i', $message)) {
                $message = $genericMessages[$status] ?? 'Permintaan tidak dapat diproses.';
            }

            $code = $status === 404 && $message === 'Data yang dicari tidak ditemukan.' ? 'DATA_NOT_FOUND' : null;
            return ApiErrorResponse::make($request, $message, $status, $code, exception: $exception);
        });

        $exceptions->render(function (\Throwable $exception, Request $request) use ($isApi) {
            if ($isApi($request)) {
                return ApiErrorResponse::make(
                    $request,
                    'Terjadi kesalahan pada server. Catat ID error lalu coba kembali.',
                    500,
                    'SERVER_ERROR',
                    exception: $exception,
                );
            }
        });
    })->create();
