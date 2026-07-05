<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('created_by_member_id')->nullable()->constrained('members')->nullOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('document_type');
            $table->string('model_type');
            $table->longText('body_html');
            $table->longText('header_html')->nullable();
            $table->longText('footer_html')->nullable();
            $table->longText('styles_css')->nullable();
            $table->jsonb('page_config')->nullable();
            $table->jsonb('branding_config')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('version')->default(1);
            $table->timestamp('last_edited_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['store_id', 'document_type', 'is_active']);
            $table->unique(['store_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_templates');
    }
};
