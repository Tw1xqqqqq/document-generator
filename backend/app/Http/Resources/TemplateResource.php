<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TemplateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'organization_id' => $this->organization_id,
            'organization' => OrganizationResource::make($this->whenLoaded('organization')),

            // Актуальная версия файла: по ней строится форма заполнения
            'current_version' => TemplateVersionResource::make($this->whenLoaded('currentVersion')),

            // История версий и настройки полей приходят только в карточке шаблона,
            // чтобы список шаблонов оставался лёгким
            'versions' => TemplateVersionResource::collection($this->whenLoaded('versions')),
            'fields' => TemplateFieldResource::collection($this->whenLoaded('fields')),

            'documents_count' => $this->whenCounted('documents'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
