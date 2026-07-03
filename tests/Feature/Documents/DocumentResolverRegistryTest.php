<?php

use App\Contracts\Documents\DocumentDataResolver;
use App\Services\Documents\DocumentResolverRegistry;
use App\Services\Documents\OpportunityDataResolver;
use App\Services\SchemaBuilder;
use Illuminate\Database\Eloquent\Model;

/**
 * @implements DocumentDataResolver
 */
class PluginDocumentDataResolverStub implements DocumentDataResolver
{
    public function __construct(
        private readonly string $documentType,
        private readonly string $fieldName,
    ) {}

    public function type(): string
    {
        return $this->documentType;
    }

    public function label(): string
    {
        return 'Plugin '.ucfirst($this->documentType);
    }

    public function description(): string
    {
        return 'Plugin-registered document resolver stub.';
    }

    public function registerFields(SchemaBuilder $builder): void
    {
        $builder->string($this->fieldName)
            ->label('Plugin Field')
            ->group('Plugin')
            ->source('document')
            ->filterable(false)
            ->sortable(false);
    }

    public function loadSource(int $sourceId): Model
    {
        return new class extends Model
        {
            protected $table = 'users';

            public $timestamps = false;
        };
    }

    public function resolve(Model $source): array
    {
        return [$this->fieldName => 'plugin-value'];
    }
}

describe('DocumentResolverRegistry', function () {
    it('registers plugin resolvers and exposes them via types()', function () {
        $registry = new DocumentResolverRegistry;
        $registry->register(new PluginDocumentDataResolverStub('invoice', 'invoice.number'));

        expect($registry->types())->toHaveCount(1)
            ->and($registry->types()[0]['type'])->toBe('invoice')
            ->and($registry->has('invoice'))->toBeTrue();
    });

    it('returns grouped field trees from registerFields metadata', function () {
        $registry = new DocumentResolverRegistry;
        $registry->register(new PluginDocumentDataResolverStub('credit_note', 'credit_note.reference'));

        $tree = $registry->fieldTree('credit_note');

        expect($tree)->toHaveCount(1)
            ->and($tree[0]['name'])->toBe('Plugin')
            ->and($tree[0]['fields'][0]['name'])->toBe('credit_note.reference')
            ->and($tree[0]['fields'][0]['type'])->toBe('string');
    });

    it('invalidates cached field trees when a resolver re-registers', function () {
        $registry = app(DocumentResolverRegistry::class);
        $registry->fieldTree('opportunity');

        $reflection = new ReflectionClass(DocumentResolverRegistry::class);
        $cache = $reflection->getProperty('fieldTreeCache');
        $cache->setAccessible(true);

        expect($cache->getValue($registry))->toHaveKey('opportunity');

        $registry->invalidate('opportunity');

        expect($cache->getValue($registry))->not->toHaveKey('opportunity');
    });

    it('clears the type cache when register() is called again', function () {
        $registry = app(DocumentResolverRegistry::class);
        $registry->fieldTree('opportunity');

        $reflection = new ReflectionClass(DocumentResolverRegistry::class);
        $cache = $reflection->getProperty('fieldTreeCache');
        $cache->setAccessible(true);

        expect($cache->getValue($registry))->toHaveKey('opportunity');

        $registry->register(app(OpportunityDataResolver::class));

        expect($cache->getValue($registry))->not->toHaveKey('opportunity');
    });

    it('keeps the core opportunity resolver alongside plugin registrations', function () {
        $registry = app(DocumentResolverRegistry::class);
        $registry->register(new PluginDocumentDataResolverStub('invoice', 'invoice.number'));

        $types = collect($registry->types())->pluck('type')->all();

        expect($types)->toContain('opportunity', 'invoice');
    });
});
