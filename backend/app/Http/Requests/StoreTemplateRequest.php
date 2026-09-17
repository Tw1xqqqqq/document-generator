<?php

namespace App\Http\Requests;

use App\Rules\DocxFile;
use Illuminate\Foundation\Http\FormRequest;

/** Загрузка нового шаблона: карточка + сам файл docx. */
class StoreTemplateRequest extends FormRequest
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

            // null = общий шаблон, доступный всем организациям
            'organization_id' => ['nullable', 'integer', 'exists:organizations,id'],

            // Правило DocxFile заглядывает внутрь архива: переименованный
            // pdf с расширением docx до обработки не дойдёт
            'file' => [
                'required',
                'file',
                'max:20480', // 20 МБ
                new DocxFile,
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Укажите название шаблона.',
            'file.required' => 'Выберите файл шаблона.',
            'file.max' => 'Файл не должен быть больше 20 МБ.',
            'organization_id.exists' => 'Выбранная организация не найдена.',
        ];
    }
}
