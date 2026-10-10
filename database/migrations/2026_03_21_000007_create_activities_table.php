<?php

use App\Enums\ActivityType;
use App\Models\ListName;
use App\Models\ListValue;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->string('subject', 255);
            $table->text('description')->nullable();
            $table->string('location', 255)->nullable();
            $table->unsignedBigInteger('regarding_id')->nullable();
            $table->string('regarding_type', 255)->nullable();
            $table->foreignId('owned_by')->constrained('users');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->integer('priority')->default(1);
            $table->unsignedBigInteger('type_id')->nullable();
            $table->integer('status_id')->default(2001);
            $table->boolean('completed')->default(false);
            $table->integer('time_status')->default(0);
            $table->jsonb('tag_list')->nullable();
            $table->timestamps();

            $table->index(['regarding_type', 'regarding_id']);
            $table->index('owned_by');
            $table->foreign('type_id')->references('id')->on('list_values')->nullOnDelete();
            $table->index('type_id');
            $table->index('status_id');
            $table->index('starts_at');
        });

        $list = ListName::query()->firstOrCreate(
            ['name' => 'Activity Type'],
            [
                'description' => 'Activity Type options',
                'is_system' => true,
            ],
        );

        foreach (ActivityType::cases() as $index => $case) {
            ListValue::query()->firstOrCreate(
                ['list_name_id' => $list->id, 'name' => $case->label()],
                [
                    'sort_order' => $index,
                    'is_system' => true,
                    'is_active' => true,
                    'metadata' => ['icon' => $case->icon()],
                ],
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('activities');
    }
};
