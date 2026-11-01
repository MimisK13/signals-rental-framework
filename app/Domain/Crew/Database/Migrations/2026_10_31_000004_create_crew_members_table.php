<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const DATES_ORDERED_CHECK = 'crew_members_dates_ordered_check';

    public function up(): void
    {
        Schema::create('crew_members', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('member_id')->nullable()->constrained('members')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('supplier_member_id')->nullable()->constrained('members')->nullOnDelete();
            $table->string('engagement_type', 24)->default('employee');
            $table->string('status', 24)->default('active');
            $table->string('name', 160);
            $table->string('email', 190)->nullable();
            $table->string('phone', 60)->nullable();
            $table->string('job_title', 120)->nullable();
            $table->string('home_timezone', 64)->default('UTC');
            $table->string('home_postcode', 20)->nullable();
            $table->smallInteger('travel_radius_km')->nullable();
            $table->text('internal_notes')->nullable();
            $table->string('external_reference', 60)->nullable();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->dateTime('last_assigned_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique('user_id');
            $table->index(['status', 'engagement_type']);
            $table->index(['engagement_type', 'status']);
            $table->index('member_id');
            $table->index('supplier_member_id');
            $table->index('name');
        });

        $connection = Schema::getConnection();

        if ($connection->getDriverName() === 'pgsql') {
            $connection->statement(sprintf(
                'ALTER TABLE crew_members ADD CONSTRAINT %s CHECK (
                    ends_on IS NULL OR starts_on IS NULL OR ends_on >= starts_on
                )',
                self::DATES_ORDERED_CHECK,
            ));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('crew_members');
    }
};
