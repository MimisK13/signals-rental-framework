<?php

use App\Domain\Availability\Contracts\AvailabilityResolutionProvider;
use App\Domain\Availability\Enums\AvailabilityResolution;
use App\Domain\Availability\Enums\DemandPhase;
use App\Domain\Availability\Events\OpportunityAvailabilityChanged;
use App\Domain\Availability\Jobs\RecalculateAvailabilityJob;
use App\Domain\Availability\Models\Demand;
use App\Domain\Availability\Services\RecalculationPipeline;
use App\Domain\Availability\Shortages\Services\ShortageDetector;
use App\Domain\Products\Models\Product;
use App\Domain\Stock\Models\StockLevel;
use App\Domain\Stores\Models\Store;
use App\Domain\Webhooks\Services\WebhookService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->app->bind(AvailabilityResolutionProvider::class, fn () => new class implements AvailabilityResolutionProvider
    {
        public function resolve(): AvailabilityResolution
        {
            return AvailabilityResolution::Daily;
        }
    });

    Carbon::setTestNow(Carbon::parse('2026-06-18T00:00:00Z'));

    $this->store = Store::factory()->create(['timezone' => 'UTC']);
});

afterEach(function () {
    Carbon::setTestNow();
});

describe('opportunity-scoped availability broadcast', function () {
    it('broadcasts OpportunityAvailabilityChanged on each affected opportunity channel', function () {
        Queue::fake();
        Event::fake([OpportunityAvailabilityChanged::class]);

        $product = Product::factory()->bulk()->create();
        StockLevel::factory()->bulk()->create([
            'product_id' => $product->id,
            'store_id' => $this->store->id,
            'quantity_held' => 5,
        ]);

        // Two demands on the same opportunity → a single de-duplicated broadcast.
        foreach ([1, 2] as $sourceId) {
            Demand::factory()
                ->phase(DemandPhase::Committed)
                ->window(Carbon::parse('2026-06-20T00:00:00Z'), Carbon::parse('2026-06-21T00:00:00Z'))
                ->create([
                    'product_id' => $product->id,
                    'store_id' => $this->store->id,
                    'source_id' => $sourceId,
                    'quantity' => 1,
                    'metadata' => ['opportunity_id' => 77],
                ]);
        }

        (new RecalculateAvailabilityJob($product->id, $this->store->id))->handle(
            app(RecalculationPipeline::class),
            app(WebhookService::class),
            app(ShortageDetector::class),
        );

        Event::assertDispatchedTimes(OpportunityAvailabilityChanged::class, 1);
        Event::assertDispatched(OpportunityAvailabilityChanged::class, function (OpportunityAvailabilityChanged $event) use ($product) {
            $names = collect($event->broadcastOn())->map(fn ($c) => $c->name)->all();

            return $event->opportunityId === 77
                && $event->productId === $product->id
                && $event->storeId === $this->store->id
                && in_array('private-availability.opportunity.77', $names, true);
        });
    });

    it('logs a warning for an opportunity_item demand missing metadata.opportunity_id', function () {
        Queue::fake();
        Event::fake([OpportunityAvailabilityChanged::class]);
        $log = Log::spy();

        $product = Product::factory()->bulk()->create();
        StockLevel::factory()->bulk()->create([
            'product_id' => $product->id,
            'store_id' => $this->store->id,
            'quantity_held' => 5,
        ]);

        // An opportunity_item demand with NO opportunity_id in its metadata — a
        // data-consistency defect that must surface in the log, not fail silently.
        Demand::factory()
            ->phase(DemandPhase::Committed)
            ->window(Carbon::parse('2026-06-20T00:00:00Z'), Carbon::parse('2026-06-21T00:00:00Z'))
            ->create([
                'product_id' => $product->id,
                'store_id' => $this->store->id,
                'source_type' => 'opportunity_item',
                'source_id' => 999,
                'quantity' => 1,
                'metadata' => [],
            ]);

        (new RecalculateAvailabilityJob($product->id, $this->store->id))->handle(
            app(RecalculationPipeline::class),
            app(WebhookService::class),
            app(ShortageDetector::class),
        );

        Event::assertNotDispatched(OpportunityAvailabilityChanged::class);
        $log->shouldHaveReceived('warning')
            ->withArgs(fn (string $message): bool => str_contains($message, 'missing metadata.opportunity_id'))
            ->once();
    });

    it('does not broadcast to opportunities when no opportunity-item demand exists', function () {
        Queue::fake();
        Event::fake([OpportunityAvailabilityChanged::class]);

        $product = Product::factory()->bulk()->create();
        StockLevel::factory()->bulk()->create([
            'product_id' => $product->id,
            'store_id' => $this->store->id,
            'quantity_held' => 5,
        ]);

        // A non-opportunity demand (e.g. quarantine) → product/store broadcast only.
        Demand::factory()
            ->phase(DemandPhase::Committed)
            ->window(Carbon::parse('2026-06-20T00:00:00Z'), Carbon::parse('2026-06-21T00:00:00Z'))
            ->create([
                'product_id' => $product->id,
                'store_id' => $this->store->id,
                'source_type' => 'quarantine',
                'quantity' => 1,
                'metadata' => [],
            ]);

        (new RecalculateAvailabilityJob($product->id, $this->store->id))->handle(
            app(RecalculationPipeline::class),
            app(WebhookService::class),
            app(ShortageDetector::class),
        );

        Event::assertNotDispatched(OpportunityAvailabilityChanged::class);
    });
});
