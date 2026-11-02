<?php

declare(strict_types=1);

use App\Domain\Documents\Services\SystemDocumentTemplateMigration;
use App\Domain\Documents\Services\SystemDocumentTemplates;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        app(SystemDocumentTemplateMigration::class)->refresh(SystemDocumentTemplates::CORE_TYPES, seedMissing: false);
    }

    public function down(): void
    {
        // System template content refreshes are intentionally irreversible.
    }
};
