<?php

namespace App\Data\Api;

use Spatie\LaravelData\Data;

class ApiEndpointData extends Data
{
    public function __construct(
        public string $name,
        public string $method,
        public string $uri,
        public ?string $resource,
        public ?string $operation,
        public ?string $ability,
        public int $price_weight,
    ) {}

    /**
     * @param  array<string, mixed>  $entry
     */
    public static function fromCatalogueEntry(array $entry): self
    {
        return new self(
            name: (string) $entry['route_name'],
            method: (string) $entry['method'],
            uri: (string) $entry['uri'],
            resource: isset($entry['resource']) ? (string) $entry['resource'] : null,
            operation: isset($entry['operation']) ? (string) $entry['operation'] : null,
            ability: isset($entry['required_ability']) ? (string) $entry['required_ability'] : null,
            price_weight: (int) $entry['price_weight'],
        );
    }
}
