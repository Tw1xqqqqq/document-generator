<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateTemplateFieldsRequest;
use App\Http\Resources\TemplateFieldResource;
use App\Models\Template;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

/**
 * Настройка полей шаблона: подписи, типы, обязательность.
 * Это и есть «редакция шаблона» на уровне формы заполнения.
 */
class TemplateFieldController extends Controller
{
    public function index(Template $template): AnonymousResourceCollection
    {
        return TemplateFieldResource::collection($template->fields);
    }

    /**
     * Сохранение сразу всего списка полей.
     * Обновляем только те, что реально существуют у шаблона: ключи полей
     * приходят из docx, добавлять произвольные снаружи нельзя.
     */
    public function update(UpdateTemplateFieldsRequest $request, Template $template): AnonymousResourceCollection
    {
        DB::transaction(function () use ($request, $template) {
            foreach ($request->validated()['fields'] as $index => $field) {
                $template->fields()
                    ->where('key', $field['key'])
                    ->update([
                        'label' => $field['label'],
                        'type' => $field['type'],
                        'required' => $field['required'],
                        'default_value' => $field['default_value'] ?? null,
                        'hint' => $field['hint'] ?? null,
                        'sort_order' => $field['sort_order'] ?? $index,
                    ]);
            }
        });

        return TemplateFieldResource::collection($template->fields()->get());
    }
}
