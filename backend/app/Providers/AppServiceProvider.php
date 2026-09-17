<?php

namespace App\Providers;

use App\Services\Documents\GotenbergClient;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Регистрация сервисов приложения.
     */
    public function register(): void
    {
        // Клиент конвертации создаётся один раз и получает настройки из config/documents.php.
        // Благодаря этому его можно требовать в конструкторе любого класса,
        // а в тестах — подменить заглушкой.
        $this->app->singleton(GotenbergClient::class, fn () => new GotenbergClient(
            baseUrl: config('documents.gotenberg_url'),
            timeout: config('documents.gotenberg_timeout'),
        ));
    }

    public function boot(): void
    {
        //
    }
}
