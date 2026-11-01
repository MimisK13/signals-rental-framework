<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crew_member_qualifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('crew_member_id')->constrained('crew_members')->cascadeOnDelete();
            $table->foreignId('crew_qualification_id')->constrained('crew_qualifications')->restrictOnDelete();
            $table->string('reference_number', 80)->nullable();
            $table->date('issued_at')->nullable();
            $table->date('expires_at')->nullable();
            $table->dateTime('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['crew_member_id', 'expires_at']);
            $table->index(['crew_qualification_id', 'expires_at']);
            $table->unique(
                ['crew_member_id', 'crew_qualification_id', 'reference_number'],
                'crew_member_qualifications_member_qualification_reference_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crew_member_qualifications');
    }
};
