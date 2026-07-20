<?php

declare(strict_types=1);

namespace App\Modules\Households\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounts\Models\User;
use App\Modules\Households\Enums\HouseholdRole;
use App\Modules\Households\Http\Resources\HouseholdMemberResource;
use App\Modules\Households\Models\Household;
use App\Modules\Shared\Audit\AuditRecorder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class HouseholdMemberController extends Controller
{
    public function index(Household $household): AnonymousResourceCollection
    {
        $this->authorize('viewMembers', $household);

        return HouseholdMemberResource::collection(
            $household->memberships()->with('user')->orderBy('joined_at')->get(),
        );
    }

    /** @throws ValidationException */
    public function destroy(
        Request $request,
        Household $household,
        string $member,
        AuditRecorder $audit,
    ): Response {
        $this->authorize('manageMembers', $household);

        $membership = $household->memberships()->where('user_id', $member)->firstOrFail();

        if ($membership->role === HouseholdRole::Owner) {
            throw ValidationException::withMessages([
                'member' => ['The household owner cannot be removed.'],
            ]);
        }

        DB::transaction(function () use ($request, $household, $membership, $audit): void {
            /** @var User $actor */
            $actor = $request->user();
            $audit->record('household.member_removed', $actor, $household, $membership);
            $membership->delete();
        });

        return response()->noContent();
    }
}
