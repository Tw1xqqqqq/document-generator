<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Organization;
use App\Models\Template;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\MakesDocxFiles;
use Tests\TestCase;

class DocumentGenerationTest extends TestCase
{
    use MakesDocxFiles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('templates');
        Storage::fake('documents');

        // Конвертер подменяем заглушкой: тесты не должны зависеть
        // от запущенного контейнера Gotenberg и ждать LibreOffice
        Http::fake([
            '*/forms/libreoffice/convert' => Http::response('%PDF-1.7 fake pdf', 200),
        ]);
    }

    private function makeTemplate(?array $lines = null): Template
    {
        $this->postJson('/api/templates', [
            'name' => 'Счёт',
            'file' => $this->makeDocx($lines ?? [
                'Поставщик: ${org.full_name}, ИНН ${org.inn}',
                'Счёт № ${doc.number} от ${doc.date}',
                'Клиент: ${client_name}',
                'Сумма: ${total_amount}',
            ]),
        ])->assertCreated();

        return Template::with(['fields', 'currentVersion'])->first();
    }

    #[Test]
    public function it_generates_document_with_organization_details(): void
    {
        $template = $this->makeTemplate();
        $organization = Organization::factory()->create([
            'full_name' => 'ООО «Ромашка»',
            'inn' => '7701234567',
        ]);

        $response = $this->postJson('/api/documents', [
            'template_id' => $template->id,
            'organization_id' => $organization->id,
            'name' => 'Счёт № 1',
            'data' => [
                'doc.number' => '1',
                'doc.date' => '2026-09-17',
                'client_name' => 'ИП Сидоров',
                'total_amount' => '15000',
            ],
        ]);

        $response->assertCreated();

        $document = Document::first();
        $text = $this->docxText(Storage::disk('documents')->path($document->docx_path));

        // Реквизиты организации подставились сами
        $this->assertStringContainsString('ООО «Ромашка»', $text);
        $this->assertStringContainsString('7701234567', $text);

        // Дата приведена к привычному виду, число отформатировано
        $this->assertStringContainsString('17.09.2026', $text);
        $this->assertStringContainsString('15 000', $text);

        // Незаменённых меток не осталось
        $this->assertStringNotContainsString('${', $text);

        // PDF тоже сохранён
        Storage::disk('documents')->assertExists($document->pdf_path);
    }

    #[Test]
    public function it_escapes_special_characters(): void
    {
        $template = $this->makeTemplate(['Клиент: ${client_name}']);
        $organization = Organization::factory()->create();

        $this->postJson('/api/documents', [
            'template_id' => $template->id,
            'organization_id' => $organization->id,
            'data' => ['client_name' => 'ООО «Иванов & Партнёры» <дочернее>'],
        ])->assertCreated();

        $document = Document::first();
        $path = Storage::disk('documents')->path($document->docx_path);

        // Главное: файл остаётся читаемым архивом с корректным xml,
        // то есть Word его откроет
        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($path) === true);

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        $this->assertNotFalse(simplexml_load_string($xml), 'Документ должен остаться корректным XML');
        $this->assertStringContainsString('&amp;', $xml);
    }

    #[Test]
    public function it_requires_fields_marked_as_required(): void
    {
        $template = $this->makeTemplate();
        $organization = Organization::factory()->create();

        $response = $this->postJson('/api/documents', [
            'template_id' => $template->id,
            'organization_id' => $organization->id,
            'data' => ['client_name' => ''],
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['data.client_name']);
        $this->assertDatabaseCount('documents', 0);
    }

    #[Test]
    public function it_downloads_generated_files(): void
    {
        $template = $this->makeTemplate();
        $organization = Organization::factory()->create();

        $this->postJson('/api/documents', [
            'template_id' => $template->id,
            'organization_id' => $organization->id,
            'data' => ['client_name' => 'ИП Сидоров', 'total_amount' => '100'],
        ])->assertCreated();

        $document = Document::first();

        $this->get("/api/documents/{$document->id}/download?format=pdf")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->get("/api/documents/{$document->id}/download?format=docx")->assertOk();
        $this->get("/api/documents/{$document->id}/download?format=exe")->assertStatus(422);
    }

    #[Test]
    public function document_keeps_link_to_version_it_was_made_from(): void
    {
        $template = $this->makeTemplate(['Клиент: ${client_name}']);
        $organization = Organization::factory()->create();

        $this->postJson('/api/documents', [
            'template_id' => $template->id,
            'organization_id' => $organization->id,
            'data' => ['client_name' => 'ИП Сидоров'],
        ])->assertCreated();

        $firstVersionId = $template->current_version_id;

        // Шаблон изменили уже после выпуска документа
        $this->postJson("/api/templates/{$template->id}/versions", [
            'file' => $this->makeDocx(['Заказчик: ${client_name}', 'Новое поле ${extra}']),
        ])->assertCreated();

        // Документ по-прежнему ссылается на ту версию, по которой создан
        $this->assertSame($firstVersionId, Document::first()->template_version_id);
    }
}
