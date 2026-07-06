<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_template_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('document_template_id')->constrained('document_templates')->cascadeOnDelete();
            $table->foreignId('edited_by_member_id')->nullable()->constrained('members')->nullOnDelete();
            $table->unsignedInteger('version');
            $table->longText('body_html');
            $table->longText('header_html')->nullable();
            $table->longText('footer_html')->nullable();
            $table->longText('styles_css')->nullable();
            $table->jsonb('page_config')->nullable();
            $table->string('change_note')->nullable();
            $table->timestamps();

            $table->unique(['document_template_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_template_versions');
    }
};
