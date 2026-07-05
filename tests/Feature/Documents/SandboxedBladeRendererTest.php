<?php

use App\Exceptions\Documents\DocumentRenderException;
use App\Services\Documents\SandboxedBladeRenderer;

describe('SandboxedBladeRenderer loop support', function () {
    beforeEach(function () {
        config(['documents.max_loop_iterations' => 3]);
        $this->renderer = app(SandboxedBladeRenderer::class);
    });

    it('renders rows from a foreach loop', function () {
        $html = $this->renderer->render(
            '@foreach ($items as $item)<p>{{ $item->name }}</p>@endforeach',
            [
                'items' => [
                    ['name' => 'Alpha'],
                    ['name' => 'Beta'],
                ],
            ],
            'sandbox-foreach-basic',
        );

        expect($html)->toContain('Alpha')->toContain('Beta');
    });

    it('supports nested foreach loops and loop indices', function () {
        $html = $this->renderer->render(
            "@foreach (\$groups as \$group)\n@foreach (\$group->items as \$item)<span>{{ \$loop->iteration }}</span>\n@endforeach\n@endforeach",
            [
                'groups' => [
                    (object) ['items' => [(object) ['name' => 'A'], (object) ['name' => 'B']]],
                    (object) ['items' => [(object) ['name' => 'C']]],
                ],
            ],
            'sandbox-foreach-nested',
        );

        expect(preg_replace('/\s+/', '', $html))->toBe('<span>1</span><span>2</span><span>1</span>');
    });

    it('rejects malicious foreach expressions during validation', function () {
        expect(fn () => $this->renderer->render(
            "@foreach (DB::table('users')->get() as \$user){{ \$user->id }}@endforeach",
            [],
            'sandbox-foreach-malicious',
        ))->toThrow(DocumentRenderException::class, 'Scope resolution');
    });

    it('caps pathologically large foreach collections', function () {
        $items = array_map(
            fn (int $index): array => ['name' => "Row {$index}"],
            range(1, 10),
        );

        $html = $this->renderer->render(
            '@foreach ($items as $item)<li>{{ $item->name }}</li>@endforeach',
            ['items' => $items],
            'sandbox-foreach-cap',
        );

        expect($html)->toContain('Row 1')
            ->toContain('Row 3')
            ->and($html)->not->toContain('Row 4');
    });

    it('blocks template access to the renderer instance via $this', function () {
        expect(fn () => $this->renderer->render(
            '{{ $this->files }}',
            ['label' => 'safe'],
            'sandbox-this-property-probe',
        ))->toThrow(DocumentRenderException::class);

        expect(fn () => $this->renderer->render(
            '@php var_dump($this); @endphp',
            [],
            'sandbox-this-php-probe',
        ))->toThrow(DocumentRenderException::class);
    });

    it('renders forelse empty branches', function () {
        $html = $this->renderer->render(
            '@forelse ($items as $item)<p>{{ $item->name }}</p>@empty<p>No lines</p>@endforelse',
            ['items' => []],
            'sandbox-forelse-empty',
        );

        expect($html)->toContain('No lines');
    });

    it('returns an empty string from executeCompiledTemplate when no buffer is active', function () {
        $compiledPath = tempnam(sys_get_temp_dir(), 'sandbox-blade-');
        file_put_contents($compiledPath, '<?php echo "compiled";');

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $method = new ReflectionMethod(SandboxedBladeRenderer::class, 'executeCompiledTemplate');
        $method->setAccessible(true);

        expect($method->invoke(null, $compiledPath, []))->toBe('');

        @unlink($compiledPath);
    });
});
