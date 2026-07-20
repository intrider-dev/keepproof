<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('receipt_documents', function (Blueprint $table): void {
            $table->timestampTz('external_ai_consent_at')->nullable()->after('prompt_version');
        });
    }

    public function down(): void
    {
        Schema::table('receipt_documents', function (Blueprint $table): void {
            $table->dropColumn('external_ai_consent_at');
        });
    }
};
