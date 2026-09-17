<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Настройка полей шаблона: как метка из docx выглядит в форме заполнения.
 * Ключи создаются автоматически при разборе файла, а подпись, тип
 * и обязательность задаёт пользователь в карточке шаблона.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('template_fields', function (Blueprint $table) {
            $table->id();

            $table->foreignId('template_id')->constrained()->cascadeOnDelete();

            $table->string('key');                        // как в docx: client_name
            $table->string('label');                      // подпись в форме: Наименование клиента
            $table->string('type')->default('text');      // text | textarea | date | number
            $table->boolean('required')->default(false);
            $table->string('default_value')->nullable();
            $table->string('hint')->nullable();           // подсказка под полем
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();

            $table->unique(['template_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('template_fields');
    }
};
