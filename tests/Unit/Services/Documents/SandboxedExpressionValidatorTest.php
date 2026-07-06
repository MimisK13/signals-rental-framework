<?php

use App\Exceptions\Documents\DocumentRenderException;
use App\Services\Documents\BladeExpressionExtractor;
use App\Services\Documents\SandboxedExpressionValidator;

describe('SandboxedExpressionValidator allowlist policy', function () {
    beforeEach(function () {
        $this->validator = new SandboxedExpressionValidator;
    });

    it('allows property access, array access, and approved helpers', function () {
        $expressions = [
            '$opportunity->subject',
            '$items[0]->name',
            'count($items)',
            'number_format($total, 2)',
            'strtoupper($member->name)',
        ];

        foreach ($expressions as $expression) {
            expect(fn () => $this->validator->validate($expression))->not->toThrow(DocumentRenderException::class);
        }
    });

    it('validates benign echo expressions extracted from real templates without hanging', function () {
        $extractor = new BladeExpressionExtractor;
        $template = '<p>{{ $opportunity->subject }} ({{ count($items) }})</p>';

        $expressions = $extractor->extract($template);

        expect($expressions)->toBe([
            '$opportunity->subject',
            'count($items)',
        ]);

        foreach ($expressions as $expression) {
            $this->validator->validate($expression);
        }
    });

    it('rejects scope resolution and namespaced references', function () {
        expect(fn () => $this->validator->validate('DB::table("users")->get()'))
            ->toThrow(DocumentRenderException::class, 'Scope resolution');

        expect(fn () => $this->validator->validate('\\Illuminate\\Support\\Facades\\DB::table("users")->get()'))
            ->toThrow(DocumentRenderException::class, 'Namespaced references');
    });

    it('rejects disallowed function calls including exec, config, and env', function () {
        expect(fn () => $this->validator->validate("exec('id')"))
            ->toThrow(DocumentRenderException::class, 'Function [exec]');

        expect(fn () => $this->validator->validate("system('id')"))
            ->toThrow(DocumentRenderException::class, 'Function [system]');

        expect(fn () => $this->validator->validate("file_get_contents('/etc/passwd')"))
            ->toThrow(DocumentRenderException::class, 'Function [file_get_contents]');

        expect(fn () => $this->validator->validate("config('app.key')"))
            ->toThrow(DocumentRenderException::class, 'Function [config]');

        expect(fn () => $this->validator->validate("env('DB_PASSWORD')"))
            ->toThrow(DocumentRenderException::class, 'Function [env]');
    });

    it('rejects variable functions, method calls, and dynamic invocation', function () {
        expect(fn () => $this->validator->validate('$callback()'))
            ->toThrow(DocumentRenderException::class, 'Variable functions');

        expect(fn () => $this->validator->validate('$opportunity->getTotal()'))
            ->toThrow(DocumentRenderException::class, 'Method calls');

        expect(fn () => $this->validator->validate("('exec')('id')"))
            ->toThrow(DocumentRenderException::class, 'Dynamic function invocation');
    });

    it('rejects object instantiation and backticks', function () {
        expect(fn () => $this->validator->validate('new stdClass()'))
            ->toThrow(DocumentRenderException::class, 'Object instantiation');

        expect(fn () => $this->validator->validate('`id`'))
            ->toThrow(DocumentRenderException::class, 'Backticks');
    });
});
