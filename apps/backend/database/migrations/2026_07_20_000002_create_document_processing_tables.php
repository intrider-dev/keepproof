<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receipt_documents', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('purchase_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('household_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('state', 32);
            $table->string('storage_disk', 32);
            $table->string('storage_key', 500)->unique();
            $table->string('original_filename', 255);
            $table->string('mime_type', 80);
            $table->unsignedBigInteger('size_bytes');
            $table->char('sha256', 64);
            $table->longText('raw_ocr_text')->nullable();
            $table->string('ocr_provider', 80)->nullable();
            $table->string('extraction_provider', 80)->nullable();
            $table->string('prompt_version', 80)->nullable();
            $table->unsignedSmallInteger('processing_attempts')->default(0);
            $table->uuid('correlation_id');
            $table->string('last_error_code', 80)->nullable();
            $table->string('user_error', 500)->nullable();
            $table->boolean('retain_original')->default(true);
            $table->timestampTz('processed_at')->nullable();
            $table->timestampsTz();

            $table->index(['household_id', 'state', 'created_at']);
            $table->index(['purchase_id', 'created_at']);
            $table->index(['household_id', 'sha256']);
        });

        Schema::create('extraction_fields', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('receipt_document_id')->constrained()->cascadeOnDelete();
            $table->string('field_key', 80);
            $table->text('raw_value')->nullable();
            $table->text('normalized_value')->nullable();
            $table->decimal('confidence', 5, 4)->nullable();
            $table->string('source', 160);
            $table->json('validation_errors');
            $table->text('confirmed_value')->nullable();
            $table->timestampsTz();

            $table->unique(['receipt_document_id', 'field_key']);
        });

        Schema::create('document_state_transitions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('receipt_document_id')->constrained()->cascadeOnDelete();
            $table->string('from_state', 32)->nullable();
            $table->string('to_state', 32);
            $table->string('reason', 120);
            $table->uuid('correlation_id');
            $table->json('metadata');
            $table->timestampTz('occurred_at');

            $table->index(['receipt_document_id', 'occurred_at']);
        });

        Schema::create('idempotency_records', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->string('operation', 80);
            $table->char('key_hash', 64);
            $table->string('resource_type', 100);
            $table->string('resource_id', 64);
            $table->timestampTz('expires_at');
            $table->timestampsTz();

            $table->unique(['user_id', 'operation', 'key_hash']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_records');
        Schema::dropIfExists('document_state_transitions');
        Schema::dropIfExists('extraction_fields');
        Schema::dropIfExists('receipt_documents');
    }
};
