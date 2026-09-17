<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDocumentRequest;
use App\Http\Resources\DocumentResource;
use App\Models\Document;
use App\Models\Organization;
use App\Models\Template;
use App\Services\Documents\DocumentGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    public function __construct(
        private readonly DocumentGenerator $generator,
    ) {}

    /** Журнал документов с фильтрами по организации и шаблону. */
    public function index(Request $request): AnonymousResourceCollection
    {
        $documents = Document::query()
            ->with(['template', 'organization', 'templateVersion'])
            ->when($request->filled('organization_id'),
                fn ($q) => $q->where('organization_id', $request->integer('organization_id')))
            ->when($request->filled('template_id'),
                fn ($q) => $q->where('template_id', $request->integer('template_id')))
            ->when($request->string('search')->toString(),
                fn ($q, $search) => $q->where('name', 'like', "%{$search}%"))
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 20));

        return DocumentResource::collection($documents);
    }

    /** Генерация документа по шаблону. */
    public function store(StoreDocumentRequest $request): JsonResponse
    {
        $template = Template::with(['fields', 'currentVersion'])->findOrFail($request->integer('template_id'));
        $organization = Organization::findOrFail($request->integer('organization_id'));

        $document = $this->generator->generate(
            $template,
            $organization,
            $request->input('data', []),
            $request->input('name'),
        );

        $document->load(['template', 'organization', 'templateVersion']);

        return DocumentResource::make($document)
            ->response()
            ->setStatusCode(201);
    }

    public function show(Document $document): DocumentResource
    {
        $document->load(['template.fields', 'organization', 'templateVersion']);

        return DocumentResource::make($document);
    }

    /**
     * Скачивание готового файла.
     * Формат выбирается параметром: ?format=pdf (по умолчанию) или docx.
     */
    public function download(Request $request, Document $document): StreamedResponse
    {
        $format = $request->string('format')->toString() ?: 'pdf';

        abort_unless(in_array($format, ['pdf', 'docx'], true), 422, 'Допустимые форматы: pdf, docx.');

        $path = $format === 'pdf' ? $document->pdf_path : $document->docx_path;

        abort_unless($path && Storage::disk('documents')->exists($path), 404, 'Файл документа не найден.');

        // Имя файла для пользователя: «Счёт № 12.pdf»
        $fileName = $this->safeFileName($document->name).'.'.$format;

        return Storage::disk('documents')->download($path, $fileName);
    }

    /**
     * Предпросмотр PDF без сохранения в журнал.
     * Возвращает сам файл, чтобы фронт показал его во встроенной смотрелке.
     */
    public function preview(StoreDocumentRequest $request): Response
    {
        $template = Template::with(['fields', 'currentVersion'])->findOrFail($request->integer('template_id'));
        $organization = Organization::findOrFail($request->integer('organization_id'));

        $pdf = $this->generator->preview($template, $organization, $request->input('data', []));

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="preview.pdf"',
        ]);
    }

    public function destroy(Document $document): JsonResponse
    {
        // Удаляем папку документа целиком — в ней и docx, и pdf
        if ($document->pdf_path) {
            Storage::disk('documents')->deleteDirectory(dirname($document->pdf_path));
        }

        $document->delete();

        return response()->json(null, 204);
    }

    /** Убирает из имени файла символы, запрещённые в файловых системах. */
    private function safeFileName(string $name): string
    {
        return trim(preg_replace('/[\/\\\\:*?"<>|]+/u', ' ', $name)) ?: 'document';
    }
}
