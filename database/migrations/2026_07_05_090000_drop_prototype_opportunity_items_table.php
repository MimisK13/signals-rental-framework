<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Removes the throwaway "Editor Lab" prototype table.
 *
 * The prototype editors (and their create-migration) were deleted with the
 * Editor Lab; this cleans the table off databases that migrated before the
 * removal. Fresh installs never create it, so this is a no-op there.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('prototype_opportunity_items');
    }

    public function down(): void
    {
        // Intentionally irreversible — the prototype feature no longer exists.
    }
};
