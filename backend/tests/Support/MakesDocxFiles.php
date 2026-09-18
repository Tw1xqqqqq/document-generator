<?php

namespace Tests\Support;

use Illuminate\Http\UploadedFile;
use PhpOffice\PhpWord\PhpWord;

/**
 * Помощник для тестов: собирает настоящий docx с нужными метками.
 * Работать с реальным файлом важно - именно на нём проверяется разбор
 * и подстановка, подделка ничего бы не доказала.
 */
trait MakesDocxFiles
{
    /**
     * @param  list<string>  $lines  строки документа, можно с метками ${...}
     */
    protected function makeDocx(array $lines, string $fileName = 'template.docx'): UploadedFile
    {
        $word = new PhpWord;
        $section = $word->addSection();

        foreach ($lines as $line) {
            $section->addText($line);
        }

        $path = tempnam(sys_get_temp_dir(), 'test_').'.docx';
        $word->save($path, 'Word2007');

        // Последний аргумент переводит объект в тестовый режим:
        // файл не пришёл из HTTP-запроса, но ведёт себя так же
        return new UploadedFile($path, $fileName, null, null, true);
    }

    /** Извлекает текст из docx, чтобы проверить результат подстановки. */
    protected function docxText(string $absolutePath): string
    {
        $zip = new \ZipArchive;
        $zip->open($absolutePath);
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        return strip_tags(str_replace('</w:p>', "\n", $xml));
    }
}
