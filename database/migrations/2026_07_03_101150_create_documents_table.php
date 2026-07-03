<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('document_template_id')->nullable()->constrained('document_templates')->nullOnDelete();
            $table->foreignId('generated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('document_type');
            $table->string('document_number');
            $table->string('documentable_type');
            $table->unsignedBigInteger('documentable_id');
            $table->string('pdf_path')->nullable();
            $table->string('html_snapshot_path')->nullable();
            $table->string('public_token')->nullable()->unique();
            $table->timestamp('public_expires_at')->nullable();
            $table->string('public_pin_hash')->nullable();
            $table->string('status')->default('draft');
            $table->jsonb('rendered_data')->nullable();
            $table->string('locale')->default('en');
            $table->string('currency_code', 3)->default('GBP');
            $table->unsignedInteger('template_version')->nullable();
            $table->timestamp('emailed_at')->nullable();
            $table->string('emailed_to')->nullable();
            $table->timestamp('first_viewed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['store_id', 'document_type']);
            $table->index(['documentable_type', 'documentable_id']);
            $table->index('document_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
