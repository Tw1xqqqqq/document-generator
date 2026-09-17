<?php

namespace App\Http\Controllers;

use App\Http\Requests\OrganizationRequest;
use App\Http\Resources\OrganizationResource;
use App\Models\Organization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;

class OrganizationController extends Controller
{
    /** Список организаций с количеством шаблонов и документов. */
    public function index(Request $request): AnonymousResourceCollection
    {
        $organizations = Organization::query()
            ->withCount(['templates', 'documents'])
            ->when($request->string('search')->toString(), function ($query, string $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('inn', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->get();

        return OrganizationResource::collection($organizations);
    }

    public function store(OrganizationRequest $request): JsonResponse
    {
        $organization = Organization::create($request->safe()->except('logo'));

        $this->saveLogo($request, $organization);

        return OrganizationResource::make($organization)
            ->response()
            ->setStatusCode(201);
    }

    public function show(Organization $organization): OrganizationResource
    {
        $organization->loadCount(['templates', 'documents']);

        return OrganizationResource::make($organization);
    }

    public function update(OrganizationRequest $request, Organization $organization): OrganizationResource
    {
        $organization->update($request->safe()->except('logo'));

        $this->saveLogo($request, $organization);

        return OrganizationResource::make($organization);
    }

    public function destroy(Organization $organization): JsonResponse
    {
        // Документы организации удаляются вместе с ней (каскад в миграции),
        // поэтому предупреждаем фронт, если их много — он спросит подтверждение.
        if ($organization->logo_path) {
            Storage::disk('public')->delete($organization->logo_path);
        }

        $organization->delete();

        return response()->json(null, 204);
    }

    /**
     * Сохранение логотипа в публичное хранилище.
     * Старый файл удаляем, чтобы не копить мусор.
     */
    private function saveLogo(OrganizationRequest $request, Organization $organization): void
    {
        if (! $request->hasFile('logo')) {
            return;
        }

        if ($organization->logo_path) {
            Storage::disk('public')->delete($organization->logo_path);
        }

        $path = $request->file('logo')->store('logos', 'public');

        $organization->update(['logo_path' => $path]);
    }
}
