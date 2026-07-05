<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('communication_template_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('communication_template_id')->constrained('communication_templates')->cascadeOnDelete();
            $table->foreignId('edited_by_member_id')->nullable()->constrained('members')->nullOnDelete();
            $table->unsignedInteger('version');
            $table->string('subject')->nullable();
            $table->text('body');
            $table->string('change_note')->nullable();
            $table->timestamps();

            $table->unique(['communication_template_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('communication_template_versions');
    }
};
