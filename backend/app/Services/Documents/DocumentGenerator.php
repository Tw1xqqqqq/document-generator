<?php

namespace App\Services\Documents;

use App\Models\Document;
use App\Models\Organization;
use App\Models\Template;
use App\Models\TemplateVersion;
use App\Services\Templates\TemplateService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpWord\TemplateProcessor;
use RuntimeException;

/**
 * Создание документа: подстановка значений в docx и конвертация в PDF.
 */
class DocumentGenerator
{
    public function __construct(
        private readonly TemplateService $templates,
        private readonly GotenbergClient $gotenberg,
    ) {}

    /**
     * Полный цикл: заполнить шаблон, сделать PDF, сохранить оба файла
     * и записать документ в журнал.
     *
     * @param  array<string, mixed>  $data  значения полей формы
     */
    public function generate(Template $template, Organization $organization, array $data, ?string $name = null): Document
    {
        $version = $this->resolveVersion($template);

        $docxTempPath = $this->renderDocx($version, $this->buildValues($template, $organization, $data));

        try {
            $pdfContent = $this->gotenberg->convertToPdf($docxTempPath, 'document.docx');

            // Папка на документ: файлы не перемешиваются и удаляются одним махом
            $folder = 'doc_'.Str::uuid();
            $docxPath = "{$folder}/document.docx";
            $pdfPath = "{$folder}/document.pdf";

            Storage::disk('documents')->put($docxPath, file_get_contents($docxTempPath));
            Storage::disk('documents')->put($pdfPath, $pdfContent);

            return Document::create([
                'template_id' => $template->id,
                'template_version_id' => $version->id,
                'organization_id' => $organization->id,
                'name' => $name ?: $this->defaultName($template),
                'data' => $data,
                'docx_path' => $docxPath,
                'pdf_path' => $pdfPath,
            ]);
        } finally {
            // Временный файл убираем в любом случае, даже если конвертация упала
            @unlink($docxTempPath);
        }
    }

    /**
     * Предпросмотр: тот же PDF, но без сохранения в журнал.
     * Используется на экране генерации и для проверки шаблона.
     *
     * @return string содержимое PDF
     */
    public function preview(Template $template, Organization $organization, array $data): string
    {
        $version = $this->resolveVersion($template);

        $docxTempPath = $this->renderDocx($version, $this->buildValues($template, $organization, $data));

        try {
            return $this->gotenberg->convertToPdf($docxTempPath, 'preview.docx');
        } finally {
            @unlink($docxTempPath);
        }
    }

    /**
     * Собирает итоговый набор значений для подстановки.
     *
     * Порядок важен: сначала реквизиты организации и данные документа,
     * затем пользовательские поля. И в конце — пустые значения для меток,
     * которые никто не заполнил, иначе в готовом файле останется ${...}.
     *
     * @return array<string, string>
     */
    public function buildValues(Template $template, Organization $organization, array $data): array
    {
        $values = $organization->toPlaceholders();

        // Служебные метки документа
        $values['doc.number'] = (string) ($data['doc.number'] ?? '');
        $values['doc.date'] = $this->formatDate($data['doc.date'] ?? now());

        // Пользовательские поля — с учётом типа и значения по умолчанию
        foreach ($template->fields as $field) {
            $raw = $data[$field->key] ?? $field->default_value ?? '';

            $values[$field->key] = match ($field->type) {
                'date' => $raw === '' ? '' : $this->formatDate($raw),
                'number' => $this->formatNumber($raw),
                default => (string) $raw,
            };
        }

        // Метки, оставшиеся без значения, заменяем пустотой
        foreach ($this->resolveVersion($template)->placeholders ?? [] as $placeholder) {
            $values[$placeholder] ??= '';
        }

        return $values;
    }

    /**
     * Подстановка значений в файл шаблона.
     *
     * @return string путь к временному docx
     */
    private function renderDocx(TemplateVersion $version, array $values): string
    {
        $processor = new TemplateProcessor($this->templates->absolutePath($version));

        foreach ($values as $key => $value) {
            $processor->setValue($key, $this->escape((string) $value));
        }

        $tempPath = tempnam(sys_get_temp_dir(), 'docgen_').'.docx';
        $processor->saveAs($tempPath);

        return $tempPath;
    }

    /**
     * Экранирование значения для вставки в XML документа.
     *
     * Без этого символы & и < ломают файл, и Word отказывается его открывать.
     * Переносы строк превращаем в настоящие переносы Word, иначе
     * многострочный адрес склеится в одну строку.
     */
    private function escape(string $value): string
    {
        $escaped = htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');

        return str_replace(["\r\n", "\n", "\r"], '</w:t><w:br/><w:t>', $escaped);
    }

    private function formatDate(mixed $value): string
    {
        if ($value instanceof Carbon) {
            return $value->format(config('documents.date_format'));
        }

        try {
            return Carbon::parse((string) $value)->format(config('documents.date_format'));
        } catch (\Throwable) {
            // Если это не дата, оставляем как ввели: пусть лучше будет текст,
            // чем ошибка генерации документа
            return (string) $value;
        }
    }

    /** Числа в печатных формах привычнее с пробелами: 1 234 567,89 */
    private function formatNumber(mixed $value): string
    {
        if ($value === '' || $value === null) {
            return '';
        }

        if (! is_numeric($value)) {
            return (string) $value;
        }

        return number_format((float) $value, floor((float) $value) == $value ? 0 : 2, ',', ' ');
    }

    private function resolveVersion(Template $template): TemplateVersion
    {
        $version = $template->currentVersion;

        if (! $version) {
            throw new RuntimeException('У шаблона нет загруженного файла.');
        }

        return $version;
    }

    private function defaultName(Template $template): string
    {
        return sprintf('%s от %s', $template->name, now()->format('d.m.Y'));
    }
}
