<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Heal tenants provisioned before the base-currency key was aligned.
 *
 * The setup wizard / CLI used to write the company currency under the orphan key
 * `company.currency`, but the money engine reads `company.base_currency`. Installs
 * that ran setup before that fix therefore have the value under the wrong key and
 * fall back to the GBP default. Copy the orphan value into the canonical key when
 * the canonical key has no stored value. Idempotent + non-destructive (the orphan
 * row is left in place — it is unreferenced and harmless).
 */
return new class extends Migration
{
    public function up(): void
    {
        $orphanValue = DB::table('settings')
            ->where('group', 'company')
            ->where('key', 'currency')
            ->value('value');

        if (! is_string($orphanValue) || $orphanValue === '') {
            return;
        }

        $hasCanonical = DB::table('settings')
            ->where('group', 'company')
            ->where('key', 'base_currency')
            ->exists();

        if (! $hasCanonical) {
            DB::table('settings')->insert([
                'group' => 'company',
                'key' => 'base_currency',
                'value' => $orphanValue,
            ]);
        }
    }

    public function down(): void
    {
        // Data heal — not reversible.
    }
};
