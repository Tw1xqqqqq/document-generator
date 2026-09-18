<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;
use ZipArchive;

/**
 * Проверка, что загружен настоящий docx.
 *
 * Почему не стандартное правило mimes:docx - оно определяет тип по
 * содержимому через finfo, а тот в зависимости от системы называет docx
 * то application/zip, то application/octet-stream. Проверять по заголовку
 * из браузера нельзя: его легко подделать.
 *
 * Поэтому смотрим в сам файл: docx это zip-архив, внутри которого
 * обязательно есть word/document.xml. Такую проверку не обойти
 * переименованием, и она одинаково работает в любой системе.
 */
class DocxFile implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile) {
            $fail('Загрузите файл шаблона.');

            return;
        }

        if (strtolower($value->getClientOriginalExtension()) !== 'docx') {
            $fail('Шаблон должен быть в формате docx (Word 2007 и новее).');

            return;
        }

        $zip = new ZipArchive;

        if ($zip->open($value->getPathname()) !== true) {
            $fail('Файл повреждён или не является документом Word.');

            return;
        }

        $hasDocument = $zip->locateName('word/document.xml') !== false;
        $zip->close();

        if (! $hasDocument) {
            $fail('Файл не похож на документ Word. Пересохраните его в формате docx.');
        }
    }
}
