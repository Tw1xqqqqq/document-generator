<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Exceptions\DocumentGenerationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Сбои генерации понятны пользователю (нет файла шаблона, недоступен
        // конвертер), поэтому их текст отдаём как есть. Остальные исключения
        // в боевом режиме скрыты за общим «Server Error».
        $exceptions->render(function (DocumentGenerationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => $e->getMessage()], 422);
            }
        });

        // Стандартный ответ Laravel содержит имя класса модели
        // («No query results for model [App\Models\Document] 9999»),
        // что подсказывает устройство приложения. Отдаём нейтральный текст.
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => 'Запись не найдена.'], 404);
            }
        });
    })->create();
