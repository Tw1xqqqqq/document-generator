<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Журнал выпущенных документов.
 * Храним и введённые данные (data), и готовые файлы: документ можно
 * скачать повторно или пересоздать, не заполняя форму заново.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();

            $table->foreignId('template_id')->constrained()->cascadeOnDelete();
            // Версия шаблона, по которой создан документ: шаблон могли изменить позже
            $table->foreignId('template_version_id')->constrained('template_versions')->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();

            $table->string('name');                   // «Счёт № 12 от 17.09.2026»
            $table->json('data');                     // значения полей формы

            $table->string('docx_path')->nullable();
            $table->string('pdf_path')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
