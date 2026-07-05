<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Prevent concurrent "set default" requests from persisting two defaults for the
 * same store + document type (document-template-engine.md §12.1).
 *
 * SQLite tests rely on the SetDefaultDocumentTemplate action transaction instead.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(<<<'SQL'
            UPDATE document_templates AS dt
            SET is_default = false
            FROM (
                SELECT store_id, document_type, MIN(id) AS keep_id
                FROM document_templates
                WHERE is_default = true
                  AND deleted_at IS NULL
                GROUP BY store_id, document_type
                HAVING COUNT(*) > 1
            ) AS winners
            WHERE dt.is_default = true
              AND dt.deleted_at IS NULL
              AND dt.store_id = winners.store_id
              AND dt.document_type = winners.document_type
              AND dt.id <> winners.keep_id
        SQL);

        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX uq_document_templates_default_per_type
              ON document_templates (store_id, document_type)
              WHERE is_default = true AND deleted_at IS NULL
        SQL);
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS uq_document_templates_default_per_type');
    }
};
