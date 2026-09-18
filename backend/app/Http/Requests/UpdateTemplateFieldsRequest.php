<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Настройка полей шаблона.
 * Приходит сразу весь список - так проще и для формы, и для сохранения.
 */
class UpdateTemplateFieldsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fields' => ['required', 'array'],

            // Ключ менять нельзя: он привязан к метке в docx
            'fields.*.key' => ['required', 'string', 'max:255'],
            'fields.*.label' => ['required', 'string', 'max:255'],
            'fields.*.type' => ['required', Rule::in(['text', 'textarea', 'date', 'number'])],
            'fields.*.required' => ['required', 'boolean'],
            'fields.*.default_value' => ['nullable', 'string', 'max:500'],
            'fields.*.hint' => ['nullable', 'string', 'max:255'],
            'fields.*.sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'fields.*.label.required' => 'У каждого поля должна быть подпись.',
            'fields.*.type.in' => 'Недопустимый тип поля.',
        ];
    }
}
