<?php

namespace App\Services\DataTable;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DataTableCsvExporter
{
    /**
     * @param  Builder<Model>  $query
     * @param  array<int, array<string, mixed>>  $columns
     */
    public function export(Builder $query, array $columns, string $filename): StreamedResponse
    {
        $exportColumns = collect($columns)
            ->reject(fn (array $column): bool => in_array($column['type'] ?? null, ['checkbox', 'actions'], true))
            ->reject(fn (array $column): bool => ($column['key'] ?? '') === 'avatar')
            ->values()
            ->all();

        $headers = array_map(fn (array $column): string => $column['label'] ?? $column['key'], $exportColumns);
        $keys = array_map(fn (array $column): string => $column['key'], $exportColumns);

        if (! in_array('id', $keys, true)) {
            array_unshift($headers, 'ID');
            array_unshift($keys, 'id');
        }

        return response()->streamDownload(function () use ($query, $keys, $headers): void {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                throw new \RuntimeException('Unable to open output stream for CSV export.');
            }

            fputcsv($handle, $headers);

            $query->chunk(500, function ($rows) use ($handle, $keys): void {
                foreach ($rows as $row) {
                    fputcsv($handle, array_map(
                        fn (string $key): mixed => $this->serializeValue(data_get($row, $key)),
                        $keys,
                    ));
                }
            });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    private function serializeValue(mixed $value): mixed
    {
        if ($value instanceof \BackedEnum) {
            return method_exists($value, 'label') ? $value->label() : $value->value;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if ($value instanceof Model) {
            return $value->name ?? $value->getKey();
        }

        if (is_array($value) || is_object($value)) {
            $encoded = json_encode($value);

            return $encoded !== false ? $encoded : '[encoding error]';
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        return $value;
    }
}
