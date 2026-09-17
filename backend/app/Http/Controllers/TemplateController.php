<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTemplateRequest;
use App\Http\Requests\UpdateTemplateRequest;
use App\Http\Resources\TemplateResource;
use App\Models\Template;
use App\Services\Templates\TemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TemplateController extends Controller
{
    public function __construct(
        private readonly TemplateService $templates,
    ) {}

    /**
     * Список шаблонов.
     * Фильтр organization_id показывает шаблоны организации вместе с общими:
     * общие доступны всем, поэтому при выборе организации они тоже нужны.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $templates = Template::query()
            ->with(['organization', 'currentVersion'])
            ->withCount('documents')
            ->when($request->filled('organization_id'), function ($query) use ($request) {
                $organizationId = $request->integer('organization_id');

                $query->where(function ($q) use ($organizationId) {
                    $q->where('organization_id', $organizationId)
                        ->orWhereNull('organization_id');
                });
            })
            ->when($request->string('search')->toString(), function ($query, string $search) {
                $query->where('name', 'like', "%{$search}%");
            })
            ->orderByDesc('created_at')
            ->get();

        return TemplateResource::collection($templates);
    }

    /** Загрузка нового шаблона вместе с файлом. */
    public function store(StoreTemplateRequest $request): JsonResponse
    {
        $template = $this->templates->create(
            $request->safe()->except('file'),
            $request->file('file'),
        );

        return TemplateResource::make($this->loadDetails($template))
            ->response()
            ->setStatusCode(201);
    }

    /** Карточка шаблона: версии, поля, организация. */
    public function show(Template $template): TemplateResource
    {
        return TemplateResource::make($this->loadDetails($template));
    }

    public function update(UpdateTemplateRequest $request, Template $template): TemplateResource
    {
        $template->update($request->validated());

        return TemplateResource::make($this->loadDetails($template));
    }

    public function destroy(Template $template): JsonResponse
    {
        $this->templates->delete($template);

        return response()->json(null, 204);
    }

    /**
     * Подгружает связи для карточки шаблона.
     * Каждой версии подставляем уже загруженный шаблон, иначе ресурс
     * версии обращался бы к базе за ним отдельным запросом (проблема N+1).
     */
    private function loadDetails(Template $template): Template
    {
        $template->load(['organization', 'currentVersion', 'versions', 'fields'])
            ->loadCount('documents');

        $template->versions->each->setRelation('template', $template);

        return $template;
    }
}
