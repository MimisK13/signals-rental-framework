<?php

namespace App\Services\Notifications;

/**
 * Safe merge-field template engine — parse-and-substitute only, no code execution.
 *
 * Grammar: {{ field.path | filter:arg | filter2 }}
 * Malformed {{ }} blocks are left as literal text. Unknown fields render as blank
 * (or the configured default behaviour) unless a default filter is chained.
 */
class MergeFieldRenderer
{
    public function __construct(
        private readonly MergeFieldFilterRegistry $filters,
    ) {}

    /**
     * Parse template content into literal and merge-field segments for caching.
     *
     * @return list<array{type: string, value?: string, field?: string, filters?: list<array{name: string, arg: string|null}>}>
     */
    public function compile(string $content): array
    {
        $segments = [];
        $offset = 0;
        $length = strlen($content);

        while ($offset < $length) {
            $open = strpos($content, '{{', $offset);

            if ($open === false) {
                $segments[] = ['type' => 'literal', 'value' => substr($content, $offset)];

                break;
            }

            if ($open > $offset) {
                $segments[] = ['type' => 'literal', 'value' => substr($content, $offset, $open - $offset)];
            }

            $close = strpos($content, '}}', $open + 2);

            if ($close === false) {
                $segments[] = ['type' => 'literal', 'value' => substr($content, $open)];

                break;
            }

            $inner = substr($content, $open + 2, $close - $open - 2);
            $parsed = $this->parseExpression(trim($inner));

            if ($parsed === null) {
                $segments[] = ['type' => 'literal', 'value' => substr($content, $open, $close - $open + 2)];
            } else {
                $segments[] = [
                    'type' => 'merge',
                    'field' => $parsed['field'],
                    'filters' => $parsed['filters'],
                ];
            }

            $offset = $close + 2;
        }

        return $segments;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function render(string $content, array $data, ?MergeFieldRenderContext $context = null): string
    {
        return $this->renderCompiled($this->compile($content), $data, $context);
    }

    /**
     * @param  list<array{type: string, value?: string, field?: string, filters?: list<array{name: string, arg: string|null}>}>  $segments
     * @param  array<string, mixed>  $data
     */
    public function renderCompiled(array $segments, array $data, ?MergeFieldRenderContext $context = null): string
    {
        $context ??= MergeFieldRenderContext::defaults();
        $output = '';

        foreach ($segments as $segment) {
            if ($segment['type'] === 'literal') {
                $output .= $segment['value'] ?? '';

                continue;
            }

            $value = $this->resolveField($segment['field'] ?? '', $data, $context);

            foreach ($segment['filters'] ?? [] as $filter) {
                if ($filter['name'] === 'default' && ($value === null || $value === '')) {
                    $value = $filter['arg'] ?? '';

                    continue;
                }

                $value = $this->filters->apply($filter['name'], $value, $filter['arg'], $context);
            }

            if ($value === null) {
                $output .= $context->unknownFieldBehaviour === 'blank' ? '' : (string) $context->unknownFieldBehaviour;

                continue;
            }

            $output .= (string) $value;
        }

        return $output;
    }

    /**
     * @return array{field: string, filters: list<array{name: string, arg: string|null}>}|null
     */
    public function parseExpression(string $expression): ?array
    {
        if ($expression === '') {
            return null;
        }

        $parts = $this->splitFilterChain($expression);

        if ($parts === [] || ! preg_match('/^[a-zA-Z0-9_.]+$/', $parts[0])) {
            return null;
        }

        $field = $parts[0];
        $filters = [];

        for ($i = 1, $count = count($parts); $i < $count; $i++) {
            $parsedFilter = $this->parseFilterSegment($parts[$i]);

            if ($parsedFilter === null) {
                return null;
            }

            $filters[] = $parsedFilter;
        }

        return [
            'field' => $field,
            'filters' => $filters,
        ];
    }

    /**
     * @return list<string>
     */
    private function splitFilterChain(string $expression): array
    {
        $parts = [];
        $current = '';
        $inQuotes = false;
        $length = strlen($expression);

        for ($i = 0; $i < $length; $i++) {
            $char = $expression[$i];

            if ($char === '"') {
                $inQuotes = ! $inQuotes;
                $current .= $char;

                continue;
            }

            if ($char === '|' && ! $inQuotes) {
                $parts[] = trim($current);
                $current = '';

                continue;
            }

            $current .= $char;
        }

        if ($current !== '') {
            $parts[] = trim($current);
        }

        return $parts;
    }

    /**
     * @return array{name: string, arg: string|null}|null
     */
    private function parseFilterSegment(string $segment): ?array
    {
        if (! preg_match('/^([a-zA-Z]+)(?::(?:"((?:[^"\\\\]|\\\\.)*)"|([^|]+))?)?$/', $segment, $matches)) {
            return null;
        }

        $arg = null;

        if (isset($matches[2]) && $matches[2] !== '') {
            $arg = stripcslashes($matches[2]);
        } elseif (isset($matches[3])) {
            $arg = trim($matches[3]);
        }

        return [
            'name' => $matches[1],
            'arg' => $arg,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveField(string $field, array $data, MergeFieldRenderContext $context): mixed
    {
        return data_get($data, $field);
    }
}
