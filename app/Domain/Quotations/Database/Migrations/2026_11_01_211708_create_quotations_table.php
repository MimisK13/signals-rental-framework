<?php

declare(strict_types=1);

use App\Domain\Projects\Models\Project;
use App\Models\Member;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotations', function (Blueprint $table): void {
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedBigInteger('state_id')->unique();
            $table->foreignIdFor(Project::class)->nullable()->constrained()->restrictOnDelete();
            $table->foreignIdFor(Member::class)->constrained()->restrictOnDelete();
            $table->foreignIdFor(Member::class, 'venue_id')->nullable()->constrained('members')->nullOnDelete();
            $table->foreignIdFor(Store::class)->constrained()->restrictOnDelete();
            $table->foreignIdFor(Member::class, 'owned_by')->nullable()->constrained('members')->nullOnDelete();
            $table->string('number', 64);
            $table->string('subject');
            $table->string('reference')->nullable();
            $table->text('description')->nullable();
            $table->string('status', 32)->default('draft')->index();
            $table->dateTimeTz('starts_at')->nullable();
            $table->dateTimeTz('ends_at')->nullable();
            $table->dateTimeTz('valid_until')->nullable();
            $table->string('currency_code', 3);
            $table->decimal('exchange_rate', 18, 8)->default(1);
            $table->boolean('prices_include_tax')->default(false);
            $table->unsignedBigInteger('active_version_id')->nullable();
            $table->unsignedBigInteger('accepted_version_id')->nullable();
            $table->unsignedInteger('version_count')->default(0);
            $table->bigInteger('charge_excluding_tax_total')->default(0);
            $table->bigInteger('tax_total')->default(0);
            $table->bigInteger('charge_including_tax_total')->default(0);
            $table->bigInteger('charge_total')->default(0);
            $table->unsignedBigInteger('editor_revision')->default(1);
            $table->dateTimeTz('accepted_at')->nullable();
            $table->foreignIdFor(Member::class, 'accepted_by')->nullable()->constrained('members')->nullOnDelete();
            $table->foreignIdFor(User::class, 'created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['store_id', 'number']);
            $table->index(['project_id', 'status']);
            $table->index(['member_id', 'status']);
            $table->index(['starts_at', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotations');
    }
};
