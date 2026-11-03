<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** @var array<string, string> */
    private const IDENTITIES = [
        'App\\Models\\Member' => 'App\\Domain\\Members\\Models\\Member',
        'App\\Models\\Membership' => 'App\\Domain\\Members\\Models\\Membership',
        'App\\Models\\MemberRelationship' => 'App\\Domain\\Members\\Models\\MemberRelationship',
        'App\\Models\\Address' => 'App\\Domain\\Members\\Models\\Address',
        'App\\Models\\Email' => 'App\\Domain\\Members\\Models\\Email',
        'App\\Models\\Phone' => 'App\\Domain\\Members\\Models\\Phone',
        'App\\Models\\Link' => 'App\\Domain\\Members\\Models\\Link',
    ];

    public function up(): void
    {
        foreach (self::IDENTITIES as $from => $to) {
            $this->replace($from, $to);
        }
    }

    public function down(): void
    {
        foreach (self::IDENTITIES as $from => $to) {
            $this->replace($to, $from);
        }
    }

    private function replace(string $from, string $to): void
    {
        DB::table('action_logs')->where('auditable_type', $from)->update(['auditable_type' => $to]);
    }
};
