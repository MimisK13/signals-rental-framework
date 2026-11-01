<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crew_member_roles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('crew_member_id')->constrained('crew_members')->cascadeOnDelete();
            $table->foreignId('crew_role_id')->constrained('crew_roles')->restrictOnDelete();
            $table->string('proficiency', 16)->default('competent');
            $table->boolean('is_primary')->default(false);
            $table->unsignedTinyInteger('years_experience')->nullable();
            $table->timestamps();

            $table->unique(['crew_member_id', 'crew_role_id']);
            $table->index(['crew_role_id', 'proficiency', 'is_primary']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crew_member_roles');
    }
};
