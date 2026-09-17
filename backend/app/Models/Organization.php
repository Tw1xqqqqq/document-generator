<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    use HasFactory;

    /**
     * Поля, которые разрешено заполнять массово (через create/update).
     * Всё, что не перечислено, изменить снаружи нельзя — защита от подмены данных.
     */
    protected $fillable = [
        'name',
        'full_name',
        'inn',
        'kpp',
        'ogrn',
        'legal_address',
        'actual_address',
        'phone',
        'email',
        'bank_name',
        'bank_account',
        'bank_corr_account',
        'bank_bik',
        'director_name',
        'director_position',
        'logo_path',
    ];

    /** Шаблоны, привязанные к этой организации. */
    public function templates(): HasMany
    {
        return $this->hasMany(Template::class);
    }

    /** Документы, выпущенные от имени организации. */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    /**
     * Реквизиты в виде плоского массива для подстановки в шаблон.
     * Ключи получают префикс org: ${org.name}, ${org.inn} и т. д.
     */
    public function toPlaceholders(string $prefix = 'org'): array
    {
        $values = [];

        foreach ($this->only($this->fillable) as $key => $value) {
            $values["{$prefix}.{$key}"] = (string) ($value ?? '');
        }

        return $values;
    }
}
