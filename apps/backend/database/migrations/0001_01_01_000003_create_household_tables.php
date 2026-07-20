<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('households', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('name', 120);
            $table->foreignUlid('owner_user_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('is_personal')->default(false);
            $table->timestamps();

            $table->index(['owner_user_id', 'created_at']);
        });

        Schema::create('household_members', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('household_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->enum('role', ['owner', 'member']);
            $table->timestamp('joined_at');
            $table->timestamps();

            $table->unique(['household_id', 'user_id']);
            $table->index(['user_id', 'role']);
        });

        Schema::create('household_invitations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('household_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('invited_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('email');
            $table->enum('role', ['owner', 'member']);
            $table->char('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();

            $table->index(['household_id', 'email']);
            $table->index(['email', 'expires_at']);
        });

        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('household_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 100);
            $table->string('subject_type', 100)->nullable();
            $table->string('subject_id', 64)->nullable();
            $table->uuid('correlation_id')->nullable();
            $table->json('metadata');
            $table->timestamp('occurred_at');

            $table->index(['actor_user_id', 'occurred_at']);
            $table->index(['household_id', 'occurred_at']);
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('household_invitations');
        Schema::dropIfExists('household_members');
        Schema::dropIfExists('households');
    }
};
