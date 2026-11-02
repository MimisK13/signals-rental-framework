<?php

declare(strict_types=1);

use App\Domain\Tax\Enums\TaxBuyerType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('members')) {
            return;
        }

        DB::table('members')->where('buyer_type', 'b2b')->update([
            'buyer_type' => TaxBuyerType::Business->value,
        ]);
        DB::table('members')->where('buyer_type', 'b2c')->update([
            'buyer_type' => TaxBuyerType::Individual->value,
        ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('members')) {
            return;
        }

        DB::table('members')->where('buyer_type', TaxBuyerType::Business->value)->update([
            'buyer_type' => 'b2b',
        ]);
        DB::table('members')->where('buyer_type', TaxBuyerType::Individual->value)->update([
            'buyer_type' => 'b2c',
        ]);
    }
};
