<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Карточка шаблона печатной формы.
 * Сам файл docx хранится не здесь, а в template_versions:
 * шаблон может пережить несколько замен файла, и история должна сохраняться.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('templates', function (Blueprint $table) {
            $table->id();

            // null = общий шаблон, доступный всем организациям
            $table->foreignId('organization_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->string('name');
            $table->string('description')->nullable();

            // Ссылка на актуальную версию файла. Внешний ключ добавим
            // отдельной миграцией, так как таблица версий создаётся позже.
            $table->unsignedBigInteger('current_version_id')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('templates');
    }
};
