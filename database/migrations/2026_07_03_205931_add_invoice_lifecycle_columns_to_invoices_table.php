<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Projection-only lifecycle columns for P32 — not part of the event-sourced
 * status model (overdue is derived; overdue_notified_at is a side-effect marker).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->boolean('is_locked')->default(false)->after('status');
            $table->dateTime('overdue_notified_at')->nullable()->after('due_at');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropColumn(['is_locked', 'overdue_notified_at']);
        });
    }
};
