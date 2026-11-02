<?php

declare(strict_types=1);

use App\Domain\Documents\Services\SystemDocumentTemplateMigration;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        app(SystemDocumentTemplateMigration::class)->snapshot(['return_list', 'statement']);
    }

    public function down(): void
    {
        // System template content refreshes are intentionally irreversible.
    }
};
