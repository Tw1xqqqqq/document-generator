<?php

use App\Http\Controllers\DocumentController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\TemplateController;
use App\Http\Controllers\TemplateFieldController;
use App\Http\Controllers\TemplateVersionController;
use App\Services\Documents\GotenbergClient;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API-маршруты
|--------------------------------------------------------------------------
| Все маршруты этого файла автоматически получают префикс /api.
*/

// Проверка работоспособности: заодно показывает, доступен ли конвертер
Route::get('/health', fn (GotenbergClient $gotenberg) => response()->json([
    'status' => 'ok',
    'service' => 'document-generator',
    'converter' => $gotenberg->isAvailable() ? 'ok' : 'unavailable',
    'time' => now()->toIso8601String(),
]));

// Организации
Route::apiResource('organizations', OrganizationController::class);

// Шаблоны печатных форм
Route::apiResource('templates', TemplateController::class);

// Версии файла шаблона
Route::post('templates/{template}/versions', [TemplateVersionController::class, 'store']);
Route::get('templates/{template}/versions/{version}/download', [TemplateVersionController::class, 'download']);
Route::post('templates/{template}/versions/{version}/restore', [TemplateVersionController::class, 'restore']);

// Настройка полей формы
Route::get('templates/{template}/fields', [TemplateFieldController::class, 'index']);
Route::put('templates/{template}/fields', [TemplateFieldController::class, 'update']);

// Документы: генерация, журнал, скачивание
Route::post('documents/preview', [DocumentController::class, 'preview']);
Route::get('documents/{document}/download', [DocumentController::class, 'download']);
Route::apiResource('documents', DocumentController::class)->except(['update']);
