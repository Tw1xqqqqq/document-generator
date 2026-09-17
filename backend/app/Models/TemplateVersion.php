<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TemplateVersion extends Model
{
    protected $fillable = [
        'template_id',
        'version',
        'file_path',
        'original_name',
        'size',
        'placeholders',
        'comment',
    ];

    /**
     * Приведение типов: в базе placeholders хранится строкой json,
     * а в коде мы работаем с обычным массивом PHP.
     */
    protected function casts(): array
    {
        return [
            'placeholders' => 'array',
            'size' => 'integer',
            'version' => 'integer',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }
}
