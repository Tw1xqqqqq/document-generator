<?php

namespace Tests\Feature;

use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OrganizationApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_creates_organization(): void
    {
        $response = $this->postJson('/api/organizations', [
            'name' => 'ООО «Ромашка»',
            'inn' => '7701234567',
            'kpp' => '770101001',
            'director_name' => 'Иванов И. И.',
        ]);

        $response->assertCreated()->assertJsonPath('data.name', 'ООО «Ромашка»');
        $this->assertDatabaseHas('organizations', ['inn' => '7701234567']);
    }

    #[Test]
    public function it_validates_russian_requisites(): void
    {
        $response = $this->postJson('/api/organizations', [
            'name' => 'ООО «Ромашка»',
            'inn' => '123',                    // должно быть 10 или 12 цифр
            'bank_account' => '40702810',      // должно быть 20 цифр
            'bank_bik' => 'abc',               // должно быть 9 цифр
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['inn', 'bank_account', 'bank_bik']);
    }

    #[Test]
    public function it_requires_name(): void
    {
        $this->postJson('/api/organizations', ['inn' => '7701234567'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('name');
    }

    #[Test]
    public function it_updates_and_deletes_organization(): void
    {
        $organization = Organization::factory()->create();

        $this->putJson("/api/organizations/{$organization->id}", [
            'name' => 'Новое название',
        ])->assertOk()->assertJsonPath('data.name', 'Новое название');

        $this->deleteJson("/api/organizations/{$organization->id}")->assertNoContent();

        $this->assertDatabaseCount('organizations', 0);
    }

    #[Test]
    public function it_counts_related_entities_in_list(): void
    {
        Organization::factory()->count(2)->create();

        $this->getJson('/api/organizations')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.templates_count', 0)
            ->assertJsonPath('data.0.documents_count', 0);
    }
}
