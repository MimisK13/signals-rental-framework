<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table): void {
            $table->boolean('tax_number_validated')->nullable()->after('tax_number');
            $table->timestamp('tax_number_validated_at')->nullable()->after('tax_number_validated');
            $table->jsonb('tax_number_validation')->nullable()->after('tax_number_validated_at');
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table): void {
            $table->dropColumn([
                'tax_number_validated',
                'tax_number_validated_at',
                'tax_number_validation',
            ]);
        });
    }
};
