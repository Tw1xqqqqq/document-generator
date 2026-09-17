<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TemplateVersionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'version' => $this->version,
            'original_name' => $this->original_name,
            'size' => $this->size,
            'placeholders' => $this->placeholders ?? [],
            'comment' => $this->comment,
            'is_current' => $this->id === $this->template?->current_version_id,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
