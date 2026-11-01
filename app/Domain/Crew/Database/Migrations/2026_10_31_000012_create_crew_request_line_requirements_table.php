<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const REQUIREMENT_TARGET_CHECK = 'crew_request_line_requirements_target_check';

    public function up(): void
    {
        Schema::create('crew_request_line_requirements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('crew_request_line_id')->constrained('crew_request_lines')->cascadeOnDelete();
            $table->string('requirement_type', 20);
            $table->foreignId('crew_skill_id')->nullable()->constrained('crew_skills')->cascadeOnDelete();
            $table->foreignId('crew_qualification_id')->nullable()->constrained('crew_qualifications')->cascadeOnDelete();
            $table->boolean('is_mandatory')->default(true);
            $table->string('min_proficiency', 16)->nullable();
            $table->timestamps();

            $table->index(['crew_request_line_id', 'requirement_type']);
        });

        $connection = Schema::getConnection();

        if ($connection->getDriverName() === 'pgsql') {
            $connection->statement(sprintf(
                "ALTER TABLE crew_request_line_requirements ADD CONSTRAINT %s CHECK (
                    (requirement_type = 'skill' AND crew_skill_id IS NOT NULL AND crew_qualification_id IS NULL)
                    OR
                    (requirement_type = 'qualification' AND crew_qualification_id IS NOT NULL AND crew_skill_id IS NULL)
                )",
                self::REQUIREMENT_TARGET_CHECK,
            ));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('crew_request_line_requirements');
    }
};
