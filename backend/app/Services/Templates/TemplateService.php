<?php

namespace App\Services\Templates;

use App\Models\Template;
use App\Models\TemplateVersion;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Загрузка шаблонов и работа с их версиями.
 *
 * Вся составная логика собрана здесь, а не в контроллере: загрузка это
 * несколько связанных действий (сохранить файл, создать версию, разобрать
 * метки, обновить поля), и они должны выполняться целиком или никак.
 */
class TemplateService
{
    public function __construct(
        private readonly PlaceholderExtractor $extractor,
    ) {}

    /** Новый шаблон вместе с первой версией файла. */
    public function create(array $attributes, UploadedFile $file): Template
    {
        return DB::transaction(function () use ($attributes, $file) {
            $template = Template::create([
                'organization_id' => $attributes['organization_id'] ?? null,
                'name' => $attributes['name'],
                'description' => $attributes['description'] ?? null,
            ]);

            $this->addVersion($template, $file, $attributes['comment'] ?? 'Первая загрузка');

            return $template->fresh(['currentVersion', 'fields', 'organization']);
        });
    }

    /**
     * Новая версия файла для существующего шаблона.
     * Старые версии остаются: по ним уже могли быть выпущены документы.
     */
    public function addVersion(Template $template, UploadedFile $file, ?string $comment = null): TemplateVersion
    {
        return DB::transaction(function () use ($template, $file, $comment) {
            $nextVersion = (int) $template->versions()->max('version') + 1;

            // Имя файла на диске делаем безопасным и уникальным,
            // а оригинальное название сохраняем в базе для показа пользователю.
            $fileName = sprintf('v%d_%s.docx', $nextVersion, Str::random(8));
            $path = $file->storeAs("template_{$template->id}", $fileName, 'templates');

            $placeholders = $this->extractor->extract(
                Storage::disk('templates')->path($path)
            );

            $version = $template->versions()->create([
                'version' => $nextVersion,
                'file_path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
                'placeholders' => $placeholders,
                'comment' => $comment,
            ]);

            // Новая версия сразу становится актуальной
            $template->update(['current_version_id' => $version->id]);

            $this->syncFields($template, $placeholders);

            return $version;
        });
    }

    /**
     * Приводит список полей шаблона в соответствие с метками файла.
     *
     * Настройки уже существующих полей (подпись, тип, обязательность)
     * сохраняются - иначе при замене файла пользователь терял бы свою работу.
     * Поля, которых больше нет в документе, удаляются: форма строится
     * по актуальной версии.
     */
    public function syncFields(Template $template, array $placeholders): void
    {
        $userPlaceholders = $this->extractor->split($placeholders)['user'];

        $existing = $template->fields()->pluck('id', 'key');

        foreach ($userPlaceholders as $index => $key) {
            if ($existing->has($key)) {
                continue;
            }

            $template->fields()->create([
                'key' => $key,
                'label' => $this->extractor->humanize($key),
                'type' => $this->guessType($key),
                'required' => true,
                'sort_order' => $index,
            ]);
        }

        // Удаляем поля, которых нет в новой версии файла
        $template->fields()
            ->whereNotIn('key', $userPlaceholders)
            ->delete();
    }

    /** Абсолютный путь к файлу версии - нужен PhpWord и конвертеру. */
    public function absolutePath(TemplateVersion $version): string
    {
        return Storage::disk('templates')->path($version->file_path);
    }

    /** Удаление шаблона вместе со всеми файлами версий. */
    public function delete(Template $template): void
    {
        DB::transaction(function () use ($template) {
            Storage::disk('templates')->deleteDirectory("template_{$template->id}");
            $template->delete();
        });
    }

    /**
     * Предположение о типе поля по его названию.
     * Это лишь удобная подсказка: тип всегда можно изменить в карточке шаблона.
     */
    private function guessType(string $key): string
    {
        $key = mb_strtolower($key);

        return match (true) {
            str_contains($key, 'date') || str_contains($key, 'дата') => 'date',
            str_contains($key, 'sum') || str_contains($key, 'amount')
                || str_contains($key, 'price') || str_contains($key, 'count') => 'number',
            str_contains($key, 'address') || str_contains($key, 'comment')
                || str_contains($key, 'description') => 'textarea',
            default => 'text',
        };
    }
}
