<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('opportunities', 'project_id')) {
            return;
        }

        Schema::table('opportunities', function (Blueprint $table): void {
            $table->foreignId('project_id')->nullable()->after('owned_by')->constrained('projects')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('opportunities', 'project_id')) {
            return;
        }

        Schema::table('opportunities', function (Blueprint $table): void {
            $table->dropForeign(['project_id']);
            $table->dropColumn('project_id');
        });
    }
};
