<?php

namespace App\Http\Requests;

use App\Models\Template;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Генерация документа.
 *
 * Обязательность полей у каждого шаблона своя и хранится в базе,
 * поэтому правила достраиваются динамически: читаем поля выбранного
 * шаблона и требуем заполнить те, что отмечены обязательными.
 */
class StoreDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'template_id' => ['required', 'integer', 'exists:templates,id'],
            'organization_id' => ['required', 'integer', 'exists:organizations,id'],
            'name' => ['nullable', 'string', 'max:255'],
            'data' => ['present', 'array'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            // Если базовые правила не прошли, шаблона может не быть - выходим
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $template = Template::with('fields')->find($this->integer('template_id'));

            if (! $template?->current_version_id) {
                $validator->errors()->add('template_id', 'У шаблона нет загруженного файла.');

                return;
            }

            $data = $this->input('data', []);

            foreach ($template->fields as $field) {
                if (! $field->required) {
                    continue;
                }

                $value = $data[$field->key] ?? $field->default_value ?? '';

                if (trim((string) $value) === '') {
                    $validator->errors()->add(
                        "data.{$field->key}",
                        "Заполните поле «{$field->label}»."
                    );
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'template_id.required' => 'Выберите шаблон.',
            'template_id.exists' => 'Шаблон не найден.',
            'organization_id.required' => 'Выберите организацию.',
            'organization_id.exists' => 'Организация не найдена.',
        ];
    }
}
