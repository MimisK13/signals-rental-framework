<?php

namespace Database\Seeders;

use App\Actions\Opportunities\AddOpportunityGroup;
use App\Actions\Opportunities\AddOpportunityItem;
use App\Actions\Opportunities\ConvertToOrder;
use App\Actions\Opportunities\ConvertToQuotation;
use App\Actions\Opportunities\CreateOpportunity;
use App\Data\Opportunities\AddOpportunityGroupData;
use App\Data\Opportunities\AddOpportunityItemData;
use App\Data\Opportunities\CreateOpportunityData;
use App\Data\Opportunities\OpportunityData;
use App\Enums\ContainerAvailabilityMode;
use App\Enums\LineItemTransactionType;
use App\Enums\MembershipType;
use App\Enums\OpportunityItemType;
use App\Enums\ProductType;
use App\Enums\RateTransactionType;
use App\Enums\StockMethod;
use App\Models\Accessory;
use App\Models\Activity;
use App\Models\Container;
use App\Models\Email;
use App\Models\Invoice;
use App\Models\Member;
use App\Models\MemberRelationship;
use App\Models\Opportunity;
use App\Models\OpportunityItem;
use App\Models\Phone;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\ProductRate;
use App\Models\ProductTaxClass;
use App\Models\PurchaseOrder;
use App\Models\RateDefinition;
use App\Models\StockLevel;
use App\Models\Store;
use App\Models\SupplierBill;
use App\Models\User;
use App\Services\CurrencyService;
use Database\Seeders\Concerns\DemoDataSeederSupport;
use Database\Seeders\Concerns\SeedsDemoPhase4;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

class DemoDataSeeder extends Seeder
{
    use DemoDataSeederSupport;
    use SeedsDemoPhase4;

    public function run(): void
    {
        $this->renameLegacySeededData();
        $this->createDemoStores();
        $this->createDemoMembers();
        $this->enrichDemoMembers();
        $this->createDemoProducts();
        $this->enrichDemoProducts();
        $this->seedDemoStockLevels();
        $this->createDemoOpportunities();
        $this->createDemoInvoices();
        $this->applyDeferredDemoProductTaxCategories();
        $this->createDemoPurchaseOrders();
        $this->createDemoSupplierBills();
        $this->createDemoContainers();
        $this->createDemoCustomFields();
        $this->createDemoActivities();
    }

    private function renameLegacySeededData(): void
    {
        $productNames = [
            'Demo - Sennheiser EW100 G4 Radio Mic' => 'Sennheiser EW100 G4 Radio Mic',
            'Demo - XLR Cable 5m' => 'XLR Cable 5m',
            'Demo - LED PAR Can RGBW' => 'LED PAR Can RGBW',
            'Demo - Moving Head Beam 230W' => 'Moving Head Beam 230W',
            'Demo - 55" LED Display Screen' => '55" LED Display Screen',
            'Demo - Stage Deck 2m x 1m' => 'Stage Deck 2m x 1m',
            'Demo - 16A Power Distro' => '16A Power Distro',
            'Demo - Gaffer Tape 50mm Black' => 'Gaffer Tape 50mm Black',
            'Demo - Folding Banquet Chair' => 'Folding Banquet Chair',
            'Demo - On-Site Technician Day Rate' => 'On-Site Technician Day Rate',
        ];

        $productSkus = [
            'DEMO-AUD-EW100' => 'AUD-EW100',
            'DEMO-AUD-SR2050' => 'AUD-SR2050',
            'DEMO-CAS-SR2050' => 'CAS-SR2050',
            'DEMO-AUD-XLR5' => 'AUD-XLR5',
            'DEMO-LGN-PARRGBW' => 'LGN-PARRGBW',
            'DEMO-LMH-BEAM230' => 'LMH-BEAM230',
            'DEMO-VID-LED55' => 'VID-LED55',
            'DEMO-STG-DECK2X1' => 'STG-DECK2X1',
            'DEMO-PWR-DISTRO16' => 'PWR-DISTRO16',
            'DEMO-CON-GAF50' => 'CON-GAF50',
            'DEMO-FUR-CHAIR' => 'FUR-CHAIR',
            'DEMO-SVC-TECH' => 'SVC-TECH',
        ];

        foreach ($productNames as $oldName => $newName) {
            Product::query()
                ->where('name', $oldName)
                ->whereJsonContains('tag_list', self::DEMO_TAG)
                ->update(['name' => $newName]);
        }

        foreach ($productSkus as $oldSku => $newSku) {
            Product::query()
                ->where('sku', $oldSku)
                ->whereJsonContains('tag_list', self::DEMO_TAG)
                ->update(['sku' => $newSku]);
        }

        $stockLevelPrefixes = [
            'DEMO-SR2050-CASE-' => 'SR2050-CASE-',
            'DEMO-SR2050-' => 'SR2050-',
            'DEMO-MH-' => 'MH-',
            'DEMO-PAR-' => 'PAR-',
        ];

        foreach ($stockLevelPrefixes as $oldPrefix => $newPrefix) {
            StockLevel::query()
                ->where('asset_number', 'like', $oldPrefix.'%')
                ->chunkById(100, function ($stockLevels) use ($oldPrefix, $newPrefix): void {
                    foreach ($stockLevels as $stockLevel) {
                        $stockLevel->forceFill([
                            'asset_number' => str_replace($oldPrefix, $newPrefix, (string) $stockLevel->asset_number),
                            'serial_number' => $stockLevel->serial_number !== null
                                ? str_replace($oldPrefix, $newPrefix, (string) $stockLevel->serial_number)
                                : null,
                            'barcode' => $stockLevel->barcode !== null
                                ? str_replace($oldPrefix, $newPrefix, (string) $stockLevel->barcode)
                                : null,
                        ])->save();
                    }
                });
        }

        $referenceMaps = [
            [Opportunity::query(), 'reference', 'DEMO-OPP-', 'OPP-'],
            [Invoice::query(), 'reference', 'DEMO-INV-', 'INV-'],
            [PurchaseOrder::query(), 'reference', 'DEMO-PO-', 'PO-'],
            [SupplierBill::query(), 'document_number', 'DEMO-SB-', 'SB-'],
        ];

        foreach ($referenceMaps as [$query, $column, $oldPrefix, $newPrefix]) {
            $query->where($column, 'like', $oldPrefix.'%')
                ->get()
                ->each(function ($record) use ($column, $oldPrefix, $newPrefix): void {
                    $record->forceFill([
                        $column => str_replace($oldPrefix, $newPrefix, (string) $record->{$column}),
                    ])->save();
                });
        }

        SupplierBill::query()
            ->where('reference', 'like', 'DEMO-PO-%')
            ->get()
            ->each(function (SupplierBill $bill): void {
                $bill->forceFill([
                    'reference' => str_replace('DEMO-PO-', 'PO-', (string) $bill->reference),
                ])->save();
            });

        foreach ([PurchaseOrder::query(), SupplierBill::query()] as $query) {
            $query->where('notes', 'like', 'Demo %')
                ->get()
                ->each(function ($record): void {
                    $record->forceFill([
                        'notes' => preg_replace('/^Demo /', '', (string) $record->notes),
                    ])->save();
                });
        }

        foreach ([
            'Demo Open Flight Case' => 'Open Flight Case',
            'Demo Sealed Flight Case' => 'Sealed Flight Case',
        ] as $oldName => $newName) {
            Container::query()->where('name', $oldName)->update(['name' => $newName]);
        }

        Member::query()
            ->whereJsonContains('tag_list', self::DEMO_TAG)
            ->where('name', 'like', 'Demo - %')
            ->get()
            ->each(function (Member $member): void {
                $member->forceFill([
                    'name' => preg_replace('/^Demo - /', '', (string) $member->name),
                ])->save();
            });
    }

    private function createDemoStores(): void
    {
        $demoStores = [
            [
                'name' => 'London Warehouse',
                'street' => '123 Industrial Way',
                'city' => 'London',
                'county' => 'Greater London',
                'postcode' => 'E1 6AN',
                'country_code' => settings('company.country_code') ?: 'GB',
                'is_default' => false,
                'tag_list' => ['demo-data'],
            ],
            [
                'name' => 'Manchester Depot',
                'street' => '45 Trade Park',
                'city' => 'Manchester',
                'county' => 'Greater Manchester',
                'postcode' => 'M1 1AA',
                'country_code' => settings('company.country_code') ?: 'GB',
                'is_default' => false,
                'tag_list' => ['demo-data'],
            ],
            [
                'name' => 'Edinburgh Warehouse',
                'street' => '78 Festival Industrial Park',
                'city' => 'Edinburgh',
                'county' => 'Midlothian',
                'postcode' => 'EH1 1BB',
                'country_code' => settings('company.country_code') ?: 'GB',
                'is_default' => false,
                'tag_list' => ['demo-data'],
            ],
        ];

        foreach ($demoStores as $storeData) {
            $store = Store::query()
                ->where('name', $storeData['name'])
                ->whereJsonContains('tag_list', self::DEMO_TAG)
                ->first();

            if ($store === null) {
                $store = Store::create($storeData);
            }

            $this->seedDemoStoreDocumentAndCommunicationTemplates($store);
        }
    }

    private function createDemoMembers(): void
    {
        if (Member::query()->whereJsonContains('tag_list', self::DEMO_TAG)->exists()) {
            $this->command->info('Demo members already exist; skipping bulk demo member creation.');

            return;
        }

        $this->command->info('Seeding 2,000 organisations...');
        $organisations = Member::factory()
            ->organisation()
            ->count(2000)
            ->create(['tag_list' => ['demo-data']]);

        $this->createContactDetailsForMembers($organisations);

        $this->command->info('Seeding 500 venues...');
        $venues = Member::factory()
            ->venue()
            ->count(500)
            ->create(['tag_list' => ['demo-data']]);

        $this->createContactDetailsForMembers($venues);

        $this->command->info('Seeding 3,000 contacts...');
        $contacts = Member::factory()
            ->contact()
            ->count(3000)
            ->create(['tag_list' => ['demo-data']]);

        $this->createContactDetailsForMembers($contacts);

        $this->command->info('Creating relationships between contacts and organisations/venues...');
        $this->createRelationships($contacts, $organisations, $venues);
    }

    /**
     * @param  Collection<int, Member>  $members
     */
    private function createContactDetailsForMembers($members): void
    {
        $emailInserts = [];
        $phoneInserts = [];
        $now = now();

        foreach ($members as $member) {
            $emailInserts[] = [
                'emailable_type' => Member::class,
                'emailable_id' => $member->id,
                'address' => fake()->unique()->safeEmail(),
                'is_primary' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            $phoneInserts[] = [
                'phoneable_type' => Member::class,
                'phoneable_id' => $member->id,
                'number' => fake()->phoneNumber(),
                'is_primary' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($emailInserts, 500) as $chunk) {
            Email::insert($chunk);
        }

        foreach (array_chunk($phoneInserts, 500) as $chunk) {
            Phone::insert($chunk);
        }
    }

    /**
     * @param  Collection<int, Member>  $contacts
     * @param  Collection<int, Member>  $organisations
     * @param  Collection<int, Member>  $venues
     */
    private function createRelationships($contacts, $organisations, $venues): void
    {
        $relationshipTypes = ['Employee', 'Director', 'Contractor', 'Consultant', 'Manager'];
        $venueRelationshipTypes = ['Event Manager', 'Site Contact', 'Venue Manager', 'Technical Contact'];
        $inserts = [];
        $now = now();

        foreach ($contacts as $index => $contact) {
            // Every contact belongs to an organisation
            $org = $organisations[$index % $organisations->count()];
            $inserts[] = [
                'member_id' => $contact->id,
                'related_member_id' => $org->id,
                'relationship_type' => $relationshipTypes[array_rand($relationshipTypes)],
                'is_primary' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            // ~30% of contacts also linked to a venue
            if ($index % 3 === 0) {
                $venue = $venues[$index % $venues->count()];
                $inserts[] = [
                    'member_id' => $contact->id,
                    'related_member_id' => $venue->id,
                    'relationship_type' => $venueRelationshipTypes[array_rand($venueRelationshipTypes)],
                    'is_primary' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        foreach (array_chunk($inserts, 500) as $chunk) {
            MemberRelationship::insert($chunk);
        }
    }

    /**
     * Seed a realistic spread of demo products across the seeded catalogue
     * groups, mixing rental/sale/service types and serialised/bulk stock
     * methods. Each record is tagged 'demo-data' so signals:clear-demo can
     * remove them. Keyed on name via firstOrCreate so re-runs are idempotent.
     */
    private function createDemoProducts(): void
    {
        $this->command->info('Seeding demo products...');

        $groupIds = ProductGroup::query()->pluck('id', 'name');
        $standardProductTaxClassId = ProductTaxClass::query()->where('is_default', true)->value('id');

        $products = [
            [
                'name' => 'Sennheiser EW100 G4 Radio Mic',
                'description' => 'Wireless handheld radio microphone system.',
                'group' => 'Audio / Wireless',
                'product_type' => ProductType::Rental,
                'stock_method' => StockMethod::Serialised,
                'replacement_charge' => 49900,
                'sku' => 'AUD-EW100',
            ],
            [
                'name' => 'Sennheiser SR 2050 IEM Transmitter',
                'description' => 'Professional dual-channel in-ear monitoring transmitter.',
                'group' => 'Audio / Wireless',
                'product_type' => ProductType::Rental,
                'stock_method' => StockMethod::Serialised,
                'replacement_charge' => 249900,
                'sku' => 'AUD-SR2050',
            ],
            [
                'name' => 'Sennheiser SR 2050 Flightcase',
                'description' => 'Protective touring flightcase for the Sennheiser SR 2050 IEM system.',
                'group' => 'Audio / Cases',
                'product_type' => ProductType::Rental,
                'stock_method' => StockMethod::Serialised,
                'replacement_charge' => 69900,
                'sku' => 'CAS-SR2050',
                'is_containerable' => true,
                'container_availability_mode' => ContainerAvailabilityMode::Kit->value,
                'container_max_nesting_depth' => 1,
            ],
            [
                'name' => 'Allen & Heath dLive C3500 Mixing Console',
                'description' => '96kHz digital mixing surface for touring and installed production.',
                'group' => 'Audio / Mixing Desks',
                'product_type' => ProductType::Rental,
                'stock_method' => StockMethod::Serialised,
                'replacement_charge' => 1249900,
                'sku' => 'AUD-DLIVE-C3500',
            ],
            [
                'name' => 'Allen & Heath SQ-5 Mixing Console',
                'description' => 'Compact digital mixing console for live sound and corporate events.',
                'group' => 'Audio / Mixing Desks',
                'product_type' => ProductType::Rental,
                'stock_method' => StockMethod::Serialised,
                'replacement_charge' => 299900,
                'sku' => 'AUD-SQ5',
            ],
            [
                'name' => 'Yamaha CL5 Digital Mixing Console',
                'description' => 'Professional digital mixing console with Dante integration.',
                'group' => 'Audio / Mixing Desks',
                'product_type' => ProductType::Rental,
                'stock_method' => StockMethod::Serialised,
                'replacement_charge' => 1099900,
                'sku' => 'AUD-YAMAHA-CL5',
            ],
            [
                'name' => 'Yamaha QL1 Digital Mixing Console',
                'description' => 'Compact Yamaha digital mixing console for touring systems.',
                'group' => 'Audio / Mixing Desks',
                'product_type' => ProductType::Rental,
                'stock_method' => StockMethod::Serialised,
                'replacement_charge' => 699900,
                'sku' => 'AUD-YAMAHA-QL1',
            ],
            [
                'name' => 'Midas M32 Live Mixing Console',
                'description' => '40-input digital mixing console for live production.',
                'group' => 'Audio / Mixing Desks',
                'product_type' => ProductType::Rental,
                'stock_method' => StockMethod::Serialised,
                'replacement_charge' => 399900,
                'sku' => 'AUD-MIDAS-M32',
            ],
            [
                'name' => 'Behringer X32 Digital Mixing Console',
                'description' => 'Digital mixing console for events, rehearsal rooms and touring.',
                'group' => 'Audio / Mixing Desks',
                'product_type' => ProductType::Rental,
                'stock_method' => StockMethod::Serialised,
                'replacement_charge' => 299900,
                'sku' => 'AUD-BEHRINGER-X32',
            ],
            [
                'name' => 'DiGiCo SD11 Digital Mixing Console',
                'description' => 'Compact professional digital console for touring productions.',
                'group' => 'Audio / Mixing Desks',
                'product_type' => ProductType::Rental,
                'stock_method' => StockMethod::Serialised,
                'replacement_charge' => 1199900,
                'sku' => 'AUD-DIGICO-SD11',
            ],
            [
                'name' => 'Soundcraft Vi2000 Digital Mixing Console',
                'description' => 'Large-format digital console for live sound and broadcast work.',
                'group' => 'Audio / Mixing Desks',
                'product_type' => ProductType::Rental,
                'stock_method' => StockMethod::Serialised,
                'replacement_charge' => 899900,
                'sku' => 'AUD-SOUNDCRAFT-VI2000',
            ],
            [
                'name' => 'Sennheiser EW-D EM Wireless Receiver',
                'description' => 'Digital wireless microphone receiver for event and touring systems.',
                'group' => 'Audio / Wireless',
                'product_type' => ProductType::Rental,
                'stock_method' => StockMethod::Serialised,
                'replacement_charge' => 59900,
                'sku' => 'AUD-SENN-EWD-EM',
            ],
            [
                'name' => 'Shure ULXD4D Dual Wireless Receiver',
                'description' => 'Dual-channel digital wireless receiver for professional RF systems.',
                'group' => 'Audio / Wireless',
                'product_type' => ProductType::Rental,
                'stock_method' => StockMethod::Serialised,
                'replacement_charge' => 329900,
                'sku' => 'AUD-SHURE-ULXD4D',
            ],
            [
                'name' => 'Shure SM58 Vocal Microphone',
                'description' => 'Industry-standard dynamic vocal microphone.',
                'group' => 'Audio / Microphones',
                'product_type' => ProductType::Rental,
                'stock_method' => StockMethod::Serialised,
                'replacement_charge' => 11900,
                'sku' => 'AUD-SHURE-SM58',
            ],
            [
                'name' => 'Shure SM57 Instrument Microphone',
                'description' => 'Dynamic instrument microphone for drums, guitar and stage use.',
                'group' => 'Audio / Microphones',
                'product_type' => ProductType::Rental,
                'stock_method' => StockMethod::Serialised,
                'replacement_charge' => 10900,
                'sku' => 'AUD-SHURE-SM57',
            ],
            [
                'name' => 'Neumann KM184 Condenser Microphone',
                'description' => 'Small-diaphragm condenser microphone for detailed capture.',
                'group' => 'Audio / Microphones',
                'product_type' => ProductType::Rental,
                'stock_method' => StockMethod::Serialised,
                'replacement_charge' => 79900,
                'sku' => 'AUD-NEUMANN-KM184',
            ],
            [
                'name' => 'DPA 4060 Lavalier Microphone',
                'description' => 'Omnidirectional lavalier microphone for broadcast and theatre.',
                'group' => 'Audio / Microphones',
                'product_type' => ProductType::Rental,
                'stock_method' => StockMethod::Serialised,
                'replacement_charge' => 49900,
                'sku' => 'AUD-DPA-4060',
            ],
            [
                'name' => 'L-Acoustics LA4X Amplified Controller',
                'description' => 'Four-channel amplified controller for professional loudspeaker systems.',
                'group' => 'Audio / Amplifiers',
                'product_type' => ProductType::Rental,
                'stock_method' => StockMethod::Serialised,
                'replacement_charge' => 699900,
                'sku' => 'AUD-LA-ACOUSTICS-LA4X',
            ],
            [
                'name' => 'd&b audiotechnik D20 Amplifier',
                'description' => 'Four-channel installation and touring amplifier.',
                'group' => 'Audio / Amplifiers',
                'product_type' => ProductType::Rental,
                'stock_method' => StockMethod::Serialised,
                'replacement_charge' => 599900,
                'sku' => 'AUD-DB-D20',
            ],
            [
                'name' => 'Radial JDI Passive Direct Box',
                'description' => 'Passive direct box for instruments and line-level sources.',
                'group' => 'Audio / DI & Signal',
                'product_type' => ProductType::Rental,
                'stock_method' => StockMethod::Serialised,
                'replacement_charge' => 24900,
                'sku' => 'AUD-RADIAL-JDI',
            ],
            [
                'name' => 'Robe LEDBeam 150 Moving Light',
                'description' => 'Compact LED moving beam fixture.',
                'group' => 'Lighting - Moving Heads',
                'product_type' => ProductType::Rental,
                'stock_method' => StockMethod::Serialised,
                'replacement_charge' => 89900,
                'sku' => 'LGN-ROBE-LEDBEAM150',
            ],
            [
                'name' => 'Astera AX5 TriplePAR',
                'description' => 'Battery-powered wireless LED uplighter.',
                'group' => 'Lighting - Generic',
                'product_type' => ProductType::Rental,
                'stock_method' => StockMethod::Serialised,
                'replacement_charge' => 64900,
                'sku' => 'LGN-ASTERA-AX5',
            ],
            [
                'name' => 'Panasonic PT-RZ120 Laser Projector',
                'description' => '12,000-lumen professional laser projector.',
                'group' => 'Video',
                'product_type' => ProductType::Rental,
                'stock_method' => StockMethod::Serialised,
                'replacement_charge' => 899900,
                'sku' => 'VID-PANASONIC-RZ120',
            ],
            [
                'name' => 'Blackmagic ATEM 4 M/E Production Studio 4K',
                'description' => '4K live production switcher for multi-camera events.',
                'group' => 'Video',
                'product_type' => ProductType::Rental,
                'stock_method' => StockMethod::Serialised,
                'replacement_charge' => 399900,
                'sku' => 'VID-BMD-ATEM4ME',
            ],
            [
                'name' => 'XLR Cable 5m',
                'description' => 'Balanced microphone cable, bulk stock.',
                'group' => 'Audio / Cables',
                'product_type' => ProductType::Rental,
                'stock_method' => StockMethod::Bulk,
                'replacement_charge' => 1200,
                'sku' => 'AUD-XLR5',
            ],
            [
                'name' => 'LED PAR Can RGBW',
                'description' => 'Compact LED uplighter, RGBW colour mixing.',
                'group' => 'Lighting - Generic',
                'product_type' => ProductType::Rental,
                'stock_method' => StockMethod::Serialised,
                'replacement_charge' => 19900,
                'sku' => 'LGN-PARRGBW',
            ],
            [
                'name' => 'Moving Head Beam 230W',
                'description' => 'Sharp-beam moving head fixture.',
                'group' => 'Lighting - Moving Heads',
                'product_type' => ProductType::Rental,
                'stock_method' => StockMethod::Serialised,
                'replacement_charge' => 129900,
                'weight' => 16.5000,
                'sku' => 'LMH-BEAM230',
            ],
            [
                'name' => '55" LED Display Screen',
                'description' => 'Full HD display panel with floor stand.',
                'group' => 'Video',
                'product_type' => ProductType::Rental,
                'stock_method' => StockMethod::Serialised,
                'replacement_charge' => 89900,
                'sku' => 'VID-LED55',
            ],
            [
                'name' => 'Stage Deck 2m x 1m',
                'description' => 'Modular staging deck, anti-slip surface.',
                'group' => 'Staging',
                'product_type' => ProductType::Rental,
                'stock_method' => StockMethod::Bulk,
                'replacement_charge' => 24900,
                'weight' => 22.0000,
                'sku' => 'STG-DECK2X1',
            ],
            [
                'name' => '16A Power Distro',
                'description' => '16A power distribution unit with RCD protection.',
                'group' => 'Power',
                'product_type' => ProductType::Rental,
                'stock_method' => StockMethod::Serialised,
                'replacement_charge' => 34900,
                'sku' => 'PWR-DISTRO16',
            ],
            [
                'name' => 'Gaffer Tape 50mm Black',
                'description' => 'Matt black cloth tape, consumable sale item.',
                'group' => 'Consumables',
                'product_type' => ProductType::Sale,
                'stock_method' => StockMethod::Bulk,
                'replacement_charge' => 900,
                'purchase_price' => 650,
                'sku' => 'CON-GAF50',
            ],
            [
                'name' => 'Folding Banquet Chair',
                'description' => 'Stackable padded event chair.',
                'group' => 'Furniture',
                'product_type' => ProductType::Rental,
                'stock_method' => StockMethod::Bulk,
                'replacement_charge' => 4900,
                'sku' => 'FUR-CHAIR',
            ],
            [
                'name' => 'On-Site Technician Day Rate',
                'description' => 'Crew labour service line.',
                'group' => null,
                'product_type' => ProductType::Service,
                'stock_method' => StockMethod::Bulk,
                'sku' => 'SVC-TECH',
            ],
        ];

        foreach ($products as $data) {
            $group = $data['group'];
            unset($data['group']);

            Product::query()->firstOrCreate(
                ['name' => $data['name']],
                array_merge($data, [
                    'product_group_id' => $group !== null ? $groupIds->get($group) : null,
                    'is_active' => true,
                    'tax_class_id' => $standardProductTaxClassId,
                    'tag_list' => ['demo-data'],
                ]),
            );
        }

        $this->seedDemoProductRates();
        $this->seedDemoProductAccessories();
    }

    /**
     * Attach a non-zero {@see ProductRate} to each demo catalogue product so the
     * rate engine prices demo lines above £0 (without rates the resolver returns
     * null and every demo line falls back to a £0.00 ad-hoc price, blocking
     * pricing demos). Rental + service lines use the "Daily Rate" preset; sale
     * lines use the duration-independent "Fixed Rate" preset. Prices are minor
     * units (pence) and the rate is store-agnostic (store_id null) at the default
     * priority, matching the all-stores resolution path.
     */
    private function seedDemoProductRates(): void
    {
        $dailyRate = RateDefinition::query()->where('preset_slug', 'daily-rate')->first();
        $fixedRate = RateDefinition::query()->where('preset_slug', 'fixed-rate')->first();

        if ($dailyRate === null || $fixedRate === null) {
            $this->command->warn('Rate definition presets not found — run RateDefinitionPresetSeeder first; demo products will price at £0.');

            return;
        }

        // Per-SKU price in minor units: a daily hire rate for rental/service lines,
        // a flat sale price for sale lines.
        $rates = [
            'AUD-EW100' => 4500,
            'AUD-SR2050' => 8500,
            'CAS-SR2050' => 1800,
            'AUD-DLIVE-C3500' => 27500,
            'AUD-SQ5' => 9500,
            'AUD-YAMAHA-CL5' => 25000,
            'AUD-YAMAHA-QL1' => 18500,
            'AUD-MIDAS-M32' => 12000,
            'AUD-BEHRINGER-X32' => 9500,
            'AUD-DIGICO-SD11' => 29500,
            'AUD-SOUNDCRAFT-VI2000' => 22500,
            'AUD-SENN-EWD-EM' => 3200,
            'AUD-SHURE-ULXD4D' => 7500,
            'AUD-SHURE-SM58' => 900,
            'AUD-SHURE-SM57' => 900,
            'AUD-NEUMANN-KM184' => 2800,
            'AUD-DPA-4060' => 2200,
            'AUD-LA-ACOUSTICS-LA4X' => 12500,
            'AUD-DB-D20' => 11000,
            'AUD-RADIAL-JDI' => 1400,
            'AUD-XLR5' => 250,
            'LGN-PARRGBW' => 1500,
            'LMH-BEAM230' => 6500,
            'LGN-ROBE-LEDBEAM150' => 4200,
            'LGN-ASTERA-AX5' => 3500,
            'VID-LED55' => 7500,
            'VID-PANASONIC-RZ120' => 18500,
            'VID-BMD-ATEM4ME' => 8500,
            'STG-DECK2X1' => 1200,
            'PWR-DISTRO16' => 3500,
            'CON-GAF50' => 900,
            'FUR-CHAIR' => 350,
            'SVC-TECH' => 35000,
        ];

        foreach ($rates as $sku => $price) {
            $product = Product::query()->where('sku', $sku)->first();

            if ($product === null) {
                continue;
            }

            $transactionType = match ($product->product_type) {
                ProductType::Sale => RateTransactionType::Sale,
                ProductType::Service => RateTransactionType::Service,
                default => RateTransactionType::Rental,
            };

            $rateDefinition = $product->product_type === ProductType::Sale ? $fixedRate : $dailyRate;

            ProductRate::query()->updateOrCreate(
                [
                    'product_id' => $product->id,
                    'store_id' => null,
                    'transaction_type' => $transactionType,
                ],
                [
                    'rate_definition_id' => $rateDefinition->id,
                    'price' => $price,
                    'currency' => 'GBP',
                    'priority' => 0,
                ],
            );
        }
    }

    /**
     * Link included catalogue accessories so product adds can auto-materialise
     * nested accessory rows in the unified line-item tree.
     */
    private function seedDemoProductAccessories(): void
    {
        $mic = Product::query()->where('name', 'Sennheiser EW100 G4 Radio Mic')->first();
        $cable = Product::query()->where('name', 'XLR Cable 5m')->first();
        $movingHead = Product::query()->where('name', 'Moving Head Beam 230W')->first();
        $distro = Product::query()->where('name', '16A Power Distro')->first();

        if ($mic !== null && $cable !== null) {
            Accessory::query()->firstOrCreate(
                [
                    'product_id' => $mic->id,
                    'accessory_product_id' => $cable->id,
                ],
                [
                    'quantity' => '1',
                    'included' => true,
                    'sort_order' => 1,
                ],
            );
        }

        if ($movingHead !== null && $distro !== null) {
            Accessory::query()->firstOrCreate(
                [
                    'product_id' => $movingHead->id,
                    'accessory_product_id' => $distro->id,
                ],
                [
                    'quantity' => '1',
                    'included' => true,
                    'sort_order' => 1,
                ],
            );
        }
    }

    /**
     * Seed a realistic spread of demo opportunities across the lifecycle: a few
     * drafts, quotations and confirmed orders, each with manually-priced line
     * items drawn from the demo catalogue.
     *
     * Opportunities are event-sourced (Verbs): every record is created through
     * the REAL action path (CreateOpportunity → AddOpportunityGroup/Item →
     * ConvertToQuotation → ConvertToOrder) so the opportunities projection,
     * demands and availability snapshots all stay consistent — never a raw
     * Opportunity::create(). The 'demo-data' tag is written onto the projection
     * afterwards (it is metadata, not lifecycle state, so it is not an event
     * field) so signals:clear-demo can identify and remove these rows.
     *
     * Re-runs are idempotent: each opportunity is keyed on a unique demo
     * `reference` and skipped when a 'demo-data'-tagged row with that reference
     * already exists, so re-seeding never duplicates or accumulates.
     */
    private function createDemoOpportunities(): void
    {
        $this->command->info('Seeding demo opportunities...');

        $owner = $this->resolveDemoOwner();
        $store = Store::query()->where('is_default', true)->first()
            ?? Store::query()->first();

        $organisations = Member::query()
            ->whereJsonContains('tag_list', self::DEMO_TAG)
            ->where('membership_type', MembershipType::Organisation)
            ->limit(20)
            ->get();

        $products = Product::query()
            ->whereJsonContains('tag_list', self::DEMO_TAG)
            ->whereIn('product_type', [ProductType::Rental, ProductType::Sale])
            ->where('sku', '!=', 'CON-GAF50')
            ->orderBy('id')
            ->get();

        if ($owner === null || $store === null || $organisations->isEmpty() || $products->isEmpty()) {
            return;
        }

        Auth::setUser($owner);

        try {
            foreach ($this->seedExtendedDemoOpportunityBlueprints() as $index => [$reference, $subject, $stage, $special]) {
                $exists = Opportunity::query()
                    ->whereJsonContains('tag_list', self::DEMO_TAG)
                    ->where('reference', $reference)
                    ->exists();

                if ($exists) {
                    continue;
                }

                $window = $this->demoOpportunityWindow($index);
                $member = $organisations[$index % $organisations->count()];

                $data = OpportunityData::from($this->buildDemoOpportunity(
                    $subject,
                    $reference,
                    $member->id,
                    $store->id,
                    $owner->member_id,
                    $window,
                ));

                $opportunity = Opportunity::query()->whereKey($data->id)->firstOrFail();

                if (in_array($reference, ['OPP-0001', 'OPP-0013'], true)) {
                    $this->seedRichDemoLineTree($opportunity, $window);
                } else {
                    $this->seedStandardDemoLines($opportunity, $window, $index, $products);
                }

                $opportunity->refresh();

                if ($special !== null && $this->isDemoPricingSpecial($special)) {
                    $this->applyDemoOpportunitySpecial($opportunity->fresh(), $special, $owner);
                    $opportunity->refresh();
                }

                $this->finalizeDemoOpportunityTotalsBeforeLifecycleConversion($opportunity->fresh());

                if ($stage === 'quotation' || $stage === 'order') {
                    (new ConvertToQuotation)($opportunity);
                    $opportunity->refresh();
                }

                if ($stage === 'order') {
                    (new ConvertToOrder)($opportunity);
                    $opportunity->refresh();
                }

                if ($special !== null && $this->isDemoStatusSpecial($special)) {
                    $this->applyDemoOpportunitySpecial($opportunity->fresh(), $special, $owner);
                }

                $opportunity->refresh()->forceFill(['tag_list' => [self::DEMO_TAG]])->save();
            }
        } finally {
            Auth::forgetUser();
        }
    }

    /**
     * Headline demo opportunities: group → nested products → auto-materialised
     * included accessories, plus a top-level crew service line.
     *
     * @param  array{starts_at: string, ends_at: string}  $window
     */
    private function seedRichDemoLineTree(Opportunity $opportunity, array $window): void
    {
        $audioGroup = $this->addDemoGroup($opportunity, 'Main Stage Audio');
        $this->addDemoProductLine(
            $opportunity,
            $this->demoProduct('Sennheiser EW100 G4 Radio Mic'),
            $window,
            parentPath: $audioGroup->path,
            quantity: '2',
            unitPrice: 3500,
        );
        $this->addDemoProductLine(
            $opportunity,
            $this->demoProduct('Moving Head Beam 230W'),
            $window,
            parentPath: $audioGroup->path,
            quantity: '4',
            unitPrice: 8500,
        );

        $lightingGroup = $this->addDemoGroup($opportunity, 'Lighting Package');
        $this->addDemoProductLine(
            $opportunity,
            $this->demoProduct('LED PAR Can RGBW'),
            $window,
            parentPath: $lightingGroup->path,
            quantity: '8',
            unitPrice: 1800,
        );
        $this->addDemoProductLine(
            $opportunity,
            $this->demoProduct('55" LED Display Screen'),
            $window,
            parentPath: $lightingGroup->path,
            quantity: '2',
            unitPrice: 12000,
        );

        $stagingGroup = $this->addDemoGroup($opportunity, 'Staging & Decks');
        $this->addDemoProductLine(
            $opportunity,
            $this->demoProduct('Stage Deck 2m x 1m'),
            $window,
            parentPath: $stagingGroup->path,
            quantity: '12',
            unitPrice: 4500,
        );

        $this->addDemoServiceLine(
            $opportunity,
            $this->demoProduct('On-Site Technician Day Rate'),
            $window,
            quantity: '3',
            unitPrice: 45000,
        );
    }

    /**
     * Standard demo opportunities: a mix of grouped, flat, and service lines.
     *
     * @param  array{starts_at: string, ends_at: string}  $window
     * @param  Collection<int, Product>  $products
     */
    private function seedStandardDemoLines(
        Opportunity $opportunity,
        array $window,
        int $index,
        Collection $products,
    ): void {
        $lineProducts = $products->values();

        if ($index % 3 === 0) {
            $group = $this->addDemoGroup($opportunity, 'Equipment Package');
            $first = $lineProducts[$index % $lineProducts->count()];
            $second = $lineProducts[($index + 1) % $lineProducts->count()];

            $this->addDemoProductLine($opportunity, $first, $window, parentPath: $group->path, quantity: '2', unitPrice: 2500);
            $this->addDemoProductLine($opportunity, $second, $window, parentPath: $group->path, quantity: '1', unitPrice: 5000);

            return;
        }

        if ($index % 3 === 1) {
            $count = max(1, $lineProducts->count());
            $offset = ($index * 2) % $count;

            foreach ([0, 1] as $position) {
                $product = $lineProducts[($offset + $position) % $count];
                $this->addDemoProductLine(
                    $opportunity,
                    $product,
                    $window,
                    quantity: (string) (($position % 3) + 1),
                    unitPrice: (($position + 1) * 2500),
                );
            }

            return;
        }

        $product = $lineProducts[$index % $lineProducts->count()];
        $this->addDemoProductLine($opportunity, $product, $window, quantity: '1', unitPrice: 3000);
        $this->addDemoServiceLine(
            $opportunity,
            $this->demoProduct('On-Site Technician Day Rate'),
            $window,
            quantity: '1',
            unitPrice: 45000,
        );
    }

    private function demoProduct(string $name): Product
    {
        return Product::query()->where('name', $name)->firstOrFail();
    }

    private function addDemoGroup(Opportunity $opportunity, string $name, ?string $parentPath = null): OpportunityItem
    {
        (new AddOpportunityGroup)($opportunity, AddOpportunityGroupData::from([
            'name' => $name,
            'parent_path' => $parentPath,
        ]));

        $opportunity->refresh();

        return $opportunity->items()
            ->where('name', $name)
            ->where('item_type', OpportunityItemType::Group)
            ->orderByDesc('id')
            ->firstOrFail();
    }

    /**
     * @param  array{starts_at: string, ends_at: string}  $window
     */
    private function addDemoProductLine(
        Opportunity $opportunity,
        Product $product,
        array $window,
        ?string $parentPath = null,
        string $quantity = '1',
        int $unitPrice = 2500,
    ): void {
        $productType = ProductType::from((string) $product->getRawOriginal('product_type'));

        (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
            'name' => $product->name,
            'itemable_id' => $product->id,
            'itemable_type' => Product::class,
            'item_type' => OpportunityItemType::Product->value,
            'parent_path' => $parentPath,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'transaction_type' => $productType === ProductType::Sale
                ? LineItemTransactionType::Sale->value
                : LineItemTransactionType::Rental->value,
            'starts_at' => $window['starts_at'],
            'ends_at' => $window['ends_at'],
        ]));
    }

    /**
     * @param  array{starts_at: string, ends_at: string}  $window
     */
    private function addDemoServiceLine(
        Opportunity $opportunity,
        Product $product,
        array $window,
        string $quantity = '1',
        int $unitPrice = 45000,
    ): void {
        (new AddOpportunityItem)($opportunity, AddOpportunityItemData::from([
            'name' => $product->name,
            'itemable_id' => $product->id,
            'itemable_type' => Product::class,
            'item_type' => OpportunityItemType::Service->value,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'transaction_type' => LineItemTransactionType::Service->value,
            'starts_at' => $window['starts_at'],
            'ends_at' => $window['ends_at'],
        ]));
    }

    /**
     * Build the CreateOpportunity action result for one demo opportunity.
     *
     * @param  array{starts_at: string, ends_at: string}  $window
     */
    private function buildDemoOpportunity(
        string $subject,
        string $reference,
        int $memberId,
        int $storeId,
        ?int $ownerId,
        array $window,
    ): OpportunityData {
        return (new CreateOpportunity)(CreateOpportunityData::from([
            'subject' => $subject,
            'reference' => $reference,
            'member_id' => $memberId,
            'store_id' => $storeId,
            'owned_by' => $ownerId,
            'starts_at' => $window['starts_at'],
            'ends_at' => $window['ends_at'],
            'currency' => app(CurrencyService::class)->baseCurrencyCode(),
        ]));
    }

    /** @codeCoverageIgnore */
    private function createDemoCustomFields(): void
    {
        // TODO: Implement with custom field example values
    }

    /**
     * Seed a realistic spread of demo activities (tasks, calls, meetings,
     * emails, notes) across the demo members. Each record is tagged
     * 'demo-data' so signals:clear-demo can remove them. Re-runs are idempotent:
     * each activity is skipped when a demo-tagged row with the same subject
     * already exists. The existence check is scoped to the 'demo-data' tag (the
     * same marker signals:clear-demo uses) so re-seeding never collides with a
     * real user's activity that happens to share a subject.
     */
    private function createDemoActivities(): void
    {
        $this->command->info('Seeding demo activities...');

        $user = User::query()->first();

        if ($user === null) {
            return;
        }

        // Anchor a few activities on demo members so the "Regarding" links work.
        $demoMembers = Member::query()
            ->whereJsonContains('tag_list', 'demo-data')
            ->limit(3)
            ->get();

        $activities = [
            [
                'subject' => 'Follow up on rental quote',
                'state' => 'task',
                'starts_at' => now()->addDay(),
                'regarding' => $demoMembers->first(),
            ],
            [
                'subject' => 'Confirm delivery schedule',
                'state' => 'call',
                'starts_at' => now()->addHours(3),
            ],
            [
                'subject' => 'Site visit for upcoming event',
                'state' => 'meeting',
                'location' => 'Client Office',
            ],
            [
                'subject' => 'Send updated price list',
                'state' => 'email',
            ],
            [
                'subject' => 'Customer prefers Friday deliveries',
                'state' => 'note',
                'description' => 'Noted during last phone call that Friday mornings work best for the customer.',
                'regarding' => $demoMembers->get(1),
            ],
            [
                'subject' => 'Initial consultation completed',
                'state' => 'completed',
            ],
        ];

        foreach ($activities as $data) {
            $existing = Activity::query()
                ->whereJsonContains('tag_list', 'demo-data')
                ->where('subject', $data['subject'])
                ->exists();

            if ($existing) {
                continue;
            }

            /** @var Member|null $regarding */
            $regarding = $data['regarding'] ?? null;

            Activity::factory()
                ->{$data['state']}()
                ->create([
                    'subject' => $data['subject'],
                    'description' => $data['description'] ?? null,
                    'location' => $data['location'] ?? null,
                    'owned_by' => $user->id,
                    'starts_at' => $data['starts_at'] ?? null,
                    'regarding_type' => $regarding !== null ? Member::class : null,
                    'regarding_id' => $regarding?->id,
                    'tag_list' => ['demo-data'],
                ]);
        }
    }
}
