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
            $table->timestampTz('processing_dispatched_at')->nullable()->after('processing_attempts');
            $table->index(['state', 'processing_dispatched_at'], 'receipt_processing_dispatch_idx');
        });
    }

    public function down(): void
    {
        Schema::table('receipt_documents', function (Blueprint $table): void {
            $table->dropIndex('receipt_processing_dispatch_idx');
            $table->dropColumn('processing_dispatched_at');
        });
    }
};
