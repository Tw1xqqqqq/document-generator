<?php

namespace App\Http\Requests;

use App\Rules\DocxFile;
use Illuminate\Foundation\Http\FormRequest;

/** Загрузка новой версии файла для существующего шаблона. */
class StoreTemplateVersionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'max:20480',
                new DocxFile,
            ],
            'comment' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Выберите файл новой версии.',
            'comment.max' => 'Комментарий не длиннее 255 символов.',
        ];
    }
}
