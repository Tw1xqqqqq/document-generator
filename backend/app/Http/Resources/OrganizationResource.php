<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * Превращает организацию в JSON для фронтенда.
 * Отдаём не путь к файлу логотипа, а готовую ссылку на картинку.
 */
class OrganizationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'full_name' => $this->full_name,
            'inn' => $this->inn,
            'kpp' => $this->kpp,
            'ogrn' => $this->ogrn,
            'legal_address' => $this->legal_address,
            'actual_address' => $this->actual_address,
            'phone' => $this->phone,
            'email' => $this->email,
            'bank_name' => $this->bank_name,
            'bank_account' => $this->bank_account,
            'bank_corr_account' => $this->bank_corr_account,
            'bank_bik' => $this->bank_bik,
            'director_name' => $this->director_name,
            'director_position' => $this->director_position,
            'logo_url' => $this->logo_path ? Storage::disk('public')->url($this->logo_path) : null,
            'templates_count' => $this->whenCounted('templates'),
            'documents_count' => $this->whenCounted('documents'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
