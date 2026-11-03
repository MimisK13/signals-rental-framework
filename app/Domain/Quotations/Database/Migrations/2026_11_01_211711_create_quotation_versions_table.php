<?php

declare(strict_types=1);

use App\Domain\Administration\Models\User;
use App\Domain\Members\Models\Member;
use App\Domain\Quotations\Models\Quotation;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotation_versions', function (Blueprint $table): void {
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedBigInteger('state_id')->unique();
            $table->foreignIdFor(Quotation::class)->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->unsignedBigInteger('parent_version_id')->nullable();
            $table->unsignedBigInteger('superseded_by_version_id')->nullable();
            $table->string('version_type', 32)->default('original');
            $table->string('label')->nullable();
            $table->boolean('is_active')->default(false);
            $table->string('status', 32)->default('draft');
            $table->bigInteger('charge_excluding_tax_total')->default(0);
            $table->bigInteger('tax_total')->default(0);
            $table->bigInteger('charge_including_tax_total')->default(0);
            $table->bigInteger('charge_total')->default(0);
            $table->string('currency_code', 3);
            $table->decimal('exchange_rate', 18, 8)->default(1);
            $table->boolean('prices_include_tax')->default(false);
            $table->text('notes')->nullable();
            $table->foreignIdFor(User::class, 'created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTimeTz('sent_at')->nullable();
            $table->foreignIdFor(Member::class, 'sent_to')->nullable()->constrained('members')->nullOnDelete();
            $table->string('sent_via', 32)->nullable();
            $table->dateTimeTz('accepted_at')->nullable();
            $table->foreignIdFor(Member::class, 'accepted_by')->nullable()->constrained('members')->nullOnDelete();
            $table->dateTimeTz('declined_at')->nullable();
            $table->string('decline_reason')->nullable();
            $table->timestampsTz();

            $table->unique(['quotation_id', 'version_number']);
            $table->index(['quotation_id', 'status']);
            $table->index(['quotation_id', 'is_active']);
            $table->index('parent_version_id');
            $table->index('superseded_by_version_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotation_versions');
    }
};
