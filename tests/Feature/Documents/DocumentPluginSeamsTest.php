<?php

use App\Contracts\Documents\DocumentDataResolver;
use App\Contracts\Documents\PdfDriver;
use App\Models\DocumentTemplate;
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

describe('receipt document type seam', function () {
    it('does not register receipt until the Payment model exists', function () {
        expect(app(DocumentTypeRegistry::class)->has('receipt'))->toBeFalse();
    });
});
