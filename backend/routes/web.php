<?php

use Illuminate\Support\Facades\Route;

// Интерфейс отдаёт nginx (собранное React-приложение),
// поэтому Laravel отвечает только за API.
Route::get('/', fn () => response()->json([
    'service' => 'document-generator API',
    'docs' => '/api/health',
]));
