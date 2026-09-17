<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Точка входа для наполнения базы демонстрационными данными.
 * Запускается автоматически при старте контейнера (artisan migrate --seed).
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            OrganizationSeeder::class,
            TemplateSeeder::class,
        ]);
    }
}
