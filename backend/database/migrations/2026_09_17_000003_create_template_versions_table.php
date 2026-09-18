<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Версии файла шаблона. Каждая загрузка docx создаёт новую запись,
 * старые файлы остаются на месте - по ним были выпущены документы.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('template_versions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('template_id')->constrained()->cascadeOnDelete();

            $table->unsignedInteger('version');          // 1, 2, 3...
            $table->string('file_path');                 // путь в storage/app/templates
            $table->string('original_name');             // как файл назывался у пользователя
            $table->unsignedBigInteger('size');          // размер в байтах

            // Метки, найденные в документе: ["client_name", "date", ...]
            $table->json('placeholders')->nullable();

            $table->string('comment')->nullable();       // что изменилось в этой версии

            $table->timestamps();

            $table->unique(['template_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('template_versions');
    }
};
