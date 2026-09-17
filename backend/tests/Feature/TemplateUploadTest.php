<?php

namespace Tests\Feature;

use App\Models\Template;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\MakesDocxFiles;
use Tests\TestCase;

class TemplateUploadTest extends TestCase
{
    use MakesDocxFiles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Файлы пишутся во временное хранилище и не засоряют проект
        Storage::fake('templates');
    }

    #[Test]
    public function it_uploads_template_and_detects_placeholders(): void
    {
        $file = $this->makeDocx([
            'Счёт от ${org.full_name}',
            'Клиент: ${client_name}, ИНН ${client_inn}',
            'Сумма: ${total_amount}',
        ]);

        $response = $this->postJson('/api/templates', [
            'name' => 'Счёт',
            'description' => 'Тестовый шаблон',
            'file' => $file,
        ]);

        $response->assertCreated();

        $template = Template::with(['currentVersion', 'fields'])->first();

        // Метки найдены все
        $this->assertEqualsCanonicalizing(
            ['org.full_name', 'client_name', 'client_inn', 'total_amount'],
            $template->currentVersion->placeholders,
        );

        // Полями формы стали только пользовательские метки:
        // реквизиты организации сервис подставляет сам
        $this->assertEqualsCanonicalizing(
            ['client_name', 'client_inn', 'total_amount'],
            $template->fields->pluck('key')->all(),
        );

        // Тип поля угадан по названию
        $this->assertSame('number', $template->fields->firstWhere('key', 'total_amount')->type);
    }

    #[Test]
    public function it_rejects_files_that_are_not_docx(): void
    {
        $response = $this->postJson('/api/templates', [
            'name' => 'Не документ',
            'file' => UploadedFile::fake()->create('scan.pdf', 100, 'application/pdf'),
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('file');
        $this->assertDatabaseCount('templates', 0);
    }

    #[Test]
    public function new_version_keeps_configured_labels_and_adds_new_fields(): void
    {
        $this->postJson('/api/templates', [
            'name' => 'Акт',
            'file' => $this->makeDocx(['Клиент ${client_name}']),
        ])->assertCreated();

        $template = Template::first();

        // Пользователь настроил подпись поля
        $this->putJson("/api/templates/{$template->id}/fields", [
            'fields' => [[
                'key' => 'client_name',
                'label' => 'Наименование заказчика',
                'type' => 'text',
                'required' => true,
            ]],
        ])->assertOk();

        // Загружаем новую версию файла, где появилась ещё одна метка
        $this->postJson("/api/templates/{$template->id}/versions", [
            'file' => $this->makeDocx(['Клиент ${client_name}', 'Договор ${contract_number}']),
            'comment' => 'Добавлен номер договора',
        ])->assertCreated();

        $template->refresh()->load('fields');

        // Настроенная подпись не потерялась
        $this->assertSame(
            'Наименование заказчика',
            $template->fields->firstWhere('key', 'client_name')->label,
        );

        // Новая метка стала новым полем
        $this->assertNotNull($template->fields->firstWhere('key', 'contract_number'));

        // Версий стало две, актуальна вторая
        $this->assertCount(2, $template->versions);
        $this->assertSame(2, $template->currentVersion->version);
    }

    #[Test]
    public function fields_disappear_when_placeholder_is_removed_from_file(): void
    {
        $this->postJson('/api/templates', [
            'name' => 'Договор',
            'file' => $this->makeDocx(['${client_name}', '${old_field}']),
        ])->assertCreated();

        $template = Template::first();
        $this->assertCount(2, $template->fields);

        $this->postJson("/api/templates/{$template->id}/versions", [
            'file' => $this->makeDocx(['${client_name}']),
        ])->assertCreated();

        $this->assertSame(['client_name'], $template->fresh()->fields->pluck('key')->all());
    }
}
