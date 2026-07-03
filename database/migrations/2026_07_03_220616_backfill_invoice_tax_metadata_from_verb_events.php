<?php

use App\Verbs\Events\Invoices\InvoiceGenerated;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Backfill invoice_taxes metadata columns from persisted InvoiceGenerated payloads.
 *
 * These fields are frozen at fire-time in the event payload but were not
 * projected until P37. Existing rows are hydrated from verb_events.data.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('verb_events') || ! Schema::hasTable('invoice_taxes')) {
            return;
        }

        $eventType = InvoiceGenerated::class;

        DB::table('verb_events')
            ->where('type', $eventType)
            ->orderBy('id')
            ->each(function (object $event): void {
                /** @var array<string, mixed>|null $payload */
                $payload = json_decode((string) $event->data, true);

                if (! is_array($payload)) {
                    return;
                }

                /** @var list<array<string, mixed>> $taxRows */
                $taxRows = $payload['tax_rows'] ?? [];

                foreach ($taxRows as $taxRow) {
                    $taxId = (int) ($taxRow['invoice_tax_id'] ?? 0);

                    if ($taxId <= 0) {
                        continue;
                    }

                    DB::table('invoice_taxes')
                        ->where('id', $taxId)
                        ->update([
                            'rate_percentage' => $taxRow['rate_percentage'] ?? null,
                            'tax_rate_name' => $taxRow['tax_rate_name'] ?? null,
                            'result_type' => $taxRow['result_type'] ?? null,
                            'legal_text' => $taxRow['legal_text'] ?? null,
                        ]);
                }
            });
    }

    public function down(): void
    {
        // Projection columns are cleared by the preceding migration rollback.
    }
};
