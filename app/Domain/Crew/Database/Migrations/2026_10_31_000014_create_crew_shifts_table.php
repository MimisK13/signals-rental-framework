<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const DATES_ORDERED_CHECK = 'crew_shifts_dates_ordered_check';

    private const BREAK_NON_NEGATIVE_CHECK = 'crew_shifts_break_non_negative_check';

    public function up(): void
    {
        Schema::create('crew_shifts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->restrictOnDelete();
            $table->foreignId('project_id')->constrained('projects')->restrictOnDelete();
            $table->foreignId('crew_assignment_id')->nullable()->constrained('crew_assignments')->nullOnDelete();
            $table->foreignId('crew_member_id')->nullable()->constrained('crew_members')->restrictOnDelete();
            $table->foreignId('venue_member_id')->nullable()->constrained('members')->nullOnDelete();
            $table->string('status', 24)->default('planned');
            $table->string('type', 16)->default('show');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('timezone', 64)->default('UTC');
            $table->unsignedSmallInteger('break_minutes')->default(0);
            $table->boolean('is_overnight')->default(false);
            $table->string('location_name', 160)->nullable();
            $table->text('notes')->nullable();
            $table->smallInteger('sequence')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['crew_assignment_id', 'starts_at']);
            $table->index(['crew_member_id', 'starts_at', 'ends_at']);
            $table->index(['project_id', 'starts_at', 'ends_at']);
            $table->index(['store_id', 'starts_at', 'ends_at']);
            $table->index(['starts_at', 'ends_at']);
            $table->index(['status', 'starts_at']);
        });

        $connection = Schema::getConnection();

        if ($connection->getDriverName() === 'pgsql') {
            $connection->statement(sprintf(
                'ALTER TABLE crew_shifts ADD CONSTRAINT %s CHECK (ends_at > starts_at)',
                self::DATES_ORDERED_CHECK,
            ));

            $connection->statement(sprintf(
                'ALTER TABLE crew_shifts ADD CONSTRAINT %s CHECK (break_minutes >= 0)',
                self::BREAK_NON_NEGATIVE_CHECK,
            ));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('crew_shifts');
    }
};
