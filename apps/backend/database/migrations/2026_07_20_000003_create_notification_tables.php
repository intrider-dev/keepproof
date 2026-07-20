<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_preferences', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('in_app_enabled')->default(true);
            $table->boolean('email_enabled')->default(true);
            $table->json('return_intervals');
            $table->json('warranty_intervals');
            $table->string('quiet_hours_start', 5)->nullable();
            $table->string('quiet_hours_end', 5)->nullable();
            $table->timestampsTz();
        });

        Schema::create('reminders', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('purchase_deadline_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('household_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->string('channel', 16);
            $table->unsignedSmallInteger('interval_days');
            $table->timestampTz('remind_at');
            $table->string('status', 16)->default('scheduled');
            $table->char('deduplication_key', 64)->unique();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestampTz('sent_at')->nullable();
            $table->string('last_error_code', 80)->nullable();
            $table->timestampsTz();

            $table->index(['status', 'remind_at']);
            $table->index(['user_id', 'status', 'remind_at']);
            $table->index(['household_id', 'remind_at']);
        });

        Schema::create('notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->string('notifiable_type');
            $table->ulid('notifiable_id');
            $table->text('data');
            $table->timestampTz('read_at')->nullable();
            $table->timestampsTz();

            $table->index(['notifiable_type', 'notifiable_id']);
            $table->index(['notifiable_id', 'read_at', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('reminders');
        Schema::dropIfExists('notification_preferences');
    }
};
