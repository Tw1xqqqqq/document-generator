<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTemplateVersionRequest;
use App\Http\Resources\TemplateResource;
use App\Models\Template;
use App\Models\TemplateVersion;
use App\Services\Templates\TemplateService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Версии файла шаблона: загрузка новой и скачивание любой из прошлых.
 */
class TemplateVersionController extends Controller
{
    public function __construct(
        private readonly TemplateService $templates,
    ) {}

    /** Загрузка новой версии: она сразу становится актуальной. */
    public function store(StoreTemplateVersionRequest $request, Template $template): JsonResponse
    {
        $this->templates->addVersion(
            $template,
            $request->file('file'),
            $request->input('comment'),
        );

        $template->refresh()->load(['organization', 'currentVersion', 'versions', 'fields']);
        $template->versions->each->setRelation('template', $template);

        return TemplateResource::make($template)
            ->response()
            ->setStatusCode(201);
    }

    /** Скачивание исходного docx конкретной версии. */
    public function download(Template $template, TemplateVersion $version): BinaryFileResponse
    {
        abort_unless($version->template_id === $template->id, 404);

        return response()->download(
            $this->templates->absolutePath($version),
            $version->original_name,
        );
    }

    /**
     * Откат к предыдущей версии: делает её снова актуальной.
     * Файл не копируется — просто меняется указатель.
     */
    public function restore(Template $template, TemplateVersion $version): TemplateResource
    {
        abort_unless($version->template_id === $template->id, 404);

        $template->update(['current_version_id' => $version->id]);
        $this->templates->syncFields($template, $version->placeholders ?? []);

        $template->refresh()->load(['organization', 'currentVersion', 'versions', 'fields']);
        $template->versions->each->setRelation('template', $template);

        return TemplateResource::make($template);
    }
}
