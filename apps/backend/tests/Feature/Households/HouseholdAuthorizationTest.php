<?php

declare(strict_types=1);

namespace Tests\Feature\Households;

use App\Modules\Accounts\Models\User;
use App\Modules\Households\Enums\HouseholdRole;
use App\Modules\Households\Models\Household;
use App\Modules\Households\Models\HouseholdMember;
use App\Modules\Households\Notifications\HouseholdInvitationNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

final class HouseholdAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_household_binding_hides_another_tenants_resource(): void
    {
        [$owner, $household] = $this->householdWithOwner('Owner');
        [$intruder] = $this->householdWithOwner('Intruder');

        $this->actingAs($owner)->getJson('/api/v1/households/'.$household->id)
            ->assertOk()
            ->assertJsonPath('data.id', $household->id);

        $foreign = $this->actingAs($intruder)->getJson('/api/v1/households/'.$household->id);
        $missing = $this->actingAs($intruder)->getJson('/api/v1/households/01J00000000000000000000000');

        $foreign->assertNotFound()->assertJsonPath('detail', 'The requested resource was not found.');
        $missing->assertNotFound()->assertJsonPath('detail', 'The requested resource was not found.');
        $this->assertSame($missing->json('title'), $foreign->json('title'));
    }

    public function test_a_member_can_read_but_only_an_owner_can_change_a_household(): void
    {
        [$owner, $household] = $this->householdWithOwner('Owner');
        $member = User::factory()->create();
        HouseholdMember::query()->create([
            'household_id' => $household->id,
            'user_id' => $member->id,
            'role' => HouseholdRole::Member,
            'joined_at' => now(),
        ]);

        $this->actingAs($member)->getJson('/api/v1/households/'.$household->id.'/members')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->actingAs($member)->patchJson('/api/v1/households/'.$household->id, ['name' => 'Forbidden'])
            ->assertForbidden();

        $this->actingAs($owner)->patchJson('/api/v1/households/'.$household->id, ['name' => 'Shared records'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Shared records');
    }

    public function test_an_invitation_can_only_be_accepted_by_its_normalized_email_recipient(): void
    {
        Notification::fake();
        [$owner, $household] = $this->householdWithOwner('Owner');
        $recipient = User::factory()->create(['email' => 'member@example.com']);
        $other = User::factory()->create(['email' => 'other@example.com']);
        $token = '';

        $response = $this->actingAs($owner)->postJson('/api/v1/households/'.$household->id.'/invitations', [
            'email' => ' MEMBER@Example.com ',
        ])->assertCreated()->assertJsonPath('data.email', 'member@example.com');

        $invitationId = $response->json('data.id');

        if (! is_string($invitationId)) {
            self::fail('The invitation response must contain a string identifier.');
        }

        Notification::assertSentOnDemand(
            HouseholdInvitationNotification::class,
            function (HouseholdInvitationNotification $notification) use (&$token): bool {
                $queryString = parse_url($notification->acceptUrl(), PHP_URL_QUERY);

                if (! is_string($queryString)) {
                    return false;
                }

                parse_str($queryString, $query);
                $parsedToken = $query['token'] ?? null;

                if (! is_string($parsedToken)) {
                    return false;
                }

                $token = $parsedToken;

                return $token !== '';
            },
        );

        $this->actingAs($other)->postJson('/api/v1/invitations/'.$invitationId.'/accept', ['token' => $token])
            ->assertUnprocessable();

        $this->actingAs($recipient)->postJson('/api/v1/invitations/'.$invitationId.'/accept', ['token' => $token])
            ->assertOk()
            ->assertJsonPath('data.id', $household->id);

        $this->assertDatabaseHas('household_members', [
            'household_id' => $household->id,
            'user_id' => $recipient->id,
            'role' => HouseholdRole::Member->value,
        ]);

        // A retried client request remains safe and does not create a second membership.
        $this->actingAs($recipient)->postJson('/api/v1/invitations/'.$invitationId.'/accept', ['token' => $token])
            ->assertOk();
        $this->assertSame(1, HouseholdMember::query()
            ->where('household_id', $household->id)
            ->where('user_id', $recipient->id)
            ->count());
    }

    public function test_an_owner_cannot_remove_the_owner_membership(): void
    {
        [$owner, $household] = $this->householdWithOwner('Owner');

        $this->actingAs($owner)
            ->deleteJson('/api/v1/households/'.$household->id.'/members/'.$owner->id)
            ->assertUnprocessable()
            ->assertJsonPath('errors.member.0', 'The household owner cannot be removed.');
    }

    public function test_ownership_transfer_requires_recent_password_and_changes_authority_atomically(): void
    {
        [$owner, $household] = $this->householdWithOwner('Owner');
        $member = User::factory()->create(['name' => 'New owner']);
        HouseholdMember::query()->create([
            'household_id' => $household->id,
            'user_id' => $member->id,
            'role' => HouseholdRole::Member,
            'joined_at' => now(),
        ]);
        $url = '/api/v1/households/'.$household->id.'/owner';

        $this->actingAs($owner)
            ->putJson($url, ['member_user_id' => $member->id])
            ->assertForbidden()
            ->assertJsonPath('code', 'password_confirmation_required');

        $this->actingAs($owner)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->putJson($url, ['member_user_id' => $member->id])
            ->assertOk()
            ->assertJsonPath('data.is_personal', false);

        $this->assertDatabaseHas('households', [
            'id' => $household->id,
            'owner_user_id' => $member->id,
            'is_personal' => false,
        ]);
        $this->assertDatabaseHas('household_members', [
            'household_id' => $household->id,
            'user_id' => $owner->id,
            'role' => HouseholdRole::Member->value,
        ]);
        $this->assertDatabaseHas('household_members', [
            'household_id' => $household->id,
            'user_id' => $member->id,
            'role' => HouseholdRole::Owner->value,
        ]);
        self::assertSame(1, HouseholdMember::query()
            ->where('household_id', $household->id)
            ->where('role', HouseholdRole::Owner)
            ->count());
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'household.ownership_transferred',
            'actor_user_id' => $owner->id,
            'household_id' => $household->id,
        ]);

        $this->actingAs($owner)
            ->patchJson('/api/v1/households/'.$household->id, ['name' => 'Former owner edit'])
            ->assertForbidden();
        $this->actingAs($member)
            ->patchJson('/api/v1/households/'.$household->id, ['name' => 'New owner edit'])
            ->assertOk();
    }

    public function test_ownership_cannot_be_transferred_to_a_non_member(): void
    {
        [$owner, $household] = $this->householdWithOwner('Owner');
        $nonMember = User::factory()->create();

        $this->actingAs($owner)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->putJson('/api/v1/households/'.$household->id.'/owner', [
                'member_user_id' => $nonMember->id,
            ])
            ->assertUnprocessable()
            ->assertJsonPath(
                'errors.member_user_id.0',
                'The new owner must already be a member of this household.',
            );

        self::assertSame($owner->id, $household->refresh()->owner_user_id);
    }

    /** @return array{User, Household} */
    private function householdWithOwner(string $name): array
    {
        $owner = User::factory()->create(['name' => $name]);
        $household = Household::factory()->create([
            'name' => $name.' household',
            'owner_user_id' => $owner->id,
            'is_personal' => true,
        ]);
        HouseholdMember::query()->create([
            'household_id' => $household->id,
            'user_id' => $owner->id,
            'role' => HouseholdRole::Owner,
            'joined_at' => now(),
        ]);

        return [$owner, $household];
    }
}
