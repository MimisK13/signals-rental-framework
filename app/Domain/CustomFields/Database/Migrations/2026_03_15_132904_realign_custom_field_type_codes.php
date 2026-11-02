<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $mapping = [16 => 115, 15 => 118, 14 => 112, 13 => 117, 12 => 113, 11 => 107, 10 => 108, 9 => 110, 8 => 109, 7 => 106, 6 => 104, 4 => 103, 3 => 102];

        foreach ($mapping as $oldCode => $temporaryCode) {
            DB::table('custom_fields')->where('field_type', $oldCode)->update(['field_type' => $temporaryCode]);
        }

        foreach ($mapping as $temporaryCode) {
            DB::table('custom_fields')->where('field_type', $temporaryCode)->update(['field_type' => $temporaryCode - 100]);
        }
    }

    public function down(): void
    {
        $mapping = [2 => 103, 3 => 104, 4 => 106, 6 => 107, 7 => 111, 8 => 110, 9 => 108, 10 => 109, 12 => 114, 13 => 112, 15 => 116, 17 => 113, 18 => 115];

        foreach ($mapping as $newCode => $temporaryCode) {
            DB::table('custom_fields')->where('field_type', $newCode)->update(['field_type' => $temporaryCode]);
        }

        foreach ($mapping as $temporaryCode) {
            DB::table('custom_fields')->where('field_type', $temporaryCode)->update(['field_type' => $temporaryCode - 100]);
        }
    }
};
