<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchases', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('household_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('state', 24)->default('draft');
            $table->string('merchant', 160);
            $table->date('purchase_date');
            $table->string('timezone', 64);
            $table->string('receipt_number', 120)->nullable();
            $table->char('currency', 3);
            $table->unsignedBigInteger('total_minor');
            $table->string('country_region', 80);
            $table->unsignedSmallInteger('return_period_quantity')->nullable();
            $table->string('return_period_unit', 12)->nullable();
            $table->string('return_counting_convention', 32)->default('purchase_day_exclusive');
            $table->unsignedSmallInteger('warranty_period_quantity')->nullable();
            $table->string('warranty_period_unit', 12)->nullable();
            $table->string('warranty_counting_convention', 32)->default('purchase_day_exclusive');
            $table->timestampTz('manual_return_deadline_at')->nullable();
            $table->string('manual_return_reason', 240)->nullable();
            $table->timestampTz('manual_warranty_deadline_at')->nullable();
            $table->string('manual_warranty_reason', 240)->nullable();
            $table->text('notes')->nullable();
            $table->timestampTz('activated_at')->nullable();
            $table->timestampsTz();

            $table->index(['household_id', 'state', 'purchase_date']);
            $table->index(['household_id', 'merchant']);
            $table->index(['household_id', 'created_at']);
        });

        Schema::create('purchase_items', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('purchase_id')->constrained()->cascadeOnDelete();
            $table->string('name', 200);
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedBigInteger('unit_price_minor')->nullable();
            $table->string('serial_number', 160)->nullable();
            $table->timestampsTz();

            $table->index(['purchase_id', 'created_at']);
        });

        Schema::create('purchase_deadlines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('purchase_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('household_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 16);
            $table->timestampTz('due_at');
            $table->string('local_timezone', 64);
            $table->string('source', 24);
            $table->string('rule_summary', 500);
            $table->json('trace');
            $table->json('reminder_intervals');
            $table->boolean('active')->default(true);
            $table->timestampsTz();

            $table->unique(['purchase_id', 'kind']);
            $table->index(['household_id', 'active', 'due_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_deadlines');
        Schema::dropIfExists('purchase_items');
        Schema::dropIfExists('purchases');
    }
};
