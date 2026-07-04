<?php

use App\Services\Api\WebhookEventRegistry;
use App\Services\Api\WebhookService;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Source files whose dedicated recorder/dispatcher classes emit webhook events
 * outside (or in addition to) the central {@see DispatchWebhookForAuditableEvent}
 * bridge. A typo or omission in {@see WebhookEventRegistry} silently drops these
 * deliveries because {@see WebhookService::dispatch()} only enqueues for
 * registered names.
 *
 * @return list<string>
 */
function recorderWebhookSourceFiles(): array
{
    return [
        app_path('Services/Shortages/ShortageEventRecorder.php'),
        app_path('Services/VirtualStock/VirtualStockIntakeEventRecorder.php'),
        app_path('Services/Containers/ContainerEventRecorder.php'),
        app_path('Services/PurchaseOrders/PurchaseOrderEventRecorder.php'),
        app_path('Services/Documents/DocumentEventDispatcher.php'),
        app_path('Services/Documents/DocumentNumberGenerator.php'),
        ...File::glob(app_path('Actions/Documents/*.php')) ?: [],
    ];
}

/**
 * Extract webhook event names emitted by recorder/dispatcher source files.
 *
 * Scans for:
 *  - direct {@see WebhookService::dispatch()} calls
 *  - {@see ShortageEventRecorder} audit actions bridged onto the webhook bus
 *  - {@see DocumentEventDispatcher} document/template/sequence/pdfGenerated callers
 *
 * @return list<string>
 */
function recorderDispatchedWebhookEvents(): array
{
    $events = [];

    foreach (recorderWebhookSourceFiles() as $path) {
        $contents = File::get($path);

        preg_match_all(
            "/(?:->dispatch|webhooks->dispatch)\(\s*'([a-z_]+(?:\.[a-z_]+)+)'/",
            $contents,
            $dispatchMatches,
        );

        preg_match_all(
            "/\\\$this->record\([^,]+,\s*'([a-z_]+(?:\.[a-z_]+)+)'/",
            $contents,
            $recordMatches,
        );

        foreach (array_merge($dispatchMatches[1], $recordMatches[1]) as $event) {
            $events[$event] = true;
        }

        if (str_contains($path, 'DocumentEventDispatcher')
            || str_contains($path, 'DocumentNumberGenerator')
            || str_contains($path, 'Actions/Documents')) {
            preg_match_all(
                "/DocumentEventDispatcher::class\)->(?:document|template|sequence)\(\s*[^,]+,\s*'([a-z_]+(?:\.[a-z_]+)+)'/s",
                $contents,
                $documentMatches,
            );

            foreach ($documentMatches[1] as $event) {
                $events[$event] = true;
            }

            if (str_contains($contents, '->pdfGenerated(')) {
                $events['document.pdf_generated'] = true;
            }
        }

        if (str_contains($path, 'ShortageEventRecorder.php')) {
            preg_match_all(
                "/action:\s*'([a-z_]+(?:\.[a-z_]+)+)'/",
                $contents,
                $auditMatches,
            );

            foreach ($auditMatches[1] as $event) {
                $events[$event] = true;
            }
        }
    }

    return array_keys($events);
}

it('exposes names parity with the legacy WebhookService allow-list', function () {
    $registry = app(WebhookEventRegistry::class);

    expect($registry->names())->toEqual(WebhookService::EVENTS)
        ->and($registry->names())->toHaveCount(count(WebhookEventRegistry::CORE_EVENT_NAMES));
});

it('defaults webhook event weights to 1', function () {
    $registry = app(WebhookEventRegistry::class);

    foreach ($registry->all() as $event) {
        expect($event['weight'])->toBe(1);
    }

    expect($registry->weight('product.created'))->toBe(1)
        ->and($registry->weight('opportunity.converted_to_order'))->toBe(1);
});

it('groups webhook events by their resource prefix', function () {
    $registry = app(WebhookEventRegistry::class);

    expect($registry->groups())
        ->toHaveKey('product')
        ->and($registry->groups()['product']['events'])
        ->toContain('product.created');
});

it('registers every webhook event emitted by recorder and dispatcher services', function () {
    $registry = app(WebhookEventRegistry::class);
    $registered = $registry->names();

    $missing = array_values(array_diff(recorderDispatchedWebhookEvents(), $registered));

    expect($missing)->toBe([]);
});

it('discovers recorder-fired shortage, container, and document events', function () {
    expect(recorderDispatchedWebhookEvents())
        ->toContain('shortage.detected')
        ->toContain('shortage.resolution.created')
        ->toContain('container.item.packed')
        ->toContain('virtual_stock_intake.confirmed')
        ->toContain('purchase_order.sent')
        ->toContain('document.finalised')
        ->toContain('document_sequence.reset');
});
