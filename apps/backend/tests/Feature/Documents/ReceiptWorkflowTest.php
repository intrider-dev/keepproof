<?php

declare(strict_types=1);

namespace Tests\Feature\Documents;

use App\Modules\Accounts\Models\User;
use App\Modules\Documents\Actions\TransitionDocumentState;
use App\Modules\Documents\Actions\ValidateReceiptContent;
use App\Modules\Documents\Contracts\DocumentTextExtractor;
use App\Modules\Documents\Contracts\ReceiptDataExtractor;
use App\Modules\Documents\Data\ExtractedDocumentText;
use App\Modules\Documents\Enums\DocumentState;
use App\Modules\Documents\Exceptions\InvalidDocumentTransition;
use App\Modules\Documents\Exceptions\TransientDocumentFailure;
use App\Modules\Documents\Extraction\DeterministicFixtureExtractor;
use App\Modules\Documents\Jobs\ProcessReceipt;
use App\Modules\Documents\Models\ExtractionField;
use App\Modules\Documents\Models\ReceiptDocument;
use App\Modules\Households\Enums\HouseholdRole;
use App\Modules\Households\Models\Household;
use App\Modules\Households\Models\HouseholdMember;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Fakes\FailingBusDispatcher;
use Tests\TestCase;

final class ReceiptWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('receipts');
        Queue::fake();
        config()->set('receipt_processing.disk', 'receipts');
        config()->set('receipt_processing.ocr_driver', 'fixture');
        config()->set('receipt_processing.data_extractor', 'patterns');
    }

    public function test_upload_sanitizes_filename_uses_an_opaque_private_key_and_is_idempotent(): void
    {
        [$owner, $household] = $this->householdWithOwner('Alex');
        $first = $this->actingAs($owner)
            ->withHeader('Idempotency-Key', 'upload-proof-0001')
            ->post('/api/v1/households/'.$household->id.'/receipts', [
                'receipt' => UploadedFile::fake()->image('../../family receipt.JPG', 120, 80),
            ]);

        $first->assertAccepted()
            ->assertJsonPath('data.state', 'validation_pending')
            ->assertJsonPath('data.filename', 'family receipt.jpg')
            ->assertJsonPath('data.external_ai_consent_granted', false)
            ->assertJsonMissingPath('data.storage_key')
            ->assertJsonMissingPath('data.raw_ocr_text');
        $documentId = $first->json('data.id');
        self::assertIsString($documentId);
        $document = ReceiptDocument::query()->findOrFail($documentId);

        self::assertStringStartsWith(
            'households/'.$household->id.'/receipts/'.$documentId.'/',
            $document->storage_key,
        );
        self::assertStringNotContainsString('family', $document->storage_key);
        self::assertStringNotContainsString('..', $document->storage_key);
        self::assertNull($document->external_ai_consent_at);
        Storage::disk('receipts')->assertExists($document->storage_key);
        Queue::assertPushed(ProcessReceipt::class, 1);

        $retry = $this->actingAs($owner)
            ->withHeader('Idempotency-Key', 'upload-proof-0001')
            ->post('/api/v1/households/'.$household->id.'/receipts', [
                'receipt' => UploadedFile::fake()->image('different.png', 20, 20),
                'external_ai_consent' => '1',
            ]);

        $retry->assertAccepted()
            ->assertJsonPath('data.id', $documentId)
            ->assertJsonPath('data.external_ai_consent_granted', false);
        $this->assertDatabaseCount('receipt_documents', 1);
        $this->assertDatabaseCount('purchases', 1);
        Queue::assertPushed(ProcessReceipt::class, 1);
    }

    public function test_external_ai_consent_is_recorded_only_when_explicitly_granted(): void
    {
        [$owner, $household] = $this->householdWithOwner('Alex');

        $response = $this->actingAs($owner)
            ->withHeader('Idempotency-Key', 'upload-consent-0001')
            ->post('/api/v1/households/'.$household->id.'/receipts', [
                'receipt' => UploadedFile::fake()->image('receipt.png', 100, 100),
                'external_ai_consent' => '1',
            ])
            ->assertAccepted()
            ->assertJsonPath('data.external_ai_consent_granted', true);

        $documentId = $response->json('data.id');
        self::assertIsString($documentId);
        self::assertNotNull(ReceiptDocument::query()->findOrFail($documentId)->external_ai_consent_at);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'receipt.uploaded',
            'subject_id' => $documentId,
        ]);
    }

    public function test_disguised_script_and_unsupported_svg_are_rejected_before_storage(): void
    {
        [$owner, $household] = $this->householdWithOwner('Alex');

        $this->actingAs($owner)
            ->withHeader('Idempotency-Key', 'upload-proof-0002')
            ->post('/api/v1/households/'.$household->id.'/receipts', [
                'receipt' => UploadedFile::fake()->createWithContent('receipt.jpg', '<?php system($_GET["x"]);'),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('receipt');

        $this->actingAs($owner)
            ->withHeader('Idempotency-Key', 'upload-proof-0003')
            ->post('/api/v1/households/'.$household->id.'/receipts', [
                'receipt' => UploadedFile::fake()->createWithContent(
                    'receipt.svg',
                    '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>',
                ),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('receipt');

        $this->assertDatabaseCount('receipt_documents', 0);
        self::assertSame([], Storage::disk('receipts')->allFiles());
    }

    public function test_processing_stores_encrypted_raw_text_and_review_fields_without_deadlines(): void
    {
        [$owner, $household] = $this->householdWithOwner('Alex');
        $document = $this->uploadImage($owner, $household, 'upload-proof-0004');

        $this->process($document, new DeterministicFixtureExtractor);
        $document->refresh()->load('fields');

        self::assertSame(DocumentState::NeedsReview, $document->state);
        self::assertSame('deterministic-fixture-v1', $document->ocr_provider);
        self::assertSame('deterministic-patterns', $document->extraction_provider);
        self::assertSame('receipt-patterns-v1', $document->prompt_version);
        self::assertSame('Northstar Home', $document->fields->firstWhere('field_key', 'merchant')?->normalized_value);
        self::assertSame('13950', $document->fields->firstWhere('field_key', 'total_minor')?->normalized_value);
        $encrypted = DB::table('receipt_documents')->where('id', $document->id)->value('raw_ocr_text');
        self::assertIsString($encrypted);
        self::assertStringNotContainsString('Northstar Home', $encrypted);
        $this->assertDatabaseMissing('purchase_deadlines', ['purchase_id' => $document->purchase_id]);
        $this->assertDatabaseHas('document_state_transitions', [
            'receipt_document_id' => $document->id,
            'from_state' => 'processing',
            'to_state' => 'needs_review',
        ]);
    }

    public function test_confirmation_activates_deadlines_once_and_persists_confirmed_values_separately(): void
    {
        [$owner, $household] = $this->householdWithOwner('Alex');
        $document = $this->uploadImage($owner, $household, 'upload-proof-0005');
        $this->process($document, new DeterministicFixtureExtractor);

        $response = $this->actingAs($owner)
            ->withHeader('Idempotency-Key', 'confirm-proof-0001')
            ->putJson(
                '/api/v1/households/'.$household->id.'/documents/'.$document->id.'/confirmation',
                $this->confirmationPayload(),
            );

        $response->assertOk()
            ->assertJsonPath('data.state', 'ready')
            ->assertJsonPath('data.purchase.state', 'active')
            ->assertJsonPath('data.purchase.merchant', 'Northstar Home')
            ->assertJsonFragment(['kind' => 'return', 'due_on' => '2026-08-07'])
            ->assertJsonFragment(['kind' => 'warranty', 'due_on' => '2027-07-08']);
        $this->assertDatabaseHas('extraction_fields', [
            'receipt_document_id' => $document->id,
            'field_key' => 'merchant',
            'raw_value' => 'Northstar Home',
            'confirmed_value' => 'Northstar Home',
        ]);
        $this->assertDatabaseCount('purchase_deadlines', 2);

        $this->actingAs($owner)
            ->withHeader('Idempotency-Key', 'confirm-proof-0001')
            ->putJson(
                '/api/v1/households/'.$household->id.'/documents/'.$document->id.'/confirmation',
                $this->confirmationPayload(),
            )
            ->assertOk()
            ->assertJsonPath('data.state', 'ready');
        $this->assertDatabaseCount('purchase_deadlines', 2);
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'receipt.confirmed')->count());
    }

    public function test_confirmation_rolls_back_every_authoritative_change_when_a_late_write_fails(): void
    {
        [$owner, $household] = $this->householdWithOwner('Alex');
        $document = $this->uploadImage($owner, $household, 'upload-confirmation-rollback');
        $this->process($document, new DeterministicFixtureExtractor);

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER reject_confirmation_audit
            BEFORE INSERT ON audit_logs
            WHEN NEW.action = 'receipt.confirmed'
            BEGIN
                SELECT RAISE(FAIL, 'simulated late confirmation failure');
            END;
            SQL);

        $this->actingAs($owner)
            ->withHeader('Idempotency-Key', 'confirm-rollback-0001')
            ->putJson(
                '/api/v1/households/'.$household->id.'/documents/'.$document->id.'/confirmation',
                $this->confirmationPayload(),
            )
            ->assertInternalServerError()
            ->assertJsonPath('detail', 'An unexpected error occurred.')
            ->assertJsonMissing(['detail' => 'simulated late confirmation failure']);

        $document->refresh()->load(['fields', 'purchase']);
        self::assertSame(DocumentState::NeedsReview, $document->state);
        self::assertSame('needs_review', $document->purchase->state->value);
        self::assertNull($document->purchase->activated_at);
        self::assertTrue($document->fields->every(
            static fn (ExtractionField $field): bool => $field->confirmed_value === null,
        ));
        $this->assertDatabaseMissing('purchase_deadlines', ['purchase_id' => $document->purchase_id]);
        $this->assertDatabaseCount('reminders', 0);
        $this->assertDatabaseMissing('idempotency_records', [
            'operation' => 'receipt.confirm',
            'resource_id' => $document->id,
        ]);
        $this->assertDatabaseMissing('document_state_transitions', [
            'receipt_document_id' => $document->id,
            'to_state' => 'ready',
        ]);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'purchase.updated']);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'receipt.confirmed']);
    }

    public function test_transient_failure_moves_to_failed_and_a_later_run_recovers_idempotently(): void
    {
        [$owner, $household] = $this->householdWithOwner('Alex');
        $document = $this->uploadImage($owner, $household, 'upload-proof-0006');
        $failing = new class implements DocumentTextExtractor
        {
            public function extract(string $binary, string $mimeType): ExtractedDocumentText
            {
                throw new TransientDocumentFailure('ocr_temporarily_unavailable');
            }
        };

        try {
            $this->process($document, $failing);
            self::fail('The transient OCR failure should be rethrown for the queue retry policy.');
        } catch (TransientDocumentFailure $exception) {
            self::assertSame('ocr_temporarily_unavailable', $exception->failureCode);
        }
        self::assertSame(DocumentState::Failed, $document->refresh()->state);

        $this->process($document, new DeterministicFixtureExtractor);
        self::assertSame(DocumentState::NeedsReview, $document->refresh()->state);
        self::assertSame(2, $document->processing_attempts);
        $this->assertSame(1, DB::table('extraction_fields')
            ->where('receipt_document_id', $document->id)
            ->where('field_key', 'merchant')
            ->count());
    }

    public function test_failed_receipt_can_be_retried_through_the_api_but_quarantine_is_terminal(): void
    {
        [$owner, $household] = $this->householdWithOwner('Alex');
        $document = $this->uploadImage($owner, $household, 'upload-api-retry');
        $failing = new class implements DocumentTextExtractor
        {
            public function extract(string $binary, string $mimeType): ExtractedDocumentText
            {
                throw new TransientDocumentFailure('ocr_temporarily_unavailable');
            }
        };

        try {
            $this->process($document, $failing);
            self::fail('The synthetic transient failure must reach the queue retry boundary.');
        } catch (TransientDocumentFailure) {
            self::assertSame(DocumentState::Failed, $document->refresh()->state);
        }

        $this->actingAs($owner)
            ->postJson('/api/v1/households/'.$household->id.'/documents/'.$document->id.'/retry')
            ->assertAccepted()
            ->assertJsonPath('data.state', 'validation_pending')
            ->assertJsonPath('data.user_error', null);
        Queue::assertPushed(ProcessReceipt::class, 2);
        $this->assertDatabaseHas('document_state_transitions', [
            'receipt_document_id' => $document->id,
            'from_state' => 'failed',
            'to_state' => 'validation_pending',
            'reason' => 'manual_retry_requested',
        ]);

        $document->update(['state' => DocumentState::Quarantined]);
        $this->actingAs($owner)
            ->postJson('/api/v1/households/'.$household->id.'/documents/'.$document->id.'/retry')
            ->assertUnprocessable()
            ->assertJsonPath(
                'errors.document.0',
                'Only a failed receipt can be retried. Quarantined files cannot be processed.',
            );
    }

    public function test_active_pdf_content_is_quarantined_without_retry(): void
    {
        [$owner, $household] = $this->householdWithOwner('Alex');
        $response = $this->actingAs($owner)
            ->withHeader('Idempotency-Key', 'upload-proof-0007')
            ->post('/api/v1/households/'.$household->id.'/receipts', [
                'receipt' => UploadedFile::fake()->createWithContent(
                    'unsafe.pdf',
                    "%PDF-1.4\n1 0 obj << /OpenAction 2 0 R /JavaScript (alert) >>\nendobj\n%%EOF",
                ),
            ])
            ->assertAccepted();
        $documentId = $response->json('data.id');
        self::assertIsString($documentId);
        $document = ReceiptDocument::query()->findOrFail($documentId);

        $this->process($document, new DeterministicFixtureExtractor);

        self::assertSame(DocumentState::Quarantined, $document->refresh()->state);
        self::assertSame('active_pdf_content', $document->last_error_code);
        $this->assertDatabaseCount('extraction_fields', 0);
    }

    public function test_invalid_state_transition_is_rejected_and_logged_state_is_unchanged(): void
    {
        [$owner, $household] = $this->householdWithOwner('Alex');
        $document = $this->uploadImage($owner, $household, 'upload-proof-0008');

        try {
            app(TransitionDocumentState::class)->execute(
                $document,
                DocumentState::Ready,
                'invalid_shortcut',
                $document->correlation_id,
            );
            self::fail('An invalid state transition must fail.');
        } catch (InvalidDocumentTransition) {
            self::assertSame(DocumentState::ValidationPending, $document->refresh()->state);
        }
    }

    public function test_document_binding_hides_another_households_receipt(): void
    {
        [$owner, $household] = $this->householdWithOwner('Alex');
        [$intruder, $foreignHousehold] = $this->householdWithOwner('Intruder');
        $document = $this->uploadImage($owner, $household, 'upload-proof-0009');

        $this->actingAs($intruder)
            ->getJson('/api/v1/households/'.$household->id.'/documents/'.$document->id)
            ->assertNotFound();
        $this->actingAs($intruder)
            ->getJson('/api/v1/households/'.$foreignHousehold->id.'/documents/'.$document->id)
            ->assertNotFound();
    }

    public function test_private_download_uses_a_bounded_same_origin_gateway_and_streams_verified_content(): void
    {
        [$owner, $household] = $this->householdWithOwner('Alex');
        $document = $this->uploadImage($owner, $household, 'upload-proof-0010');
        $this->process($document, new DeterministicFixtureExtractor);

        $response = $this->actingAs($owner)
            ->postJson('/api/v1/households/'.$household->id.'/documents/'.$document->id.'/download')
            ->assertOk();

        $cacheControl = (string) $response->headers->get('Cache-Control');
        self::assertStringContainsString('private', $cacheControl);
        self::assertStringContainsString('no-store', $cacheControl);
        $url = $response->json('data.url');
        self::assertIsString($url);
        self::assertStringStartsWith('/api/v1/receipt-downloads/'.$document->id.'?', $url);
        self::assertStringNotContainsString('object-storage', $url);
        self::assertStringNotContainsString($document->storage_key, (string) $response->getContent());
        $expiresAt = $response->json('data.expires_at');
        self::assertIsString($expiresAt);
        self::assertLessThanOrEqual(
            301,
            now()->diffInSeconds($expiresAt, absolute: true),
        );
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'receipt.download_url_created',
            'subject_id' => $document->id,
        ]);
        $metadata = DB::table('audit_logs')
            ->where('action', 'receipt.download_url_created')
            ->value('metadata');
        self::assertIsString($metadata);
        self::assertStringNotContainsString($document->storage_key, $metadata);
        self::assertStringNotContainsString('receipt-downloads', $metadata);

        $download = $this->actingAs($owner)->get($url)
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $downloadCacheControl = (string) $download->headers->get('Cache-Control');
        self::assertStringContainsString('private', $downloadCacheControl);
        self::assertStringContainsString('no-store', $downloadCacheControl);
        self::assertStringContainsString('max-age=0', $downloadCacheControl);
        $contentDisposition = (string) $download->headers->get('Content-Disposition');
        self::assertStringContainsString('attachment', $contentDisposition);
        self::assertStringContainsString('receipt.png', $contentDisposition);
        self::assertStringStartsWith("\x89PNG\r\n\x1a\n", $download->streamedContent());
    }

    public function test_private_download_rejects_tampering_anonymous_access_and_another_tenant(): void
    {
        [$owner, $household] = $this->householdWithOwner('Alex');
        [$intruder] = $this->householdWithOwner('Intruder');
        $document = $this->uploadImage($owner, $household, 'upload-proof-download-access');
        $this->process($document, new DeterministicFixtureExtractor);
        $response = $this->actingAs($owner)
            ->postJson('/api/v1/households/'.$household->id.'/documents/'.$document->id.'/download')
            ->assertOk();
        $url = $response->json('data.url');
        self::assertIsString($url);

        $this->actingAs($owner)->get($url.'&tampered=1')->assertForbidden();
        $this->actingAs($intruder)->get($url)->assertForbidden();
        Auth::guard('web')->logout();
        Auth::forgetGuards();
        $this->get($url)->assertUnauthorized();

        $this->actingAs($owner);
        $this->travel(6)->minutes();
        $this->get($url)->assertForbidden();
        $this->travelBack();
    }

    public function test_quarantine_or_original_deletion_revokes_an_already_minted_download_url(): void
    {
        [$owner, $household] = $this->householdWithOwner('Alex');
        $document = $this->uploadImage($owner, $household, 'upload-proof-0011');
        $this->process($document, new DeterministicFixtureExtractor);
        $response = $this->actingAs($owner)
            ->postJson('/api/v1/households/'.$household->id.'/documents/'.$document->id.'/download')
            ->assertOk();
        $url = $response->json('data.url');
        self::assertIsString($url);

        $document->update(['state' => DocumentState::Quarantined]);

        $this->actingAs($owner)->get($url)->assertNotFound();
        $this->actingAs($owner)
            ->postJson('/api/v1/households/'.$household->id.'/documents/'.$document->id.'/download')
            ->assertNotFound();

        $document->update(['state' => DocumentState::Failed, 'retain_original' => false]);
        $this->actingAs($owner)->get($url)->assertNotFound();
        $this->actingAs($owner)
            ->postJson('/api/v1/households/'.$household->id.'/documents/'.$document->id.'/download')
            ->assertNotFound();
    }

    public function test_user_can_delete_a_processed_original_once_without_deleting_extracted_data(): void
    {
        [$owner, $household] = $this->householdWithOwner('Alex');
        $document = $this->uploadImage($owner, $household, 'upload-delete-original');
        $this->process($document, new DeterministicFixtureExtractor);
        Storage::disk('receipts')->assertExists($document->storage_key);

        $url = '/api/v1/households/'.$household->id.'/documents/'.$document->id.'/original';
        $this->actingAs($owner)->deleteJson($url)
            ->assertOk()
            ->assertJsonPath('data.retain_original', false)
            ->assertJsonFragment([
                'key' => 'merchant',
                'normalized_value' => 'Northstar Home',
            ]);

        Storage::disk('receipts')->assertMissing($document->storage_key);
        $this->assertDatabaseHas('receipt_documents', [
            'id' => $document->id,
            'retain_original' => false,
        ]);
        $this->assertDatabaseHas('extraction_fields', ['receipt_document_id' => $document->id]);
        $this->actingAs($owner)->deleteJson($url)
            ->assertOk()
            ->assertJsonPath('data.retain_original', false);
        self::assertSame(1, DB::table('audit_logs')
            ->where('action', 'receipt.original_deleted')
            ->where('subject_id', $document->id)
            ->count());

        $this->actingAs($owner)
            ->postJson('/api/v1/households/'.$household->id.'/documents/'.$document->id.'/download')
            ->assertNotFound();
    }

    public function test_queue_dispatch_outage_keeps_reconcilable_database_work(): void
    {
        $this->app->instance(Dispatcher::class, new FailingBusDispatcher);
        [$owner, $household] = $this->householdWithOwner('Alex');

        $response = $this->actingAs($owner)
            ->withHeader('Idempotency-Key', 'upload-proof-0012')
            ->post('/api/v1/households/'.$household->id.'/receipts', [
                'receipt' => UploadedFile::fake()->image('receipt.png', 100, 100),
            ])
            ->assertAccepted()
            ->assertJsonPath('data.state', 'validation_pending');

        $documentId = $response->json('data.id');
        self::assertIsString($documentId);
        $document = ReceiptDocument::query()->findOrFail($documentId);
        self::assertNull($document->processing_dispatched_at);
        self::assertSame('queue_dispatch_unavailable', $document->last_error_code);
        Storage::disk('receipts')->assertExists($document->storage_key);
    }

    public function test_reconciler_dispatches_only_stale_database_backed_work(): void
    {
        [$owner, $household] = $this->householdWithOwner('Alex');
        $stale = $this->uploadImage($owner, $household, 'upload-proof-0013');
        $fresh = $this->uploadImage($owner, $household, 'upload-proof-0014');
        $stale->forceFill([
            'processing_dispatched_at' => now()->subMinutes(10),
            'last_error_code' => 'queue_dispatch_unavailable',
            'user_error' => 'Receipt processing is delayed and will be retried safely.',
        ])->save();
        config()->set('receipt_processing.reconcile_after_seconds', 300);
        Queue::fake();

        self::assertSame(0, Artisan::call('keepproof:receipts:reconcile'));
        self::assertStringContainsString(
            'dispatched 1 job(s); 0 dispatch(es) failed',
            Artisan::output(),
        );

        Queue::assertPushed(
            ProcessReceipt::class,
            static fn (ProcessReceipt $job): bool => $job->documentId === $stale->id,
        );
        Queue::assertNotPushed(
            ProcessReceipt::class,
            static fn (ProcessReceipt $job): bool => $job->documentId === $fresh->id,
        );
        $stale->refresh();
        self::assertNotNull($stale->processing_dispatched_at);
        self::assertNull($stale->last_error_code);
        self::assertNull($stale->user_error);
    }

    private function uploadImage(User $owner, Household $household, string $key): ReceiptDocument
    {
        $response = $this->actingAs($owner)
            ->withHeader('Idempotency-Key', $key)
            ->post('/api/v1/households/'.$household->id.'/receipts', [
                'receipt' => UploadedFile::fake()->image('receipt.png', 100, 100),
            ])
            ->assertAccepted();
        $documentId = $response->json('data.id');
        self::assertIsString($documentId);

        return ReceiptDocument::query()->findOrFail($documentId);
    }

    private function process(ReceiptDocument $document, DocumentTextExtractor $extractor): void
    {
        (new ProcessReceipt($document->id, $document->correlation_id))->handle(
            app(TransitionDocumentState::class),
            app(ValidateReceiptContent::class),
            $extractor,
            app(ReceiptDataExtractor::class),
        );
    }

    /** @return array<string, mixed> */
    private function confirmationPayload(): array
    {
        return [
            'merchant' => 'Northstar Home',
            'purchase_date' => '2026-07-08',
            'timezone' => 'America/New_York',
            'receipt_number' => 'NS-1042',
            'currency' => 'USD',
            'total_minor' => 13950,
            'country_region' => 'US',
            'return_period_quantity' => 30,
            'return_period_unit' => 'days',
            'return_counting_convention' => 'purchase_day_exclusive',
            'warranty_period_quantity' => 12,
            'warranty_period_unit' => 'months',
            'warranty_counting_convention' => 'purchase_day_exclusive',
            'notes' => 'Synthetic fixture only.',
            'items' => [[
                'name' => 'Linen floor lamp',
                'quantity' => 1,
                'unit_price_minor' => 13950,
                'serial_number' => null,
            ]],
        ];
    }

    /** @return array{User, Household} */
    private function householdWithOwner(string $name): array
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
