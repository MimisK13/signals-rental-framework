<?php

namespace Database\Seeders;

use App\Models\Address;
use App\Models\Member;
use App\Models\Opportunity;
use App\Models\Store;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Demo opportunities for the Job Planner overlap timeline (browser / local demo only).
 *
 * NOT registered in DatabaseSeeder — run manually:
 * php artisan db:seed --class=PlannerDemoSeeder
 */
class PlannerDemoSeeder extends Seeder
{
    private const string TAG = 'planner-demo';

    public function run(): void
    {
        $this->purgePlannerDemoData();

        $store = Store::query()->where('is_default', true)->first()
            ?? Store::query()->first();

        if ($store === null) {
            if ($this->command !== null) {
                $this->command->error('No store found — seed stores first.');
            }

            return;
        }

        $venues = $this->seedVenuesWithAddresses();
        $member = Member::factory()->organisation()->create([
            'name' => 'Planner Demo Customer Ltd',
            'tag_list' => [self::TAG],
        ]);

        $rangeStart = Carbon::parse('2026-06-28 09:00:00', 'UTC');
        $rangeEnd = Carbon::parse('2026-09-28 17:00:00', 'UTC');

        $subjects = [
            'Summer Festival Main Stage',
            'Corporate AGM AV',
            'Wedding Marquee Package',
            'Arena Tour Lighting',
            'Conference Breakout AV',
            'Charity Gala Staging',
            'Product Launch Rig',
            'Outdoor Cinema Night',
            'Sports Presentation',
            'University Graduation',
            'Trade Show Booth',
            'Christmas Market Lights',
            'Food Festival PA',
            'Theatre Run Support',
            'Exhibition Build',
        ];

        $created = 0;
        $cursor = $rangeStart->copy();

        while ($cursor->lessThanOrEqualTo($rangeEnd->copy()->subDays(3))) {
            $clusterSize = fake()->numberBetween(2, 5);
            $duration = fake()->numberBetween(3, 10);

            for ($i = 0; $i < $clusterSize; $i++) {
                $start = $cursor->copy()->addDays(fake()->numberBetween(0, 2))->setTime(9, 0);
                $end = $start->copy()->addDays($duration)->setTime(17, 0);

                if ($end->greaterThan($rangeEnd)) {
                    break;
                }

                $showStart = $start->copy()->addDay()->setTime(9, 0);
                $showEnd = $end->copy()->subDay()->setTime(17, 0);
                if ($showEnd->lessThan($showStart)) {
                    $showEnd = $showStart->copy();
                }

                $isOrder = fake()->boolean(55);
                $factory = $isOrder
                    ? Opportunity::factory()->order()
                    : Opportunity::factory()->quotation();

                $factory->create([
                    'member_id' => $member->id,
                    'store_id' => $store->id,
                    'venue_id' => $venues->random()->id,
                    'subject' => fake()->randomElement($subjects).' #'.($created + 1),
                    'starts_at' => $start,
                    'ends_at' => $end,
                    'deliver_starts_at' => $start->copy(),
                    'deliver_ends_at' => $start->copy()->setTime(12, 0),
                    'show_starts_at' => $showStart,
                    'show_ends_at' => $showEnd,
                    'collect_starts_at' => $end->copy()->setTime(9, 0),
                    'collect_ends_at' => $end->copy(),
                    'customer_collecting' => fake()->boolean(25),
                    'customer_returning' => fake()->boolean(25),
                    'tag_list' => [self::TAG],
                ]);

                $created++;
            }

            $cursor->addDays(fake()->numberBetween(5, 9));
        }

        if ($this->command !== null) {
            $this->command->info(sprintf(
                'Planner demo: %d opportunities, %d venues (%s → %s).',
                $created,
                $venues->count(),
                $rangeStart->toDateString(),
                $rangeEnd->toDateString(),
            ));
        }
    }

    private function purgePlannerDemoData(): void
    {
        Opportunity::query()
            ->whereJsonContains('tag_list', self::TAG)
            ->each(fn (Opportunity $opportunity) => $opportunity->forceDelete());

        Member::query()
            ->whereJsonContains('tag_list', self::TAG)
            ->each(fn (Member $member) => $member->forceDelete());
    }

    /**
     * @return Collection<int, Member>
     */
    private function seedVenuesWithAddresses()
    {
        $blueprints = [
            ['Riverside Event Centre', '12 Thames Quay', 'London', 'SE1 2AA'],
            ['Northgate Exhibition Hall', '45 Arena Drive', 'Manchester', 'M1 4BT'],
            ['Castle Park Marquee Site', 'Castle Park Road', 'Edinburgh', 'EH1 3QR'],
            ['Harbourfront Pavilion', '3 Quay Street', 'Bristol', 'BS1 6DZ'],
            ['Lakeside Festival Grounds', 'Festival Lane', 'Birmingham', 'B1 2HP'],
            ['Grand Ballroom Hotel', '88 Park Crescent', 'Leeds', 'LS1 5AB'],
            ['Coastal Promenade Stage', 'Marine Parade', 'Brighton', 'BN1 3PL'],
            ['Cathedral Quarter Venue', '15 Chapter Street', 'York', 'YO1 7DT'],
            ['City Stadium Forecourt', 'Stadium Way', 'Sheffield', 'S2 4SU'],
            ['Botanic Gardens Lawn', 'Botanic Road', 'Cambridge', 'CB2 1JE'],
            ['Waterfront Warehouse', 'Dock Road 7', 'Liverpool', 'L1 0AN'],
            ['Highland Showground', 'Showground Lane', 'Inverness', 'IV2 5PR'],
        ];

        return collect($blueprints)->map(function (array $row): Member {
            $venue = Member::factory()->venue()->create([
                'name' => $row[0],
                'tag_list' => [self::TAG],
            ]);

            Address::factory()->primary()->create([
                'addressable_type' => Member::class,
                'addressable_id' => $venue->id,
                'street' => $row[1],
                'city' => $row[2],
                'postcode' => $row[3],
            ]);

            return $venue;
        });
    }
}
