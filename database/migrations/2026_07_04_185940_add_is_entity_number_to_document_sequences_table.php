<?php

use App\Services\Documents\DocumentSequenceEntityProjection;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_sequences', function (Blueprint $table): void {
            $table->boolean('is_entity_number')->default(false)->after('document_type');
        });

        DB::table('document_sequences')
            ->whereIn('document_type', DocumentSequenceEntityProjection::ENTITY_DOCUMENT_TYPES)
            ->update(['is_entity_number' => true]);
    }

    public function down(): void
    {
        Schema::table('document_sequences', function (Blueprint $table): void {
            $table->dropColumn('is_entity_number');
        });
    }
};
