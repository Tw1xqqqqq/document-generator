<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Template;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\MakesDocxFiles;
use Tests\TestCase;

/**
 * Ответы на ошибки: пользователь должен понять, что произошло,
 * а посторонний не должен узнать устройство приложения.
 */
class ErrorHandlingTest extends TestCase
{
    use MakesDocxFiles;
    use RefreshDatabase;

    #[Test]
    public function missing_record_returns_neutral_message(): void
    {
        $response = $this->getJson('/api/documents/9999');

        $response->assertNotFound()->assertJson(['message' => 'Запись не найдена.']);

        // В ответе не должно быть имени класса модели: стандартное сообщение
        // Laravel выдаёт «No query results for model [App\Models\Document]»
        $response->assertJsonMissing(['message' => 'No query results for model [App\Models\Document] 9999']);
        $this->assertStringNotContainsString('App\\Models', $response->getContent());
    }

    #[Test]
    public function unknown_route_returns_json_not_html(): void
    {
        $this->getJson('/api/unknown-endpoint')
            ->assertNotFound()
            ->assertJson(['message' => 'Запись не найдена.']);
    }

    #[Test]
    public function converter_failure_is_explained_to_user(): void
    {
        Storage::fake('templates');
        Storage::fake('documents');

        // Конвертер недоступен
        Http::fake(['*/forms/libreoffice/convert' => Http::response('', 503)]);

        $this->postJson('/api/templates', [
            'name' => 'Счёт',
            'file' => $this->makeDocx(['Клиент: ${client_name}']),
        ])->assertCreated();

        $template = Template::first();
        $organization = Organization::factory()->create();

        $response = $this->postJson('/api/documents', [
            'template_id' => $template->id,
            'organization_id' => $organization->id,
            'data' => ['client_name' => 'ИП Сидоров'],
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('конвертации', $response->json('message'));

        // Незавершённый документ в журнал не попадает
        $this->assertDatabaseCount('documents', 0);
    }
}
