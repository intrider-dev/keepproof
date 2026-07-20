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
use Illuminate\Contracts\Notifications\Dispatcher as NotificationDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Mockery\CompositeExpectation;
use Ramsey\Uuid\Uuid;
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

    public function test_quiet_hours_shift_email_but_not_in_app_delivery_in_the_user_timezone(): void
    {
        [$owner, $household] = $this->householdWithOwner();
        $owner->notificationPreference()->create([
            'in_app_enabled' => true,
            'email_enabled' => true,
            'return_intervals' => [3],
            'warranty_intervals' => [],
            'quiet_hours_start' => '08:00',
            'quiet_hours_end' => '10:30',
        ]);

        $this->createPurchase($owner, $household);

        $inApp = Reminder::query()->where('channel', ReminderChannel::InApp)->firstOrFail();
        $email = Reminder::query()->where('channel', ReminderChannel::Email)->firstOrFail();
        self::assertSame('2026-08-16 13:00:00', $inApp->remind_at->format('Y-m-d H:i:s'));
        self::assertSame('2026-08-16 14:30:00', $email->remind_at->format('Y-m-d H:i:s'));
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
            ->assertJsonPath('data.0.data.purchase_id', $reminder->deadline->purchase_id)
            ->assertJsonMissingPath('data.0.notifiable_id')
            ->assertJsonMissingPath('data.0.notifiable_type');
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

    public function test_in_app_retry_reconciles_a_persisted_notification_without_sending_a_duplicate(): void
    {
        Notification::fake();
        [$owner, $household] = $this->householdWithOwner();
        $this->createPurchase($owner, $household);
        $reminder = Reminder::query()->where('channel', ReminderChannel::InApp)->firstOrFail();
        $notificationId = Uuid::uuid5(
            Uuid::NAMESPACE_URL,
            'https://keepproof.local/reminders/'.$reminder->id,
        )->toString();
        DB::table('notifications')->insert([
            'id' => $notificationId,
            'type' => DeadlineReminderNotification::class,
            'notifiable_type' => $owner->getMorphClass(),
            'notifiable_id' => $owner->id,
            'data' => '{}',
            'read_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $reminder->update([
            'status' => ReminderStatus::Failed,
            'remind_at' => now()->subMinute(),
            'attempts' => 1,
        ]);

        (new SendReminder($reminder->id))->handle();

        Notification::assertNothingSent();
        self::assertSame(ReminderStatus::Sent, $reminder->refresh()->status);
        self::assertSame(1, $reminder->attempts);
        $this->assertDatabaseCount('notifications', 1);
    }

    public function test_ambiguous_email_failure_is_terminal_and_is_not_automatically_resent(): void
    {
        [$owner, $household] = $this->householdWithOwner();
        $this->createPurchase($owner, $household);
        $reminder = Reminder::query()->where('channel', ReminderChannel::Email)->firstOrFail();
        $reminder->update(['remind_at' => now()->subMinute()]);
        $dispatcher = Mockery::mock(NotificationDispatcher::class);
        $expectation = $dispatcher->shouldReceive('send');
        self::assertInstanceOf(CompositeExpectation::class, $expectation);
        $expectation->__call('once', []);
        $expectation->__call('andThrow', [new \RuntimeException('Synthetic disconnect after SMTP DATA.')]);
        $this->app->instance(NotificationDispatcher::class, $dispatcher);
        $job = new SendReminder($reminder->id);

        $job->handle();
        $job->handle();
        $job->failed(new \RuntimeException('Synthetic queue callback.'));

        self::assertSame(ReminderStatus::DeliveryUnknown, $reminder->refresh()->status);
        self::assertSame('email_delivery_unknown', $reminder->last_error_code);
        self::assertSame(1, $reminder->attempts);
    }

    public function test_dispatch_reconciles_interrupted_email_as_unknown_and_retries_in_app_once(): void
    {
        Queue::fake();
        [$owner, $household] = $this->householdWithOwner();
        $this->createPurchase($owner, $household);
        $email = Reminder::query()->where('channel', ReminderChannel::Email)->firstOrFail();
        $inApp = Reminder::query()->where('channel', ReminderChannel::InApp)->firstOrFail();
        foreach ([$email, $inApp] as $reminder) {
            $reminder->update([
                'status' => ReminderStatus::Sending,
                'remind_at' => now()->subMinute(),
                'attempts' => 1,
            ]);
            DB::table('reminders')->where('id', $reminder->id)->update([
                'updated_at' => now()->subMinutes(2),
            ]);
        }

        app(DispatchDueReminders::class)->execute();

        self::assertSame(ReminderStatus::DeliveryUnknown, $email->refresh()->status);
        self::assertSame('email_delivery_unknown_after_interruption', $email->last_error_code);
        self::assertSame(ReminderStatus::Failed, $inApp->refresh()->status);
        self::assertSame('in_app_delivery_interrupted', $inApp->last_error_code);
        Queue::assertPushed(
            SendReminder::class,
            static fn (SendReminder $job): bool => $job->reminderId === $inApp->id,
        );
        Queue::assertNotPushed(
            SendReminder::class,
            static fn (SendReminder $job): bool => $job->reminderId === $email->id,
        );
    }

    public function test_failed_callback_preserves_unknown_state_for_an_interrupted_email(): void
    {
        [$owner, $household] = $this->householdWithOwner();
        $this->createPurchase($owner, $household);
        $reminder = Reminder::query()->where('channel', ReminderChannel::Email)->firstOrFail();
        $reminder->update(['status' => ReminderStatus::Sending]);

        (new SendReminder($reminder->id))->failed(new \RuntimeException('Synthetic worker timeout.'));

        self::assertSame(ReminderStatus::DeliveryUnknown, $reminder->refresh()->status);
        self::assertSame('email_delivery_unknown_after_interruption', $reminder->last_error_code);
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
