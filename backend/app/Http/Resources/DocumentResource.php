<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'data' => $this->data,

            'template_id' => $this->template_id,
            'template' => TemplateResource::make($this->whenLoaded('template')),
            'template_version' => TemplateVersionResource::make($this->whenLoaded('templateVersion')),

            'organization_id' => $this->organization_id,
            'organization' => OrganizationResource::make($this->whenLoaded('organization')),

            // Ссылки на скачивание: файлы отдаются только через API
            'pdf_url' => $this->pdf_path ? url("/api/documents/{$this->id}/download?format=pdf") : null,
            'docx_url' => $this->docx_path ? url("/api/documents/{$this->id}/download?format=docx") : null,

            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
