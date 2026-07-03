<?php

namespace App\Services\Documents;

use App\Contracts\Documents\DocumentDataResolver;
use App\Services\SchemaBuilder;
use App\Services\SchemaRegistry;
use App\ValueObjects\FieldDefinition;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Plugin-extensible registry of document data resolvers.
 *
 * Resolver field metadata is the single source of truth for the template field
 * browser (P20) and the document-type fields API. {@see SchemaRegistry} delegates
 * document-type lookups here so both consumers share identical definitions.
 */
class DocumentResolverRegistry
{
    /** @var array<string, DocumentDataResolver> */
    private array $resolvers = [];

    /**
     * Post-resolve extenders keyed by resolver type. Each extender receives the
     * resolved data array and source model, and returns the (possibly mutated) array.
     *
     * @var array<string, list<callable(array<string, mixed>, Model): array<string, mixed>>>
     */
    private array $resolveExtenders = [];

    /**
     * @var array<string, list<array{name: string, fields: list<array<string, mixed>>}>>
     */
    private array $fieldTreeCache = [];

    /**
     * @var array<string, array<string, FieldDefinition>>
     */
    private array $fieldDefinitionCache = [];

    public function register(DocumentDataResolver $resolver): void
    {
        $this->resolvers[$resolver->type()] = $resolver;
        $this->invalidate($resolver->type());
    }

    /**
     * @param  callable(array<string, mixed>, Model): array<string, mixed>  $extender
     */
    public function extendResolve(string $type, callable $extender): void
    {
        $this->resolveExtenders[$type][] = $extender;
    }

    /**
     * @throws InvalidArgumentException when the type is not registered
     */
    public function get(string $type): DocumentDataResolver
    {
        return $this->resolvers[$type]
            ?? throw new InvalidArgumentException("Unknown document type: {$type}");
    }

    public function has(string $type): bool
    {
        return isset($this->resolvers[$type]);
    }

    /**
     * @return list<array{type: string, label: string, description: string}>
     */
    public function types(): array
    {
        return array_values(array_map(
            static fn (DocumentDataResolver $resolver): array => [
                'type' => $resolver->type(),
                'label' => $resolver->label(),
                'description' => $resolver->description(),
            ],
            $this->resolvers,
        ));
    }

    /**
     * Flat field definitions keyed by merge-field path.
     *
     * @return array<string, FieldDefinition>
     */
    public function fieldDefinitions(string $type): array
    {
        if (isset($this->fieldDefinitionCache[$type])) {
            return $this->fieldDefinitionCache[$type];
        }

        $definitions = $this->buildFieldDefinitions($this->get($type));
        $this->fieldDefinitionCache[$type] = $definitions;

        return $definitions;
    }

    /**
     * Grouped field tree for the template field browser.
     *
     * @return list<array{name: string, fields: list<array<string, mixed>>}>
     */
    public function fieldTree(string $type): array
    {
        if (isset($this->fieldTreeCache[$type])) {
            return $this->fieldTreeCache[$type];
        }

        $tree = $this->buildFieldTree($this->fieldDefinitions($type));
        $this->fieldTreeCache[$type] = $tree;

        return $tree;
    }

    public function load(string $type, int $sourceId): Model
    {
        return $this->get($type)->loadSource($sourceId);
    }

    /**
     * @return array<string, mixed>
     */
    public function resolve(string $type, int $sourceId): array
    {
        $resolver = $this->get($type);
        $source = $resolver->loadSource($sourceId);
        $data = $resolver->resolve($source);

        foreach ($this->resolveExtenders[$type] ?? [] as $extender) {
            $data = $extender($data, $source);
        }

        return $data;
    }

    public function invalidate(?string $type = null): void
    {
        if ($type === null) {
            $this->fieldTreeCache = [];
            $this->fieldDefinitionCache = [];

            return;
        }

        unset($this->fieldTreeCache[$type], $this->fieldDefinitionCache[$type]);
        app(SchemaRegistry::class)->invalidateDocumentType($type);
    }

    /**
     * @return array<string, FieldDefinition>
     */
    private function buildFieldDefinitions(DocumentDataResolver $resolver): array
    {
        $builder = new SchemaBuilder;
        $resolver->registerFields($builder);

        return $builder->build();
    }

    /**
     * @param  array<string, FieldDefinition>  $definitions
     * @return list<array{name: string, fields: list<array<string, mixed>>}>
     */
    private function buildFieldTree(array $definitions): array
    {
        /** @var array<string, list<array<string, mixed>>> $grouped */
        $grouped = [];

        foreach ($definitions as $definition) {
            $groupName = $definition->group ?? 'General';
            $grouped[$groupName] ??= [];
            $grouped[$groupName][] = $definition->toArray();
        }

        $tree = [];

        foreach ($grouped as $name => $fields) {
            $tree[] = [
                'name' => $name,
                'fields' => $fields,
            ];
        }

        return $tree;
    }
}
