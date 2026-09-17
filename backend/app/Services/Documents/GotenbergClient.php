<?php

namespace App\Services\Documents;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Клиент сервиса конвертации Gotenberg (внутри него LibreOffice).
 *
 * Почему отдельный сервис, а не библиотека внутри PHP: конвертацию делает
 * настоящий LibreOffice, поэтому вёрстка docx сохраняется — шрифты, таблицы,
 * колонтитулы. PHP-библиотеки (dompdf и подобные) собирают PDF заново
 * и ломают оформление печатных форм.
 */
class GotenbergClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly int $timeout = 120,
    ) {}

    /**
     * Принимает путь к docx, возвращает содержимое готового PDF.
     */
    public function convertToPdf(string $docxPath, string $fileName = 'document.docx'): string
    {
        if (! is_file($docxPath)) {
            throw new RuntimeException("Файл для конвертации не найден: {$docxPath}");
        }

        $response = Http::timeout($this->timeout)
            ->attach('files', file_get_contents($docxPath), $fileName)
            ->post(rtrim($this->baseUrl, '/').'/forms/libreoffice/convert');

        if ($response->failed()) {
            throw new RuntimeException(
                'Сервис конвертации вернул ошибку '.$response->status().'. '
                .'Проверьте, что контейнер gotenberg запущен.'
            );
        }

        return $response->body();
    }

    /** Доступен ли сервис конвертации — используется в /api/health. */
    public function isAvailable(): bool
    {
        try {
            return Http::timeout(5)
                ->get(rtrim($this->baseUrl, '/').'/health')
                ->successful();
        } catch (\Throwable) {
            return false;
        }
    }
}
