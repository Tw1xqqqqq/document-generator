<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Изменение карточки шаблона: название, описание, организация.
 * Файл здесь не меняется — для этого есть загрузка новой версии.
 */
class UpdateTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'organization_id' => ['nullable', 'integer', 'exists:organizations,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Укажите название шаблона.',
            'organization_id.exists' => 'Выбранная организация не найдена.',
        ];
    }
}
