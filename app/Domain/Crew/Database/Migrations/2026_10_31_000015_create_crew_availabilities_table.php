<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const DATES_ORDERED_CHECK = 'crew_availabilities_dates_ordered_check';

    public function up(): void
    {
        Schema::create('crew_availabilities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('crew_member_id')->constrained('crew_members')->cascadeOnDelete();
            $table->string('type', 20)->default('unavailable');
            $table->string('status', 20)->default('requested');
            $table->boolean('all_day')->default(false);
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('timezone', 64)->default('UTC');
            $table->string('reason', 160)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['crew_member_id', 'starts_at', 'ends_at']);
            $table->index(['status', 'starts_at']);
        });

        $connection = Schema::getConnection();

        if ($connection->getDriverName() === 'pgsql') {
            $connection->statement(sprintf(
                'ALTER TABLE crew_availabilities ADD CONSTRAINT %s CHECK (ends_at > starts_at)',
                self::DATES_ORDERED_CHECK,
            ));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('crew_availabilities');
    }
};
