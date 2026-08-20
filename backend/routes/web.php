<?php

use Illuminate\Support\Facades\Route;

Route::get('/{path?}', function (?string $path = null) {
    $publicIndex = public_path('index.html');
    $frontendDist = realpath(base_path('../frontend/dist'));

    if ($frontendDist && $path) {
        $requestedFile = realpath($frontendDist.DIRECTORY_SEPARATOR.$path);
        if ($requestedFile
            && str_starts_with($requestedFile, $frontendDist.DIRECTORY_SEPARATOR)
            && is_file($requestedFile)) {
            return response()->file($requestedFile);
        }
    }

    $index = is_file($publicIndex) ? $publicIndex : ($frontendDist ? $frontendDist.DIRECTORY_SEPARATOR.'index.html' : null);
    abort_unless($index && is_file($index), 503, 'Frontend belum dibangun. Jalankan npm run build pada folder frontend.');

    return response()->file($index);
})->where('path', '^(?!api(?:/|$)).*');
