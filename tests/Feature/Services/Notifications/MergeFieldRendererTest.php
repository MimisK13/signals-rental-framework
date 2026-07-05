<?php

use App\Models\Currency;
use App\Models\User;
use App\Services\Notifications\CoreMergeFieldFilters;
use App\Services\Notifications\MergeFieldFilterRegistry;
use App\Services\Notifications\MergeFieldRenderContext;
use App\Services\Notifications\MergeFieldRenderer;
use App\Support\Timezone;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Currency::factory()->create(['code' => 'GBP', 'symbol' => '£', 'name' => 'British Pound']);
    Currency::factory()->create(['code' => 'USD', 'symbol' => '$', 'name' => 'US Dollar']);

    settings()->set('company.date_format_php', 'd/m/Y');
    settings()->set('company.time_format_php', 'H:i');
    settings()->set('company.base_currency', 'GBP');
    settings()->set('company.timezone', 'Europe/London');
    settings()->set('preferences.number_decimal_separator', '.');
    settings()->set('preferences.number_thousands_separator', ',');
    settings()->set('preferences.currency_display', 'symbol');

    $registry = new MergeFieldFilterRegistry;
    CoreMergeFieldFilters::register($registry);
    $this->renderer = new MergeFieldRenderer($registry);
    $this->context = MergeFieldRenderContext::defaults();
});

it('resolves nested merge fields', function () {
    $result = $this->renderer->render('Hello {{ contact.name }}!', [
        'contact' => ['name' => 'Alice'],
    ], $this->context);

    expect($result)->toBe('Hello Alice!');
});

it('returns blank for unknown fields by default', function () {
    $result = $this->renderer->render('Value: {{ missing.field }}', [], $this->context);

    expect($result)->toBe('Value: ');
});

it('returns null from parseExpression for empty strings', function () {
    expect($this->renderer->parseExpression(''))->toBeNull();
});

it('leaves malformed unclosed merge tags as literal text', function () {
    $result = $this->renderer->render('Keep {{ unclosed tag here', [], $this->context);

    expect($result)->toBe('Keep {{ unclosed tag here');
});

it('leaves merge tags with invalid field paths as literal text', function () {
    $result = $this->renderer->render('Bad {{ invalid@field }} stays', [], $this->context);

    expect($result)->toBe('Bad {{ invalid@field }} stays');
});

it('leaves merge tags with invalid filter syntax as literal text', function () {
    $result = $this->renderer->render('Bad {{ field | bad filter }} stays', [], $this->context);

    expect($result)->toBe('Bad {{ field | bad filter }} stays');
});

it('applies upper filter', function () {
    expect($this->renderer->render('{{ name | upper }}', ['name' => 'alice'], $this->context))
        ->toBe('ALICE');
});

it('applies lower filter', function () {
    expect($this->renderer->render('{{ name | lower }}', ['name' => 'ALICE'], $this->context))
        ->toBe('alice');
});

it('applies title filter', function () {
    expect($this->renderer->render('{{ name | title }}', ['name' => 'jane smith'], $this->context))
        ->toBe('Jane Smith');
});

it('applies default filter for missing values', function () {
    expect($this->renderer->render('{{ missing | default:"N/A" }}', [], $this->context))
        ->toBe('N/A');
});

it('applies default filter for empty string values', function () {
    expect($this->renderer->render('{{ name | default:"Guest" }}', ['name' => ''], $this->context))
        ->toBe('Guest');
});

it('applies date filter using timezone helper conventions', function () {
    Carbon::setTestNow('2026-07-03T12:00:00Z');

    $this->actingAs(User::factory()->create(['timezone' => 'Europe/London']));

    $result = $this->renderer->render(
        '{{ event.starts_at | date }}',
        ['event' => ['starts_at' => '2026-07-01T09:00:00Z']],
        $this->context,
    );

    expect($result)->toBe('01/07/2026');
});

it('applies date filter with custom format argument', function () {
    $result = $this->renderer->render(
        '{{ event.starts_at | date:"Y-m-d" }}',
        ['event' => ['starts_at' => '2026-07-01T09:00:00Z']],
        $this->context,
    );

    expect($result)->toBe('2026-07-01');
});

it('applies time filter using timezone helper conventions', function () {
    $this->actingAs(User::factory()->create(['timezone' => 'Europe/London']));

    $result = $this->renderer->render(
        '{{ event.starts_at | time }}',
        ['event' => ['starts_at' => '2026-07-01T09:00:00Z']],
        $this->context,
    );

    expect(app(Timezone::class)->toLocal('2026-07-01T09:00:00Z')->format('H:i'))
        ->toBe($result);
});

it('applies currency filter from integer minor units without floats', function () {
    $result = $this->renderer->render(
        '{{ invoice.total | currency }}',
        ['invoice' => ['total' => 12_550]],
        $this->context,
    );

    expect($result)->toBe('£125.50');
});

it('rejects float values in currency filter', function () {
    expect(fn () => CoreMergeFieldFilters::currency(125.50, null, $this->context))
        ->toThrow(InvalidArgumentException::class, 'float');
});

it('rejects decimal string values in currency filter', function () {
    expect(fn () => CoreMergeFieldFilters::currency('125.50', null, $this->context))
        ->toThrow(InvalidArgumentException::class, 'decimal strings');
});

it('applies currency filter with explicit currency code argument', function () {
    $result = $this->renderer->render(
        '{{ amount | currency:USD }}',
        ['amount' => 1000],
        $this->context,
    );

    expect($result)->toBe('$10.00');
});

it('applies number filter', function () {
    expect($this->renderer->render('{{ qty | number:0 }}', ['qty' => 1500], $this->context))
        ->toBe('1,500');
});

it('applies truncate filter', function () {
    $result = $this->renderer->render(
        '{{ text | truncate:10 }}',
        ['text' => 'Hello wonderful world'],
        $this->context,
    );

    expect($result)->toBe('Hello w...');
});

it('applies plural filter for singular count', function () {
    expect($this->renderer->render('{{ count | plural:"item|items" }}', ['count' => 1], $this->context))
        ->toBe('item');
});

it('applies plural filter for plural count', function () {
    expect($this->renderer->render('{{ count | plural:"item|items" }}', ['count' => 3], $this->context))
        ->toBe('items');
});

it('chains filters left to right', function () {
    $result = $this->renderer->render(
        '{{ name | default:"guest" | upper }}',
        [],
        $this->context,
    );

    expect($result)->toBe('GUEST');
});

it('chains currency after default filter', function () {
    $result = $this->renderer->render(
        '{{ invoice.total | default:0 | currency }}',
        [],
        $this->context,
    );

    expect($result)->toBe('£0.00');
});

it('compile and renderCompiled produce identical output', function () {
    $content = 'Ref {{ opportunity.reference | upper | default:"N/A" }}';
    $data = ['opportunity' => ['reference' => 'abc-123']];
    $compiled = $this->renderer->compile($content);

    expect($this->renderer->render($content, $data, $this->context))
        ->toBe($this->renderer->renderCompiled($compiled, $data, $this->context));
});

it('supports configurable unknown field behaviour', function () {
    $context = new MergeFieldRenderContext(unknownFieldBehaviour: '—');

    expect($this->renderer->render('{{ missing }}', [], $context))->toBe('—');
});

describe('filter matrix edge cases', function () {
    it('passes through null date filter output', function (mixed $value, mixed $expected) {
        expect(CoreMergeFieldFilters::date($value, null, $this->context))->toBe($expected);
    })->with([
        'null' => [null, null],
        'empty string' => ['', ''],
    ]);

    it('passes through non-datetime date filter values unchanged', function () {
        expect(CoreMergeFieldFilters::date('not-a-date', null, $this->context))->toBe('not-a-date');
    });
});
