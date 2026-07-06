<?php

use App\Models\CommunicationTemplate;
use App\Models\Currency;
use App\Services\Notifications\CommunicationTemplateRenderer;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    Currency::factory()->create(['code' => 'GBP', 'symbol' => '£', 'name' => 'British Pound']);
    settings()->set('company.name', 'Acme Rentals');
    settings()->set('company.base_currency', 'GBP');
});

it('renders email templates with merge fields and markdown html', function () {
    $template = CommunicationTemplate::factory()->email()->create([
        'subject' => 'Hello {{ contact.name }}',
        'body' => 'Total: **{{ invoice.total | currency }}**',
    ]);

    $result = app(CommunicationTemplateRenderer::class)->render($template, [
        'contact' => ['name' => 'Bob'],
        'invoice' => ['total' => 5000],
    ]);

    expect($result['subject'])->toBe('Hello Bob');
    expect($result['body'])->toBe('Total: **£50.00**');
    expect($result['html'])->toContain('<strong>£50.00</strong>');
});

it('renders sms templates as plain text without html', function () {
    $template = CommunicationTemplate::factory()->sms()->create([
        'body' => 'Ref {{ opportunity.reference }}',
    ]);

    $result = app(CommunicationTemplateRenderer::class)->render($template, [
        'opportunity' => ['reference' => 'Q-100'],
    ]);

    expect($result['body'])->toBe('Ref Q-100');
    expect($result['html'])->toBeNull();
    expect($result['subject'])->toBeNull();
});

it('caches compiled segments keyed by template version', function () {
    Cache::flush();

    $template = CommunicationTemplate::factory()->create([
        'version' => 3,
        'body' => 'Version {{ opportunity.id }}',
    ]);

    $renderer = app(CommunicationTemplateRenderer::class);
    $renderer->compiledSegments($template);

    expect(Cache::has('communication-template:compiled:'.$template->id.':3'))->toBeTrue();

    $template->update(['version' => 4]);
    $template->refresh();

    expect(Cache::has('communication-template:compiled:'.$template->id.':3'))->toBeTrue();
    expect(Cache::has('communication-template:compiled:'.$template->id.':4'))->toBeFalse();

    $renderer->compiledSegments($template);

    expect(Cache::has('communication-template:compiled:'.$template->id.':4'))->toBeTrue();
});

it('invalidates compiled cache on explicit invalidation', function () {
    Cache::flush();

    $template = CommunicationTemplate::factory()->create(['version' => 2]);
    $renderer = app(CommunicationTemplateRenderer::class);

    $renderer->compiledSegments($template);
    expect(Cache::has('communication-template:compiled:'.$template->id.':2'))->toBeTrue();

    $renderer->invalidateCompiledCache($template);

    expect(Cache::has('communication-template:compiled:'.$template->id.':2'))->toBeFalse();
});

it('wraps email output in branded templated email mailable', function () {
    $template = CommunicationTemplate::factory()->email()->create([
        'subject' => 'Confirmed',
        'body' => 'Thanks **{{ contact.name }}**',
    ]);

    $mailable = app(CommunicationTemplateRenderer::class)->renderBrandedEmail(
        $template,
        ['contact' => ['name' => 'Jane']],
        eyebrow: 'Booking Confirmed',
    );

    expect($mailable->subjectLine)->toBe('Confirmed');
    expect($mailable->bodyHtml)->toContain('<strong>Jane</strong>');
    expect($mailable->eyebrow)->toBe('Booking Confirmed');
});
