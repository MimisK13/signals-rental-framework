<?php

use App\Contracts\Documents\DocumentDataResolver;
use App\Contracts\Documents\PdfDriver;
use App\Models\CreditNote;
use App\Models\DocumentTemplate;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Member;
use App\Models\Opportunity;
use App\Models\Store;
use App\Services\Documents\DocumentResolverRegistry;
use App\Services\Documents\DocumentTypeRegistry;
use App\Services\Documents\PdfDriverManager;
use App\Services\Plugins\PluginDocumentTemplateSeederRegistry;
use App\Services\Plugins\PluginRegistrar;
use App\Services\SchemaBuilder;
use Database\Seeders\DocumentTemplateSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\Eloquent\Model;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
});

describe('core document type registry', function () {
    it('registers receipt against InvoicePayment and only the expected core types', function () {
        /** @var array<string, array{resolver_type: string, model_type: class-string, label: string}> $expectedCoreDocumentTypes */
        $expectedCoreDocumentTypes = [
            'quote' => [
                'resolver_type' => 'opportunity',
                'model_type' => Opportunity::class,
                'label' => 'Quote',
            ],
            'invoice' => [
                'resolver_type' => 'invoice',
                'model_type' => Invoice::class,
                'label' => 'Invoice',
            ],
            'credit_note' => [
                'resolver_type' => 'credit_note',
                'model_type' => CreditNote::class,
                'label' => 'Credit Note',
            ],
            'delivery_note' => [
                'resolver_type' => 'opportunity',
                'model_type' => Opportunity::class,
                'label' => 'Delivery Note',
            ],
            'pick_list' => [
                'resolver_type' => 'opportunity',
                'model_type' => Opportunity::class,
                'label' => 'Pick List',
            ],
            'return_list' => [
                'resolver_type' => 'return_list',
                'model_type' => Opportunity::class,
                'label' => 'Return List',
            ],
            'pro_forma' => [
                'resolver_type' => 'invoice',
                'model_type' => Invoice::class,
                'label' => 'Pro Forma Invoice',
            ],
            'damage_report' => [
                'resolver_type' => 'opportunity',
                'model_type' => Opportunity::class,
                'label' => 'Damage Report',
            ],
            'purchase_order' => [
                'resolver_type' => 'opportunity',
                'model_type' => Opportunity::class,
                'label' => 'Purchase Order',
            ],
            'statement' => [
                'resolver_type' => 'statement',
                'model_type' => Member::class,
                'label' => 'Statement',
            ],
            'receipt' => [
                'resolver_type' => 'receipt',
                'model_type' => InvoicePayment::class,
                'label' => 'Payment Receipt',
            ],
        ];

        $registry = app(DocumentTypeRegistry::class);

        $actualCoreDocumentTypes = [];

        foreach ($expectedCoreDocumentTypes as $type => $definition) {
            $actualCoreDocumentTypes[$type] = [
                'resolver_type' => $registry->resolverType($type),
                'model_type' => $registry->modelType($type),
                'label' => $registry->get($type)['label'],
            ];
        }

        expect($actualCoreDocumentTypes)->toBe($expectedCoreDocumentTypes)
            ->and(collect($registry->all())->pluck('type')->sort()->values()->all())
            ->toBe(collect(array_keys($expectedCoreDocumentTypes))->sort()->values()->all())
            ->and(app(DocumentResolverRegistry::class)->has('receipt'))->toBeTrue()
            ->and(app(DocumentResolverRegistry::class)->has('invoice'))->toBeTrue()
            ->and(app(DocumentResolverRegistry::class)->has('credit_note'))->toBeTrue();
    });
});

class FakePluginDocumentResolver implements DocumentDataResolver
{
    public function type(): string
    {
        return 'equipment_certificate';
    }

    public function label(): string
    {
        return 'Equipment Certificate';
    }

    public function description(): string
    {
        return 'Fake plugin document resolver.';
    }

    public function registerFields(SchemaBuilder $builder): void
    {
        $builder->string('certificate.number')->label('Certificate Number')->group('Plugin');
    }

    public function loadSource(int $sourceId): Model
    {
        return Opportunity::query()->findOrFail($sourceId);
    }

    public function resolve(Model $source): array
    {
        return ['certificate' => ['number' => 'CERT-001']];
    }
}

class FakePluginPdfDriver implements PdfDriver
{
    public static bool $called = false;

    public function render(string $html, array $pageConfig): string
    {
        self::$called = true;

        return '%PDF-fake';
    }
}

describe('PluginRegistrar document seams', function () {
    it('registers a custom document type and resolver', function () {
        $registrar = app(PluginRegistrar::class);

        $registrar
            ->documentType(
                'equipment_certificate',
                'equipment_certificate',
                Opportunity::class,
                'Equipment Certificate',
            )
            ->documentResolver(new FakePluginDocumentResolver);

        $types = app(DocumentTypeRegistry::class);

        expect($types->has('equipment_certificate'))->toBeTrue()
            ->and($types->resolverType('equipment_certificate'))->toBe('equipment_certificate')
            ->and(app(DocumentResolverRegistry::class)->has('equipment_certificate'))->toBeTrue();
    });

    it('extends resolver output through the end-of-resolve hook', function () {
        $registry = app(DocumentResolverRegistry::class);

        $registry->extendResolve('opportunity', function (array $data, Model $source): array {
            $data['plugin'] = ['injected' => 'yes'];

            return $data;
        });

        $opportunity = Opportunity::factory()->create();

        $resolved = $registry->resolve('opportunity', $opportunity->id);

        expect($resolved['plugin']['injected'] ?? null)->toBe('yes');
    });

    it('registers a custom PDF driver', function () {
        FakePluginPdfDriver::$called = false;

        app(PluginRegistrar::class)->pdfDriver('fake-plugin', FakePluginPdfDriver::class);

        $manager = app(PdfDriverManager::class);

        expect($manager->availableDrivers())->toContain('fake-plugin')
            ->and($manager->driver('fake-plugin'))->toBeInstanceOf(FakePluginPdfDriver::class);
    });

    it('seeds plugin templates when a store is seeded', function () {
        $seeded = false;

        app(PluginRegistrar::class)->seedDocumentTemplates(function (Store $store) use (&$seeded): void {
            $seeded = true;

            DocumentTemplate::query()->firstOrCreate(
                ['store_id' => $store->id, 'slug' => 'plugin-equipment-cert'],
                [
                    'name' => 'Plugin Equipment Certificate',
                    'document_type' => 'equipment_certificate',
                    'model_type' => Opportunity::class,
                    'body_html' => '<p>Plugin template</p>',
                    'is_default' => false,
                    'is_system' => false,
                    'is_active' => true,
                    'version' => 1,
                    'last_edited_at' => now(),
                ],
            );
        });

        $store = Store::factory()->create();
        app(DocumentTemplateSeeder::class)->seedForStore($store);

        expect($seeded)->toBeTrue()
            ->and(DocumentTemplate::query()->where('slug', 'plugin-equipment-cert')->exists())->toBeTrue();
    });

    it('exposes the plugin template seeder registry for inspection', function () {
        app(PluginDocumentTemplateSeederRegistry::class)->register(fn () => null);

        expect(app(PluginDocumentTemplateSeederRegistry::class)->all())->toHaveCount(1);
    });
});
