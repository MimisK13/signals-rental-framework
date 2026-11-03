<?php

declare(strict_types=1);

namespace App\Domain\Availability\Presentation\Livewire;

use App\Domain\Availability\Data\ProductAvailabilitySummary;
use App\Domain\Availability\Services\ProductAvailabilitySummaryReader;
use App\Domain\Products\Models\Product;
use App\Domain\Stores\Models\Store;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')] #[Title('Product Availability')]
final class ProductAvailability extends Component
{
    public Product $product;

    #[Url(as: 'store')]
    public ?int $storeId = null;

    #[Url(as: 'from')]
    public string $from = '';

    #[Url(as: 'until')]
    public string $until = '';

    public function mount(Product $product): void
    {
        Gate::authorize('availability.view');

        $this->product = $product;
        $this->from = $this->from !== ''
            ? $this->from
            : Carbon::today('UTC')->startOfDay()->format('Y-m-d\\TH:i');
        $this->until = $this->until !== ''
            ? $this->until
            : Carbon::today('UTC')->addDays(7)->startOfDay()->format('Y-m-d\\TH:i');

        $storeIds = $this->stores()->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all();

        if ($this->storeId === null || ! in_array($this->storeId, $storeIds, true)) {
            $this->storeId = $storeIds[0] ?? null;
        }
    }

    public function rendering(View $view): void
    {
        $view->title($this->product->name.' — Availability');
    }

    /**
     * @return Collection<int, Store>
     */
    #[Computed]
    public function stores(): Collection
    {
        return Store::query()
            ->inDefaultQueries()
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['storeId', 'from', 'until'], true)) {
            unset($this->summary);
        }
    }

    public function periodError(): ?string
    {
        try {
            $from = Carbon::parse($this->from);
            $until = Carbon::parse($this->until);
        } catch (\Throwable) {
            return 'Enter a valid availability period.';
        }

        return $until->greaterThan($from) ? null : 'The end of the period must be after its start.';
    }

    #[Computed]
    public function summary(): ?ProductAvailabilitySummary
    {
        if ($this->storeId === null || $this->periodError() !== null) {
            return null;
        }

        return app(ProductAvailabilitySummaryReader::class)->read(
            product: $this->product,
            storeId: $this->storeId,
            from: Carbon::parse($this->from),
            until: Carbon::parse($this->until),
        );
    }

    public function formatTimestamp(?string $timestamp): string
    {
        return $timestamp === null ? '—' : Carbon::parse($timestamp)->format('d M Y H:i');
    }

    public function phaseLabel(string $phase): string
    {
        return ucfirst($phase);
    }

    public function render(): View
    {
        return view('availability::product-availability');
    }
}
