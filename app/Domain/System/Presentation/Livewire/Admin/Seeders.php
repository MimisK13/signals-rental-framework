<?php

declare(strict_types=1);

namespace App\Domain\System\Presentation\Livewire\Admin;

use App\Domain\Communications\Models\EmailTemplate;
use App\Domain\Invoices\Models\Invoice;
use App\Domain\Lists\Models\ListName;
use App\Domain\Members\Models\Member;
use App\Domain\Notifications\Models\NotificationType;
use App\Domain\Rates\Models\RateDefinition;
use App\Domain\ReferenceData\Models\Country;
use App\Domain\Stores\Models\Store;
use App\Domain\Tax\Models\OrganisationTaxClass;
use App\Domain\Tax\Models\ProductTaxClass;
use App\Domain\Tax\Models\TaxRate;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

#[Layout('components.layouts.app')]
#[Title('Seeders')]
final class Seeders extends Component
{
    /** @var array<string, array{name: string, class: string, description: string, items: list<string>, seeded: bool, isDefault: bool}> */
    public array $seeders = [];

    public function mount(): void
    {
        $this->loadSeederStatus();
    }

    public function seed(string $key): void
    {
        $seeder = $this->seeders[$key] ?? null;

        if (! $seeder) {
            $this->addError('seeder', "Unknown seeder: {$key}");

            return;
        }

        try {
            Artisan::call('db:seed', [
                '--class' => $seeder['class'],
                '--force' => true,
            ]);

            $this->loadSeederStatus();
            $this->dispatch('seeder-completed');
        } catch (\Exception $e) {
            $this->addError('seeder', "Seeder failed: {$e->getMessage()}");
        }
    }

    public function seedAll(): void
    {
        try {
            foreach (['permissions', 'roles'] as $key) {
                Artisan::call('db:seed', [
                    '--class' => $this->seeders[$key]['class'],
                    '--force' => true,
                ]);
            }

            $this->loadSeederStatus();
            $this->dispatch('seeder-completed');
        } catch (\Exception $e) {
            $this->addError('seeder', "Seeder failed: {$e->getMessage()}");
        }
    }

    private function loadSeederStatus(): void
    {
        $permissionsSeeded = Schema::hasTable('permissions') && Permission::query()->exists();

        $systemRoles = ['Admin', 'Operations Manager', 'Sales', 'Warehouse', 'Read Only'];
        $rolesSeeded = Schema::hasTable('roles') && Role::query()->whereIn('name', $systemRoles)->count() === count($systemRoles);

        $emailTemplatesSeeded = Schema::hasTable('email_templates') && EmailTemplate::query()->exists();
        $notificationTypesSeeded = Schema::hasTable('notification_types') && NotificationType::query()->exists();

        $countriesSeeded = Schema::hasTable('countries') && Country::query()->exists();
        $listsSeeded = Schema::hasTable('list_names') && ListName::query()->where('is_system', true)->exists();
        $taxClassesSeeded = Schema::hasTable('product_tax_classes') && ProductTaxClass::query()->exists()
            && Schema::hasTable('organisation_tax_classes') && OrganisationTaxClass::query()->exists();

        $taxRatesSeeded = Schema::hasTable('tax_rates') && TaxRate::query()->exists();

        $rateDefinitionsSeeded = Schema::hasTable('rate_definitions') && RateDefinition::query()->where('is_preset', true)->exists();

        $storesSeeded = Schema::hasTable('stores') && Store::query()->exists();

        $demoStoresSeeded = Schema::hasTable('stores')
            && Store::query()->whereJsonContains('tag_list', 'demo-data')->exists();
        $demoMembersSeeded = Schema::hasTable('members')
            && Member::query()->whereJsonContains('tag_list', 'demo-data')->exists();
        $demoInvoicesSeeded = Schema::hasTable('invoices')
            && Invoice::query()->whereJsonContains('tag_list', 'demo-data')->exists();

        $this->seeders = [
            'countries' => [
                'name' => 'CountrySeeder',
                'class' => 'App\\Domain\\ReferenceData\\Database\\Seeders\\CountrySeeder',
                'description' => 'Populates the countries table from the reference data file.',
                'items' => [
                    'All ISO 3166-1 countries with currency codes, phone prefixes, and default timezones',
                ],
                'seeded' => $countriesSeeded,
                'isDefault' => true,
            ],
            'lists' => [
                'name' => 'ListOfValuesSeeder',
                'class' => 'App\\Domain\\Lists\\Database\\Seeders\\ListOfValuesSeeder',
                'description' => 'Creates the editable system lists used across the app — contact-detail type classification and the Activity Type list (manageable in Settings → List Names).',
                'items' => [
                    'Address Type (Billing, Shipping, Primary, Registered)',
                    'Email Type (Work, Personal, Billing, Support)',
                    'Phone Type (Work, Mobile, Home, Fax)',
                    'Link Type (Website, LinkedIn, Facebook, Instagram, X, YouTube)',
                    'Relationship Type (Employee, Director, Contractor, Agent)',
                    'Activity Type (Task, Call, Fax, Email, Meeting, Note, Letter)',
                ],
                'seeded' => $listsSeeded,
                'isDefault' => true,
            ],
            'tax_classes' => [
                'name' => 'TaxClassSeeder',
                'class' => 'App\\Domain\\Tax\\Database\\Seeders\\TaxClassSeeder',
                'description' => 'Creates default product and organisation tax classes.',
                'items' => [
                    'Organisation tax class: Standard',
                    'Product tax classes: Standard, Exempt',
                ],
                'seeded' => $taxClassesSeeded,
                'isDefault' => true,
            ],
            'tax_rates' => [
                'name' => 'TaxRateSeeder',
                'class' => 'App\\Domain\\Tax\\Database\\Seeders\\TaxRateSeeder',
                'description' => 'Creates default UK tax rates.',
                'items' => [
                    'Standard — 20%',
                    'Reduced — 5%',
                    'Zero — 0%',
                ],
                'seeded' => $taxRatesSeeded,
                'isDefault' => true,
            ],
            'rate_definitions' => [
                'name' => 'RateDefinitionPresetSeeder',
                'class' => 'App\\Domain\\Rates\\Database\\Seeders\\RateDefinitionPresetSeeder',
                'description' => 'Creates the industry-standard rate definition presets used to price products.',
                'items' => [
                    'Daily, Hourly, Half-Hourly, Weekly and Monthly Rate presets',
                    'Fixed Rate, Fixed Rate and Subs Days presets',
                    'Multiplier and Factor variants (daily, hourly, monthly)',
                ],
                'seeded' => $rateDefinitionsSeeded,
                'isDefault' => true,
            ],
            'permissions' => [
                'name' => 'PermissionSeeder',
                'class' => 'App\\Domain\\Administration\\Database\\Seeders\\PermissionSeeder',
                'description' => 'Creates all system permissions used for role-based access control.',
                'items' => [
                    'Settings permissions (view, manage)',
                    'User management permissions (view, invite, edit, deactivate, activate, reset-password)',
                    'Role permissions (view, manage)',
                    'Resource permissions (members, opportunities, invoices, products, stock)',
                    'System permissions (action-log, custom-fields, static-data, webhooks)',
                ],
                'seeded' => $permissionsSeeded,
                'isDefault' => true,
            ],
            'roles' => [
                'name' => 'RoleSeeder',
                'class' => 'App\\Domain\\Administration\\Database\\Seeders\\RoleSeeder',
                'description' => 'Creates the five system roles with their default permission sets.',
                'items' => [
                    'Admin — all permissions',
                    'Operations Manager — all resource permissions (no settings/users/roles)',
                    'Sales — opportunities, invoices, members, product/report viewing',
                    'Warehouse — stock and product management',
                    'Read Only — read-only access to all resources',
                ],
                'seeded' => $rolesSeeded,
                'isDefault' => true,
            ],
            'email_templates' => [
                'name' => 'EmailTemplateSeeder',
                'class' => 'App\\Domain\\Communications\\Database\\Seeders\\EmailTemplateSeeder',
                'description' => 'Creates default email templates for system notifications.',
                'items' => [
                    'User Invited — invitation email sent to new users',
                    'Password Reset — password reset notification',
                    'Test Email — email delivery verification',
                ],
                'seeded' => $emailTemplatesSeeded,
                'isDefault' => true,
            ],
            'notification_types' => [
                'name' => 'NotificationTypeSeeder',
                'class' => 'App\\Domain\\Notifications\\Database\\Seeders\\NotificationTypeSeeder',
                'description' => 'Creates core notification types with default channel configurations.',
                'items' => [
                    'User notifications (invited, deactivated, reactivated)',
                    'System notifications (password reset, test email)',
                ],
                'seeded' => $notificationTypesSeeded,
                'isDefault' => true,
            ],
            'stores' => [
                'name' => 'StoreSeeder',
                'class' => 'App\\Domain\\Stores\\Database\\Seeders\\StoreSeeder',
                'description' => 'Creates a default store if none exist.',
                'items' => [
                    'Main Warehouse (default store)',
                ],
                'seeded' => $storesSeeded,
                'isDefault' => false,
            ],
            'demo' => [
                'name' => 'DemoDataSeeder',
                'class' => 'Database\\Seeders\\DemoDataSeeder',
                'description' => 'Populates a full Phase-4 UAT substrate: CRM volume data plus billing, sub-hire, and container scenarios for end-to-end testing.',
                'items' => [
                    '3 demo stores plus CRM volume (organisations, venues, contacts, relationships)',
                    'Member enrichment: discount/price categories with tiers; EU B2B, charity, tax-exempt, and ROW tax variants',
                    'Product enrichment: supplier costs, reduced tax category, containerable serialised product with assets',
                    '~12 opportunities across draft, quotation, order, dispatched, and returned (deal price, surcharge, quantity-break)',
                    'Quote-to-cash: draft, paid, partial + refund + credit note, deposit, pro-forma, and overdue invoices',
                    'Sub-hire: draft PO plus sent/received PO with confirmed virtual stock intake',
                    'Containers: open flight case and sealed packed case on serialised demo assets',
                ],
                'seeded' => $demoStoresSeeded && $demoMembersSeeded && $demoInvoicesSeeded,
                'isDefault' => false,
            ],
        ];
    }

    public function render(): View
    {
        return view('system::admin.seeders');
    }
}
