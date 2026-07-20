<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Modules\Accounts\Models\User;
use App\Modules\Households\Enums\HouseholdRole;
use App\Modules\Households\Models\Household;
use App\Modules\Households\Models\HouseholdMember;
use App\Modules\Notifications\Actions\DispatchDueReminders;
use App\Modules\Notifications\Enums\ReminderChannel;
use App\Modules\Notifications\Enums\ReminderStatus;
use App\Modules\Notifications\Jobs\SendReminder;
use App\Modules\Notifications\Models\Reminder;
use App\Modules\Notifications\Notifications\DeadlineReminderNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class ReminderWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-07-20 12:00:00 UTC');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_deadline_activation_builds_deduplicated_in_app_and_email_schedules(): void
    {
        [$owner, $household] = $this->householdWithOwner();

        $this->createPurchase($owner, $household);

        $this->assertDatabaseCount('purchase_deadlines', 2);
        $this->assertDatabaseCount('reminders', 10);
        $this->assertSame(10, Reminder::query()->distinct('deduplication_key')->count('deduplication_key'));
        $this->assertSame(5, Reminder::query()->where('channel', ReminderChannel::InApp)->count());
        $this->assertSame(5, Reminder::query()->where('channel', ReminderChannel::Email)->count());

        // Replacing the same purchase data recalculates deadlines without duplicating active reminder rows.
        $purchaseId = $household->purchases()->value('id');
        self::assertIsString($purchaseId);
        $this->actingAs($owner)->putJson(
            '/api/v1/households/'.$household->id.'/purchases/'.$purchaseId,
            $this->payload(),
        )->assertOk();
        $this->assertSame(10, Reminder::query()->where('status', ReminderStatus::Scheduled)->count());
    }

    public function test_preference_change_rebuilds_only_unsent_reminders_for_that_user(): void
    {
        [$owner, $household] = $this->householdWithOwner();
        $this->createPurchase($owner, $household);

        $this->actingAs($owner)->putJson('/api/v1/notification-preferences', [
            'in_app_enabled' => true,
            'email_enabled' => false,
            'return_intervals' => [5],
            'warranty_intervals' => [],
            'quiet_hours_start' => '22:00',
            'quiet_hours_end' => '07:00',
        ])->assertOk()
            ->assertJsonPath('data.return_intervals.0', 5)
            ->assertJsonPath('data.email_enabled', false)
            ->assertJsonPath('data.timezone', 'America/New_York');

        $scheduled = Reminder::query()->where('status', ReminderStatus::Scheduled)->get();
        self::assertCount(1, $scheduled);
        $remaining = Reminder::query()->where('status', ReminderStatus::Scheduled)->firstOrFail();
        self::assertSame(ReminderChannel::InApp, $remaining->channel);
        self::assertSame(5, $remaining->interval_days);
        self::assertGreaterThanOrEqual(10, Reminder::query()->where('status', ReminderStatus::Cancelled)->count());
    }

    public function test_in_app_delivery_is_sent_once_and_visible_only_to_the_recipient(): void
    {
        [$owner, $household] = $this->householdWithOwner();
        [$other] = $this->householdWithOwner('Other');
        $this->createPurchase($owner, $household);
        $reminder = Reminder::query()->where('channel', ReminderChannel::InApp)->firstOrFail();
        $reminder->update(['remind_at' => now()->subMinute()]);
        $job = new SendReminder($reminder->id);

        $job->handle();
        $job->handle();

        self::assertSame(ReminderStatus::Sent, $reminder->refresh()->status);
        $this->assertDatabaseCount('notifications', 1);
        $this->actingAs($owner)->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonPath('data.0.data.purchase_id', $reminder->deadline->purchase_id);
        $this->actingAs($other)->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_email_delivery_and_due_dispatch_are_bounded_and_deduplicated(): void
    {
        Notification::fake();
        Queue::fake();
        [$owner, $household] = $this->householdWithOwner();
        $this->createPurchase($owner, $household);
        $reminder = Reminder::query()->where('channel', ReminderChannel::Email)->firstOrFail();
        $reminder->update(['remind_at' => now()->subMinute()]);

        app(DispatchDueReminders::class)->execute();
        Queue::assertPushed(SendReminder::class, 1);

        $job = new SendReminder($reminder->id);
        $job->handle();
        $job->handle();

        Notification::assertSentToTimes($owner, DeadlineReminderNotification::class, 1);
        self::assertSame(ReminderStatus::Sent, $reminder->refresh()->status);
        self::assertSame(1, $reminder->attempts);
    }

    public function test_exhausted_delivery_is_explicitly_failed_without_an_infinite_retry_state(): void
    {
        [$owner, $household] = $this->householdWithOwner();
        $this->createPurchase($owner, $household);
        $reminder = Reminder::query()->firstOrFail();
        $reminder->update(['attempts' => 3, 'status' => ReminderStatus::Sending]);

        (new SendReminder($reminder->id))->failed(new \RuntimeException('synthetic provider failure'));

        self::assertSame(ReminderStatus::Failed, $reminder->refresh()->status);
        self::assertSame('notification_delivery_exhausted', $reminder->last_error_code);
        Queue::fake();
        app(DispatchDueReminders::class)->execute();
        Queue::assertNothingPushed();
    }

    private function createPurchase(User $owner, Household $household): void
    {
        $this->actingAs($owner)
            ->postJson('/api/v1/households/'.$household->id.'/purchases', $this->payload())
            ->assertCreated();
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        return [
            'merchant' => 'North & Pine',
            'purchase_date' => '2026-07-20',
            'timezone' => 'America/New_York',
            'receipt_number' => 'NP-REMINDER',
            'currency' => 'USD',
            'total_minor' => 8950,
            'country_region' => 'US',
            'return_period_quantity' => 30,
            'return_period_unit' => 'days',
            'warranty_period_quantity' => 12,
            'warranty_period_unit' => 'months',
            'items' => [[
                'name' => 'Pour-over electric kettle',
                'quantity' => 1,
                'unit_price_minor' => 8950,
            ]],
        ];
    }

    /** @return array{User, Household} */
    private function householdWithOwner(string $name = 'Alex'): array
    {
        $owner = User::factory()->create(['name' => $name, 'timezone' => 'America/New_York']);
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
