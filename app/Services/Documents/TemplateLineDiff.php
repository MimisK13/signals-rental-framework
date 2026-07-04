<?php

namespace App\Services\Documents;

/**
 * Line-based diff for document template source using longest-common-subsequence backtracking.
 */
class TemplateLineDiff
{
    public const int DEFAULT_COLLAPSE_THRESHOLD = 4;

    /**
     * @return list<array{type: 'equal'|'insert'|'delete'|'collapsed', lines: list<string>, count?: int}>
     */
    public function diff(string $old, string $new, int $collapseThreshold = self::DEFAULT_COLLAPSE_THRESHOLD): array
    {
        $old = str_replace(["\r\n", "\r"], "\n", $old);
        $new = str_replace(["\r\n", "\r"], "\n", $new);

        if ($old === $new) {
            return [];
        }

        $oldLines = $this->splitLines($old);
        $newLines = $this->splitLines($new);

        $ops = $this->computeOps($oldLines, $newLines);

        return $this->collapseEqualRuns($ops, max(1, $collapseThreshold));
    }

    /**
     * @return list<string>
     */
    private function splitLines(string $text): array
    {
        if ($text === '') {
            return [];
        }

        return explode("\n", $text);
    }

    /**
     * @param  list<string>  $oldLines
     * @param  list<string>  $newLines
     * @return list<array{type: 'equal'|'insert'|'delete', lines: list<string>}>
     */
    private function computeOps(array $oldLines, array $newLines): array
    {
        $oldCount = count($oldLines);
        $newCount = count($newLines);

        if ($oldCount === 0 && $newCount === 0) {
            return [];
        }

        if ($oldCount === 0) {
            return [['type' => 'insert', 'lines' => $newLines]];
        }

        if ($newCount === 0) {
            return [['type' => 'delete', 'lines' => $oldLines]];
        }

        /** @var list<list<int>> $lengths */
        $lengths = array_fill(0, $oldCount + 1, array_fill(0, $newCount + 1, 0));

        for ($oldIndex = 1; $oldIndex <= $oldCount; $oldIndex++) {
            for ($newIndex = 1; $newIndex <= $newCount; $newIndex++) {
                if ($oldLines[$oldIndex - 1] === $newLines[$newIndex - 1]) {
                    $lengths[$oldIndex][$newIndex] = $lengths[$oldIndex - 1][$newIndex - 1] + 1;

                    continue;
                }

                $lengths[$oldIndex][$newIndex] = max(
                    $lengths[$oldIndex - 1][$newIndex],
                    $lengths[$oldIndex][$newIndex - 1],
                );
            }
        }

        /** @var list<array{type: 'equal'|'insert'|'delete', lines: list<string>}> $ops */
        $ops = [];
        $oldIndex = $oldCount;
        $newIndex = $newCount;

        while ($oldIndex > 0 || $newIndex > 0) {
            if ($oldIndex > 0 && $newIndex > 0 && $oldLines[$oldIndex - 1] === $newLines[$newIndex - 1]) {
                $ops[] = ['type' => 'equal', 'lines' => [$oldLines[$oldIndex - 1]]];
                $oldIndex--;
                $newIndex--;

                continue;
            }

            if ($newIndex > 0 && ($oldIndex === 0 || $lengths[$oldIndex][$newIndex - 1] >= $lengths[$oldIndex - 1][$newIndex])) {
                $ops[] = ['type' => 'insert', 'lines' => [$newLines[$newIndex - 1]]];
                $newIndex--;

                continue;
            }

            $ops[] = ['type' => 'delete', 'lines' => [$oldLines[$oldIndex - 1]]];
            $oldIndex--;
        }

        return $this->mergeAdjacentOps(array_reverse($ops));
    }

    /**
     * @param  list<array{type: 'equal'|'insert'|'delete', lines: list<string>}>  $ops
     * @return list<array{type: 'equal'|'insert'|'delete', lines: list<string>}>
     */
    private function mergeAdjacentOps(array $ops): array
    {
        /** @var list<array{type: 'equal'|'insert'|'delete', lines: list<string>}> $merged */
        $merged = [];

        foreach ($ops as $op) {
            $lastIndex = count($merged) - 1;

            if ($lastIndex >= 0 && $merged[$lastIndex]['type'] === $op['type']) {
                $merged[$lastIndex]['lines'] = array_merge($merged[$lastIndex]['lines'], $op['lines']);

                continue;
            }

            $merged[] = $op;
        }

        return $merged;
    }

    /**
     * @param  list<array{type: 'equal'|'insert'|'delete', lines: list<string>}>  $ops
     * @return list<array{type: 'equal'|'insert'|'delete'|'collapsed', lines: list<string>, count?: int}>
     */
    private function collapseEqualRuns(array $ops, int $collapseThreshold): array
    {
        /** @var list<array{type: 'equal'|'insert'|'delete'|'collapsed', lines: list<string>, count?: int}> $collapsed */
        $collapsed = [];

        foreach ($ops as $op) {
            if ($op['type'] !== 'equal' || count($op['lines']) <= $collapseThreshold) {
                $collapsed[] = $op;

                continue;
            }

            $collapsed[] = [
                'type' => 'collapsed',
                'lines' => [],
                'count' => count($op['lines']),
            ];
        }

        return $collapsed;
    }
}
