<?php

namespace Database\Seeders\Concerns;

use App\Actions\Stores\CreateStore;
use App\Models\OrganisationTaxClass;
use App\Models\Store;
use App\Models\TaxZone;
use App\Models\User;
use Database\Seeders\CommunicationTemplateSeeder;
use Database\Seeders\DocumentTemplateSeeder;
use Illuminate\Support\Facades\Auth;

trait DemoDataSeederSupport
{
    protected const DEMO_TAG = 'demo-data';

    private function resolveDemoOwner(): ?User
    {
        $user = User::query()
            ->where('email', 'test@example.com')
            ->first()
            ?? User::query()->where('is_owner', true)->first()
            ?? User::query()->first();

        if ($user !== null && ! $user->is_owner) {
            $user->forceFill(['is_owner' => true])->save();
        }

        return $user;
    }

    private function actingAsDemoOwner(callable $callback): void
    {
        $owner = $this->resolveDemoOwner();

        if ($owner === null) {
            return;
        }

        Auth::setUser($owner);

        try {
            $callback($owner);
        } finally {
            Auth::forgetUser();
        }
    }

    /**
     * @return array{uk: TaxZone, eu: TaxZone, world: TaxZone, orgClass: OrganisationTaxClass}|null
     */
    private function demoTaxInfrastructure(): ?array
    {
        $uk = TaxZone::query()->where('slug', 'uk')->first();
        $eu = TaxZone::query()->where('slug', 'eu_member')->first();
        $world = TaxZone::query()->where('slug', 'world')->first();
        $orgClass = OrganisationTaxClass::query()->where('name', 'Standard')->first();

        if ($uk === null || $eu === null || $world === null || $orgClass === null) {
            return null;
        }

        return [
            'uk' => $uk,
            'eu' => $eu,
            'world' => $world,
            'orgClass' => $orgClass,
        ];
    }

    private function ensureDemoFinanceSettings(): void
    {
        if (settings('tax.unresolved_zone_fallback') === null || settings('tax.unresolved_zone_fallback') === '') {
            settings()->set('tax.unresolved_zone_fallback', 'seller_domestic');
        }

        if (settings('invoices.due_days') === null || settings('invoices.due_days') === '') {
            settings()->set('invoices.due_days', 30);
        }
    }

    /**
     * Mirror {@see CreateStore}: provision system document
     * templates, communication templates, and document sequences for one store.
     */
    private function seedDemoStoreDocumentAndCommunicationTemplates(Store $store): void
    {
        app(DocumentTemplateSeeder::class)->seedForStore($store);
        app(CommunicationTemplateSeeder::class)->seedForStore($store);
    }

    /**
     * @return array{starts_at: string, ends_at: string}
     */
    private function demoOpportunityWindow(int $index): array
    {
        $anchors = [
            ['weeks' => 1, 'duration_days' => 3],
            ['weeks' => -2, 'duration_days' => 5],
            ['weeks' => -6, 'duration_days' => 2],
            ['weeks' => 3, 'duration_days' => 4],
        ];

        $anchor = $anchors[$index % count($anchors)];

        $starts = now()->addWeeks($anchor['weeks'])->setTime(9, 0);
        $ends = $starts->copy()->addDays($anchor['duration_days'])->setTime(17, 0);

        return [
            'starts_at' => $starts->toIso8601String(),
            'ends_at' => $ends->toIso8601String(),
        ];
    }
}
