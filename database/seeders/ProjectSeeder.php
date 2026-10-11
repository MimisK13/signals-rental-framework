<?php

namespace Database\Seeders;

use App\Enums\MembershipType;
use App\Enums\ProjectStatus;
use App\Models\Member;
use App\Models\Project;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Seeds a small, repeatable set of realistic project planning records.
 *
 * Run explicitly with `php artisan db:seed --class=ProjectSeeder`.
 * This seeder is intentionally not part of DatabaseSeeder because Projects
 * are application data rather than required framework/catalog data.
 */
class ProjectSeeder extends Seeder
{
    public function run(): void
    {
        $members = [
            'summerCustomer' => $this->member('Northstar Live Events', MembershipType::Organisation),
            'tourCustomer' => $this->member('Helix Touring Ltd', MembershipType::Organisation),
            'corporateCustomer' => $this->member('Aegean Broadcast Services', MembershipType::Organisation),
            'summerVenue' => $this->member('Riverside Event Centre', MembershipType::Venue),
            'tourVenue' => $this->member('Northgate Arena', MembershipType::Venue),
            'corporateVenue' => $this->member('Metropolitan Conference Hall', MembershipType::Venue),
            'owner' => $this->member('Alex Morgan', MembershipType::User),
        ];

        $projects = [
            [
                'name' => 'Summer Festival 2027',
                'member_id' => $members['summerCustomer']->id,
                'venue_id' => $members['summerVenue']->id,
                'status' => ProjectStatus::Active,
                'starts_at' => Carbon::parse('2027-06-18 08:00'),
                'ends_at' => Carbon::parse('2027-06-21 23:00'),
            ],
            [
                'name' => 'European Arena Tour 2027',
                'member_id' => $members['tourCustomer']->id,
                'venue_id' => $members['tourVenue']->id,
                'status' => ProjectStatus::Draft,
                'starts_at' => Carbon::parse('2027-09-01 08:00'),
                'ends_at' => Carbon::parse('2027-11-30 23:00'),
            ],
            [
                'name' => 'Corporate AV Programme 2027',
                'member_id' => $members['corporateCustomer']->id,
                'venue_id' => $members['corporateVenue']->id,
                'status' => ProjectStatus::Completed,
                'starts_at' => Carbon::parse('2027-02-10 08:00'),
                'ends_at' => Carbon::parse('2027-02-14 23:00'),
            ],
        ];

        foreach ($projects as $attributes) {
            Project::query()->firstOrCreate(
                ['name' => $attributes['name']],
                [...$attributes, 'owned_by' => $members['owner']->id],
            );
        }
    }

    private function member(string $name, MembershipType $type): Member
    {
        return Member::query()->firstOrCreate(
            ['name' => $name, 'membership_type' => $type],
            ['is_active' => true],
        );
    }
}
