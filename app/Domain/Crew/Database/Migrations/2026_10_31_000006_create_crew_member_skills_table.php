<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crew_member_skills', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('crew_member_id')->constrained('crew_members')->cascadeOnDelete();
            $table->foreignId('crew_skill_id')->constrained('crew_skills')->restrictOnDelete();
            $table->string('proficiency', 16)->default('competent');
            $table->unsignedTinyInteger('years_experience')->nullable();
            $table->dateTime('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['crew_member_id', 'crew_skill_id']);
            $table->index(['crew_skill_id', 'proficiency']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crew_member_skills');
    }
};
