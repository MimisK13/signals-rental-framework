<?php

namespace App\Services\Documents;

use App\Exceptions\Documents\DocumentRenderException;
use Illuminate\Support\Arr;
use Illuminate\Support\LazyCollection;

/**
 * Minimal Blade loop stack shim for sandboxed document template rendering.
 *
 * Provides the {@see $__env} loop API expected by compiled @foreach / @forelse
 * directives without bootstrapping the full Laravel view factory.
 */
class SandboxedLoopEnvironment
{
    /** @var list<array<string, mixed>> */
    private array $loopsStack = [];

    public static function maxIterations(): int
    {
        return max(1, (int) config('documents.max_loop_iterations', 500));
    }

    /**
     * Cap iterable loop sources before iteration to prevent runaway renders.
     *
     * @return iterable<mixed>|list<mixed>
     */
    public static function prepareLoopData(mixed $data): iterable
    {
        $max = self::maxIterations();

        if (is_array($data)) {
            if (count($data) > $max) {
                return array_slice($data, 0, $max, preserve_keys: true);
            }

            return $data;
        }

        if ($data instanceof \Countable && count($data) > $max) {
            throw DocumentRenderException::forbidden(
                "Loop collection exceeds the maximum of {$max} iterations allowed in document templates.",
            );
        }

        return $data;
    }

    public function addLoop(mixed $data): void
    {
        $length = is_countable($data) && ! $data instanceof LazyCollection
            ? count($data)
            : null;

        $parent = Arr::last($this->loopsStack);

        $this->loopsStack[] = [
            'iteration' => 0,
            'index' => 0,
            'remaining' => $length ?? null,
            'count' => $length,
            'first' => true,
            'last' => isset($length) ? $length === 1 : null,
            'odd' => false,
            'even' => true,
            'depth' => count($this->loopsStack) + 1,
            'parent' => $parent ? (object) $parent : null,
        ];
    }

    public function incrementLoopIndices(): void
    {
        if ($loop = Arr::last($this->loopsStack)) {
            $index = $loop['iteration'] + 1;
            $remaining = isset($loop['count']) ? max(0, $loop['count'] - $index) : null;

            $this->loopsStack[array_key_last($this->loopsStack)] = [
                'iteration' => $index,
                'index' => $loop['index'] + 1,
                'remaining' => $remaining,
                'count' => $loop['count'],
                'first' => $index === 1,
                'last' => isset($loop['count']) ? $index === $loop['count'] : null,
                'odd' => $index % 2 === 1,
                'even' => $index % 2 === 0,
                'depth' => $loop['depth'],
                'parent' => $loop['parent'],
            ];
        }
    }

    public function popLoop(): void
    {
        array_pop($this->loopsStack);
    }

    public function getLastLoop(): ?object
    {
        if ($last = Arr::last($this->loopsStack)) {
            return (object) $last;
        }

        return null;
    }
}
