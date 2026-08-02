<?php

use Illuminate\Support\Facades\Route;

Route::get('/{path?}', function () {
    return response()->file(base_path('../frontend/dist/index.html'));
})->where('path', '^(?!api(?:/|$)).*');
