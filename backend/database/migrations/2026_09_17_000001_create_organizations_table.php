<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Организации, от имени которых выпускаются документы.
 * Все поля этой таблицы доступны в шаблоне как метки ${org.*}
 * (например ${org.inn}), поэтому реквизиты хранятся отдельными колонками,
 * а не одним json - так их проще подставлять и валидировать.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();

            $table->string('name');                       // краткое название: ООО «Ромашка»
            $table->string('full_name')->nullable();      // полное наименование

            $table->string('inn', 12)->nullable();
            $table->string('kpp', 9)->nullable();
            $table->string('ogrn', 15)->nullable();

            $table->string('legal_address')->nullable();
            $table->string('actual_address')->nullable();

            $table->string('phone', 50)->nullable();
            $table->string('email')->nullable();

            $table->string('bank_name')->nullable();
            $table->string('bank_account', 34)->nullable();       // расчётный счёт
            $table->string('bank_corr_account', 34)->nullable();  // корреспондентский счёт
            $table->string('bank_bik', 9)->nullable();

            $table->string('director_name')->nullable();
            $table->string('director_position')->nullable()->default('Генеральный директор');

            $table->string('logo_path')->nullable();      // путь к файлу логотипа в storage

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
