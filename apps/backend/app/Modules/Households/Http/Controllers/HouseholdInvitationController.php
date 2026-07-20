<?php

declare(strict_types=1);

namespace App\Modules\Households\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Households\Actions\AcceptHouseholdInvitation;
use App\Modules\Households\Actions\InviteHouseholdMember;
use App\Modules\Households\Http\Requests\AcceptHouseholdInvitationRequest;
use App\Modules\Households\Http\Requests\InviteHouseholdMemberRequest;
use App\Modules\Households\Http\Resources\HouseholdInvitationResource;
use App\Modules\Households\Http\Resources\HouseholdResource;
use App\Modules\Households\Models\Household;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class HouseholdInvitationController extends Controller
{
    public function store(
        InviteHouseholdMemberRequest $request,
        Household $household,
        InviteHouseholdMember $invite,
    ): JsonResponse {
        $this->authorize('manageMembers', $household);
        $actor = $this->authenticatedUser($request);
        $invitation = $invite->execute($household, $actor, $request->email());

        return (new HouseholdInvitationResource($invitation))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function accept(
        AcceptHouseholdInvitationRequest $request,
        string $invitation,
        AcceptHouseholdInvitation $accept,
    ): HouseholdResource {
        $user = $this->authenticatedUser($request);
        $household = $accept->execute($invitation, $request->token(), $user);

        return new HouseholdResource(
            $user->households()->whereKey($household->id)->withCount('members')->firstOrFail(),
        );
    }
}
