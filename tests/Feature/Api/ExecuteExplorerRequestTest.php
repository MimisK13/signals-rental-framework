<?php

use App\Actions\Api\ExecuteExplorerRequest;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
});

it('builds the outgoing request with method, url, query, headers, and bearer token', function () {
    config(['app.url' => 'https://signals.test']);

    Http::fake(function ($request) {
        expect($request->method())->toBe('GET')
            ->and($request->url())->toBe('https://signals.test/api/v1/products?page=2')
            ->and($request->header('Authorization'))->toBe(['Bearer secret-token'])
            ->and($request->header('X-Custom'))->toBe(['probe']);

        return Http::response(['products' => []], 200, [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer secret-token',
        ]);
    });

    $result = app(ExecuteExplorerRequest::class)(
        method: 'GET',
        path: '/api/v1/products',
        query: ['page' => '2'],
        headers: ['X-Custom' => 'probe'],
        bearerToken: 'secret-token',
    );

    expect($result['status'])->toBe(200)
        ->and($result['status_class'])->toBe('2xx')
        ->and($result['body'])->toContain('"products"')
        ->and($result['duration_ms'])->toBeGreaterThanOrEqual(0)
        ->and($result['headers']['Authorization'])->toBe(['Bearer '.ExecuteExplorerRequest::maskToken('')])
        ->and($result)->not->toHaveKey('error');
});

it('returns an empty body when the upstream response has no content', function () {
    config(['app.url' => 'https://signals.test']);

    Http::fake([
        '*' => Http::response('', 204),
    ]);

    $result = app(ExecuteExplorerRequest::class)(
        method: 'GET',
        path: '/api/v1/products',
        query: [],
        headers: [],
        bearerToken: 'secret-token',
    );

    expect($result['body'])->toBe('')
        ->and($result['status'])->toBe(204);
});

it('handles non-json responses with a raw body fallback', function () {
    config(['app.url' => 'https://signals.test']);

    Http::fake([
        '*' => Http::response('not-json', 200, ['Content-Type' => 'text/plain']),
    ]);

    $result = app(ExecuteExplorerRequest::class)(
        method: 'GET',
        path: '/api/v1/products',
        query: [],
        headers: [],
        bearerToken: 'secret-token',
    );

    expect($result['body'])->toBe('not-json')
        ->and($result['status'])->toBe(200);
});

it('handles connection failures gracefully', function () {
    config(['app.url' => 'https://signals.test']);

    Http::fake(function () {
        throw new ConnectionException('Connection timed out');
    });

    $result = app(ExecuteExplorerRequest::class)(
        method: 'GET',
        path: '/api/v1/products',
        query: [],
        headers: [],
        bearerToken: 'secret-token',
    );

    expect($result['status'])->toBe(0)
        ->and($result['status_class'])->toBe('5xx')
        ->and($result['body'])->toBe('')
        ->and($result['error'])->toContain('Connection failed');
});

it('masks bearer tokens for display', function () {
    expect(ExecuteExplorerRequest::maskToken('1234567890abcdef'))->toBe('1234••••cdef')
        ->and(ExecuteExplorerRequest::maskToken('short'))->toBe('••••••••');
});
