<?php

declare(strict_types=1);

namespace App\Modules\Households\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Households\Http\Requests\UpdateHouseholdRequest;
use App\Modules\Households\Http\Resources\HouseholdResource;
use App\Modules\Households\Models\Household;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

final class HouseholdController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $this->authenticatedUser($request);
        $perPage = min(max($request->integer('per_page', 20), 1), 100);
        $households = $user->households()
            ->withCount('members')
            ->orderBy('name')
            ->paginate($perPage);

        return HouseholdResource::collection($households);
    }

    public function show(Household $household): HouseholdResource
    {
        $this->authorize('view', $household);

        return new HouseholdResource($household->loadCount('members'));
    }

    public function update(
        UpdateHouseholdRequest $request,
        Household $household,
        AuditRecorder $audit,
    ): HouseholdResource {
        $this->authorize('update', $household);

        DB::transaction(function () use ($request, $household, $audit): void {
            $household->update(['name' => $request->name()]);
            $actor = $this->authenticatedUser($request);
            $audit->record('household.updated', $actor, $household, $household, ['changed_fields' => 'name']);
        });

        return new HouseholdResource($household->refresh()->loadCount('members'));
    }
}
