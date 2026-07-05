<?php

namespace App\Services\Notifications;

use App\Models\NotificationType;
use App\Models\Opportunity;

/**
 * Builds preview merge-field payloads for communication template editor.
 */
class NotificationPreviewData
{
    /**
     * @return array<string, mixed>
     */
    public function sampleForType(NotificationType $type): array
    {
        $schema = $type->data_schema ?? [];
        $paths = $this->collectFieldPaths($schema);

        if ($this->pathsInclude($paths, 'invoice.')) {
            return $this->invoiceSample();
        }

        if ($this->pathsInclude($paths, 'shortage.')) {
            return $this->shortageSample();
        }

        if ($this->pathsInclude($paths, 'document.')) {
            return $this->documentSample();
        }

        return $this->opportunitySample();
    }

    /**
     * @return array<string, mixed>
     */
    public function fromOpportunity(Opportunity $opportunity): array
    {
        $opportunity->loadMissing(['member', 'store']);

        return [
            'opportunity' => [
                'id' => $opportunity->id,
                'subject' => $opportunity->subject,
                'reference' => $opportunity->reference ?? $opportunity->number,
                'starts_at' => $opportunity->starts_at?->toIso8601String(),
                'ends_at' => $opportunity->ends_at?->toIso8601String(),
            ],
            'contact' => [
                'name' => $opportunity->member->name ?? 'Contact',
            ],
            'store' => [
                'name' => $opportunity->store->name ?? 'Store',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function opportunitySample(): array
    {
        return [
            'opportunity' => [
                'id' => 1042,
                'subject' => 'Summer Festival Stage Package',
                'reference' => 'OPP-1042',
                'starts_at' => '2026-07-15T09:00:00Z',
                'ends_at' => '2026-07-17T18:00:00Z',
            ],
            'contact' => [
                'name' => 'Alex Sample',
            ],
            'store' => [
                'name' => 'Sample Store',
            ],
            'user' => [
                'name' => 'Jordan Admin',
                'email' => 'admin@example.com',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function invoiceSample(): array
    {
        return [
            'invoice' => [
                'id' => 2087,
                'number' => 'INV-2087',
                'total' => '1250.00',
                'due_at' => '2026-07-20T00:00:00Z',
            ],
            'contact' => [
                'name' => 'Alex Sample',
            ],
            'store' => [
                'name' => 'Sample Store',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function shortageSample(): array
    {
        return [
            'shortage' => [
                'id' => 12,
                'product_name' => 'LED Par Can',
                'quantity' => 4,
            ],
            'opportunity' => [
                'id' => 1042,
                'subject' => 'Summer Festival Stage Package',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function documentSample(): array
    {
        return [
            'document' => [
                'id' => 55,
                'type' => 'quote',
                'number' => 'QUO-0042',
            ],
            'opportunity' => [
                'id' => 1042,
                'subject' => 'Summer Festival Stage Package',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return list<string>
     */
    private function collectFieldPaths(array $schema): array
    {
        $paths = [];

        foreach ($schema['groups'] ?? [] as $group) {
            foreach ($group['fields'] ?? [] as $field) {
                if (isset($field['path']) && is_string($field['path'])) {
                    $paths[] = $field['path'];
                }
            }
        }

        return $paths;
    }

    /**
     * @param  list<string>  $paths
     */
    private function pathsInclude(array $paths, string $prefix): bool
    {
        foreach ($paths as $path) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
