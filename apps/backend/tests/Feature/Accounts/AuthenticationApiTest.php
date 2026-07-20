<?php

declare(strict_types=1);

namespace Tests\Feature\Accounts;

use App\Modules\Accounts\Models\User;
use App\Modules\Households\Enums\HouseholdRole;
use App\Modules\Households\Models\Household;
use App\Modules\Households\Models\HouseholdMember;
use App\Modules\Shared\Audit\Models\AuditLog;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

final class AuthenticationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_normalizes_email_and_atomically_creates_a_personal_household(): void
    {
        Notification::fake();
        $correlationId = '018f4c5e-7b9d-7c11-9c2f-c6f91710bd41';

        $response = $this->withHeader('X-Correlation-ID', $correlationId)->postJson('/api/v1/auth/register', [
            'name' => 'Alex Morgan',
            'email' => '  Alex.Morgan@Example.COM ',
            'password' => 'Correct-Horse-42!',
            'password_confirmation' => 'Correct-Horse-42!',
            'timezone' => 'Europe/London',
            'locale' => 'en',
        ]);

        $response
            ->assertCreated()
            ->assertHeader('X-Correlation-ID', $correlationId)
            ->assertJsonPath('data.email', 'alex.morgan@example.com')
            ->assertJsonPath('data.email_verified', false)
            ->assertJsonPath('data.households.0.role', HouseholdRole::Owner->value);

        $user = User::query()->sole();
        $this->assertTrue(Hash::check('Correct-Horse-42!', $user->password));
        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseCount('households', 1);
        $this->assertDatabaseHas('household_members', [
            'user_id' => $user->id,
            'role' => HouseholdRole::Owner->value,
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'account.registered']);
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_validation_errors_follow_problem_details_contract(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => '',
            'email' => 'not-an-email',
            'password' => 'weak',
            'password_confirmation' => 'different',
            'timezone' => 'Mars/Olympus',
            'locale' => 'unknown',
        ]);

        $response
            ->assertUnprocessable()
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertHeader('X-Correlation-ID')
            ->assertJsonPath('status', 422)
            ->assertJsonStructure(['type', 'title', 'status', 'detail', 'instance', 'correlation_id', 'errors' => [
                'name', 'email', 'password', 'timezone', 'locale',
            ]]);
    }

    public function test_registration_rolls_back_all_account_records_when_a_late_write_fails(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER reject_registration_audit
            BEFORE INSERT ON audit_logs
            BEGIN
                SELECT RAISE(FAIL, 'simulated audit failure');
            END;
            SQL);

        $this->postJson('/api/v1/auth/register', [
            'name' => 'Rollback Example',
            'email' => 'rollback@example.com',
            'password' => 'Correct-Horse-42!',
            'password_confirmation' => 'Correct-Horse-42!',
            'timezone' => 'UTC',
            'locale' => 'en',
        ])->assertInternalServerError()
            ->assertJsonPath('detail', 'An unexpected error occurred.')
            ->assertJsonMissing(['detail' => 'simulated audit failure']);

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('households', 0);
        $this->assertDatabaseCount('household_members', 0);
    }

    public function test_stateful_spa_mutations_require_a_csrf_token_outside_the_test_environment(): void
    {
        $this->app->instance('env', 'local');

        $this->postJson('/api/v1/auth/register', [
            'name' => 'CSRF Example',
            'email' => 'csrf@example.com',
            'password' => 'Correct-Horse-42!',
            'password_confirmation' => 'Correct-Horse-42!',
            'timezone' => 'UTC',
            'locale' => 'en',
        ])->assertStatus(419)
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('detail', 'The CSRF token is missing or expired.');

        $this->assertDatabaseCount('users', 0);
    }

    public function test_login_regenerates_the_session_and_logout_invalidates_it(): void
    {
        $user = User::factory()->create([
            'email' => 'case@example.com',
            'password' => 'Correct-Horse-42!',
        ]);

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => ' Case@Example.COM ',
            'password' => 'Correct-Horse-42!',
        ]);

        $login->assertOk()->assertJsonPath('data.id', $user->id);
        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('audit_logs', ['action' => 'account.logged_in']);

        $this->postJson('/api/v1/auth/logout')->assertNoContent();
        $this->getJson('/api/v1/me')->assertUnauthorized();
        $this->assertDatabaseHas('audit_logs', ['action' => 'account.logged_out']);
    }

    public function test_repeated_failed_logins_are_rate_limited_without_losing_problem_details(): void
    {
        User::factory()->create(['email' => 'limited@example.com']);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => 'limited@example.com',
                'password' => 'incorrect',
            ])->assertUnprocessable();
        }

        $this->postJson('/api/v1/auth/login', [
            'email' => 'limited@example.com',
            'password' => 'incorrect',
        ])->assertTooManyRequests()
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('status', 429);
    }

    public function test_email_verification_requires_a_valid_signature_for_the_authenticated_user(): void
    {
        $user = User::factory()->unverified()->create();
        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(10),
            ['id' => $user->id, 'hash' => sha1($user->email)],
        );

        $this->actingAs($user)->getJson($verificationUrl)->assertNoContent();

        $this->assertTrue($user->refresh()->hasVerifiedEmail());
        $this->assertDatabaseHas('audit_logs', ['action' => 'account.email_verified']);

        $other = User::factory()->unverified()->create();
        $this->actingAs($other)->getJson($verificationUrl)
            ->assertForbidden()
            ->assertJsonPath('status', 403);
        $this->assertFalse($other->refresh()->hasVerifiedEmail());
    }

    public function test_browser_email_verification_returns_to_the_spa(): void
    {
        $user = User::factory()->unverified()->create();
        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(10),
            ['id' => $user->id, 'hash' => sha1($user->email)],
        );

        $this->actingAs($user)
            ->withHeader('Accept', 'text/html')
            ->get($verificationUrl)
            ->assertRedirect('http://localhost:5173/verify-email?verified=1');

        $this->assertTrue($user->refresh()->hasVerifiedEmail());
    }

    public function test_password_reset_uses_a_single_use_broker_token(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'reset@example.com']);
        $token = '';

        $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email])
            ->assertAccepted()
            ->assertJsonMissing(['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use (&$token): bool {
            $token = $notification->token;

            return true;
        });

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => $user->email,
            'token' => $token,
            'password' => 'New-Correct-Horse-84!',
            'password_confirmation' => 'New-Correct-Horse-84!',
        ])->assertNoContent();

        $this->assertTrue(Hash::check('New-Correct-Horse-84!', $user->refresh()->password));
        $this->assertDatabaseHas('audit_logs', ['action' => 'account.password_reset']);

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => $user->email,
            'token' => $token,
            'password' => 'Another-Horse-126!',
            'password_confirmation' => 'Another-Horse-126!',
        ])->assertUnprocessable();
    }

    public function test_profile_changes_validate_preferences_and_reverify_a_changed_email(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'old@example.com']);
        $household = Household::factory()->create(['owner_user_id' => $user->id, 'is_personal' => true]);
        HouseholdMember::query()->create([
            'household_id' => $household->id,
            'user_id' => $user->id,
            'role' => HouseholdRole::Owner,
            'joined_at' => now(),
        ]);

        $this->actingAs($user)->patchJson('/api/v1/me', [
            'email' => ' NEW@example.com ',
            'timezone' => 'America/New_York',
            'locale' => 'en',
        ])->assertOk()
            ->assertJsonPath('data.email', 'new@example.com')
            ->assertJsonPath('data.email_verified', false)
            ->assertJsonPath('data.timezone', 'America/New_York');

        Notification::assertSentTo($user, VerifyEmail::class);
        $this->assertNull($user->refresh()->email_verified_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'account.profile_updated']);
    }

    public function test_unverified_users_cannot_access_household_data(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->getJson('/api/v1/households')
            ->assertForbidden()
            ->assertJsonPath('status', 403);
    }

    public function test_password_reauthentication_records_a_short_lived_session_confirmation(): void
    {
        $user = User::factory()->create(['password' => 'Correct-Horse-42!']);

        $this->actingAs($user)->postJson('/api/v1/auth/confirm-password', [
            'password' => 'Correct-Horse-42!',
        ])->assertOk()->assertJsonStructure(['confirmed_until']);

        $this->assertNotNull(session('auth.password_confirmed_at'));
        $this->assertSame(1, AuditLog::query()->where('action', 'account.password_confirmed')->count());
    }

    public function test_verified_user_can_change_password_and_revoke_other_database_sessions(): void
    {
        $user = User::factory()->create(['password' => 'Correct-Horse-42!']);
        DB::table('sessions')->insert([
            'id' => 'other-authenticated-session',
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Synthetic test session',
            'payload' => base64_encode('synthetic'),
            'last_activity' => time(),
        ]);

        $this->actingAs($user)->putJson('/api/v1/me/password', [
            'current_password' => 'Correct-Horse-42!',
            'password' => 'Replacement-Horse-84!',
            'password_confirmation' => 'Replacement-Horse-84!',
        ])->assertNoContent();

        self::assertTrue(Hash::check('Replacement-Horse-84!', $user->refresh()->password));
        $this->assertDatabaseMissing('sessions', ['id' => 'other-authenticated-session']);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'account.password_changed',
            'actor_user_id' => $user->id,
        ]);
        self::assertNotNull(session('auth.password_confirmed_at'));
    }

    public function test_password_change_rejects_the_wrong_current_password_without_mutation(): void
    {
        $user = User::factory()->create(['password' => 'Correct-Horse-42!']);

        $this->actingAs($user)->putJson('/api/v1/me/password', [
            'current_password' => 'Wrong-Horse-42!',
            'password' => 'Replacement-Horse-84!',
            'password_confirmation' => 'Replacement-Horse-84!',
        ])->assertUnprocessable()
            ->assertJsonPath('errors.current_password.0', 'The current password is incorrect.');

        self::assertTrue(Hash::check('Correct-Horse-42!', $user->refresh()->password));
        $this->assertDatabaseMissing('audit_logs', ['action' => 'account.password_changed']);
    }
}
