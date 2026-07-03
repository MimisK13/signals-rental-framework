<?php

use App\Models\Invoice;
use App\Services\Invoices\InvoiceStatusResolver;
use Carbon\CarbonImmutable;

beforeEach(function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-07-10T12:00:00Z'));
    $this->resolver = app(InvoiceStatusResolver::class);
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

dataset('invoice status matrix', [
    'draft stays draft' => [
        'draft', 0, 10_000, 10_000, '2026-07-20T12:00:00Z', 'draft', false,
    ],
    'issued before due' => [
        'issued', 0, 10_000, 10_000, '2026-07-20T12:00:00Z', 'issued', false,
    ],
    'issued past due is overdue' => [
        'issued', 0, 10_000, 10_000, '2026-07-01T12:00:00Z', 'overdue', true,
    ],
    'partial before due' => [
        'issued', 4_000, 10_000, 6_000, '2026-07-20T12:00:00Z', 'partially_paid', false,
    ],
    'partial past due is overdue' => [
        'issued', 4_000, 10_000, 6_000, '2026-07-01T12:00:00Z', 'overdue', true,
    ],
    'fully paid' => [
        'issued', 10_000, 10_000, 0, '2026-07-01T12:00:00Z', 'paid', false,
    ],
    'void stays void' => [
        'void', 0, 10_000, 10_000, '2026-07-01T12:00:00Z', 'void', false,
    ],
    'credited stays credited' => [
        'credited', 0, 10_000, 0, '2026-07-01T12:00:00Z', 'credited', false,
    ],
]);

it('resolves effective status from config rules', function (
    string $stored,
    int $paid,
    int $total,
    int $outstanding,
    string $dueAt,
    string $expected,
    bool $overdue,
) {
    $due = CarbonImmutable::parse($dueAt);

    expect($this->resolver->resolve($stored, $paid, $total, $outstanding, $due))
        ->toBe($expected)
        ->and($this->resolver->isOverdue($stored, $outstanding, $due))->toBe($overdue);
})->with('invoice status matrix');

it('scopeOverdue finds issued unpaid invoices past due date', function () {
    $eligible = Invoice::factory()->create([
        'status' => 'issued',
        'amount_outstanding' => 5000,
        'due_at' => CarbonImmutable::parse('2026-07-01T12:00:00Z'),
    ]);

    Invoice::factory()->create([
        'status' => 'issued',
        'amount_outstanding' => 5000,
        'due_at' => CarbonImmutable::parse('2026-07-20T12:00:00Z'),
    ]);

    Invoice::factory()->create([
        'status' => 'draft',
        'amount_outstanding' => 5000,
        'due_at' => CarbonImmutable::parse('2026-07-01T12:00:00Z'),
    ]);

    $ids = Invoice::query()->overdue()->pluck('id')->all();

    expect($ids)->toBe([$eligible->id]);
});

it('model isOverdue accessor matches resolver', function () {
    $invoice = Invoice::factory()->create([
        'status' => 'issued',
        'amount_outstanding' => 2500,
        'due_at' => CarbonImmutable::parse('2026-07-01T12:00:00Z'),
    ]);

    expect($invoice->isOverdue())->toBeTrue()
        ->and($invoice->effectiveStatus())->toBe('overdue');
});
