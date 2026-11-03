<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** @var array<string, string> */
    private const IDENTITIES = [
        'App\\Models\\Opportunity' => 'App\\Domain\\Projects\\Opportunities\\Models\\Opportunity',
        'App\\Models\\OpportunityItem' => 'App\\Domain\\Projects\\Opportunities\\Models\\OpportunityItem',
        'App\\Models\\OpportunityItemAsset' => 'App\\Domain\\Projects\\Opportunities\\Models\\OpportunityItemAsset',
        'App\\Models\\OpportunityCost' => 'App\\Domain\\Projects\\Opportunities\\Models\\OpportunityCost',
        'App\\Models\\OpportunityParticipant' => 'App\\Domain\\Projects\\Opportunities\\Models\\OpportunityParticipant',
        'App\\Models\\OpportunitySection' => 'App\\Domain\\Projects\\Opportunities\\Models\\OpportunitySection',
        'App\\Models\\OpportunityVersion' => 'App\\Domain\\Projects\\Opportunities\\Models\\OpportunityVersion',
        'App\\Models\\OpportunityItemSurcharge' => 'App\\Domain\\Projects\\Opportunities\\Models\\OpportunityItemSurcharge',
        'App\\Models\\OpportunitySurchargeGroup' => 'App\\Domain\\Projects\\Opportunities\\Models\\OpportunitySurchargeGroup',
    ];

    public function up(): void
    {
        $this->rewrite(self::IDENTITIES);
    }

    public function down(): void
    {
        $this->rewrite(array_flip(self::IDENTITIES));
    }

    /** @param array<string, string> $identities */
    private function rewrite(array $identities): void
    {
        foreach ($identities as $from => $to) {
            DB::table('action_logs')->where('auditable_type', $from)->update(['auditable_type' => $to]);
        }
    }
};
