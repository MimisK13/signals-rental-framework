<?php

declare(strict_types=1);

namespace App\Domain\Stock\Presentation\Livewire;

use App\Domain\Stock\Actions\CreateStockLevel;
use App\Domain\Stock\Actions\UpdateStockLevel;
use App\Domain\Stock\Data\CreateStockLevelData;
use App\Domain\Stock\Data\UpdateStockLevelData;
use App\Domain\Stock\Enums\StockMethod;
use App\Domain\Stock\Models\StockLevel;
use App\Models\Product;
use App\Models\Store;
use App\Services\Api\RansackFilter;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
final class Form extends Component
{
    public ?int $stockLevelId = null;

    public ?int $productId = null;

    public ?int $storeId = null;

    public string $itemName = '';

    public string $assetNumber = '';

    public string $serialNumber = '';

    public int $quantityHeld = 0;

    /** Product autocomplete */
    public string $productSearch = '';

    /** @var array<int, array{id: int, name: string, stock_method: int|null}> */
    public array $productResults = [];

    public bool $productSelected = false;

    public ?string $selectedProductName = null;

    public ?string $stockMethodLabel = null;

    public bool $isSerialisedStock = false;

    /** Entry mode for serialised stock creation: 'single' | 'bulk' */
    public string $entryMode = 'single';

    /**
     * Bulk-entry rows. status ∈ '' (empty/unchecked), 'valid', 'duplicate'.
     *
     * @var list<array{asset_number: string, serial_number: string, asset_status: string, serial_status: string}>
     */
    public array $bulkRows = [];

    /** Whether a product_id was passed via query param (for cancel redirect) */
    public ?int $sourceProductId = null;

    /** Whether any stores exist */
    public bool $hasStores = true;

    public function mount(?StockLevel $stockLevel = null): void
    {
        // Check if any stores exist
        $storeQuery = Store::query()->orderBy('name');
        $stores = $storeQuery->get(['id']);
        $this->hasStores = $stores->count() > 0;

        // Default to first store if only one exists
        if ($stores->count() === 1) {
            $this->storeId = $stores->first()->id;
        }

        // Pre-populate product from query param
        $productIdParam = request()->query('product_id');
        if (is_string($productIdParam) && is_numeric($productIdParam)) {
            $this->sourceProductId = (int) $productIdParam;
            $this->selectProduct((int) $productIdParam);
        }

        if ($stockLevel?->exists) {
            $this->stockLevelId = $stockLevel->id;
            $this->storeId = $stockLevel->store_id;
            $this->assetNumber = $stockLevel->asset_number ?? '';
            $this->serialNumber = $stockLevel->serial_number ?? '';
            $this->quantityHeld = (int) ($stockLevel->quantity_held ?? 0);

            // Load the product for editing
            if ($stockLevel->product_id) {
                $this->selectProduct($stockLevel->product_id);
            }
        }
    }

    public function updatedProductSearch(): void
    {
        $search = trim($this->productSearch);

        if (mb_strlen($search) < 1) {
            $this->productResults = [];

            return;
        }

        $this->productResults = Product::query()
            ->where('name', 'ilike', '%'.RansackFilter::escapeLike($search).'%')
            ->where('is_active', true)
            ->limit(15)
            ->get(['id', 'name', 'stock_method'])
            ->map(fn (Product $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'stock_method' => $p->stock_method?->value,
            ])
            ->toArray();
    }

    public function selectProduct(int $productId): void
    {
        $product = Product::query()->where('is_active', true)->find($productId, ['id', 'name', 'stock_method']);

        if (! $product) {
            // Also allow selecting inactive products when editing existing stock levels
            $product = Product::query()->find($productId, ['id', 'name', 'stock_method']);
        }

        if (! $product) {
            return;
        }

        $this->productId = $product->id;
        $this->selectedProductName = $product->name;
        $this->itemName = $product->name;
        $this->productSelected = true;
        $this->productSearch = '';
        $this->productResults = [];

        // Determine stock method
        $this->isSerialisedStock = $product->stock_method === StockMethod::Serialised;
        $this->stockMethodLabel = $product->stock_method?->label() ?? 'Bulk';

        if ($this->isSerialisedStock) {
            $this->quantityHeld = 1;
        }
    }

    public function clearProduct(): void
    {
        $this->productId = null;
        $this->selectedProductName = null;
        $this->itemName = '';
        $this->productSelected = false;
        $this->productSearch = '';
        $this->productResults = [];
        $this->stockMethodLabel = null;
        $this->isSerialisedStock = false;
        $this->quantityHeld = 0;
        $this->entryMode = 'single';
        $this->bulkRows = [];
    }

    /**
     * Switch between single and bulk serialised-entry modes.
     */
    public function setEntryMode(string $mode): void
    {
        if (! in_array($mode, ['single', 'bulk'], true)) {
            return;
        }

        $this->entryMode = $mode;

        if ($mode === 'bulk' && $this->bulkRows === []) {
            $this->bulkRows = [$this->emptyBulkRow()];
        }
    }

    /**
     * @return array{asset_number: string, serial_number: string, asset_status: string, serial_status: string}
     */
    private function emptyBulkRow(): array
    {
        return [
            'asset_number' => '',
            'serial_number' => '',
            'asset_status' => '',
            'serial_status' => '',
        ];
    }

    public function addBulkRow(): void
    {
        $this->bulkRows[] = $this->emptyBulkRow();
        $this->dispatch('bulk-row-added');
    }

    public function removeBulkRow(int $index): void
    {
        if (count($this->bulkRows) <= 1) {
            return;
        }

        unset($this->bulkRows[$index]);
        $this->bulkRows = array_values($this->bulkRows);
        $this->revalidateBulkRows();
    }

    /**
     * Livewire hook — re-check every row whenever any bulk field changes
     * (covers cross-row duplicate effects on blur).
     */
    public function updatedBulkRows(): void
    {
        $this->revalidateBulkRows();
    }

    /**
     * Recompute asset_status + serial_status for every row.
     *
     * Empty value → ''. Value already in the DB column OR appearing more than
     * once across the form rows → 'duplicate'. Otherwise → 'valid'.
     */
    public function revalidateBulkRows(): void
    {
        $assets = array_values(array_filter(array_map(
            static fn (array $row): string => trim($row['asset_number']),
            $this->bulkRows,
        ), static fn (string $value): bool => $value !== ''));

        $serials = array_values(array_filter(array_map(
            static fn (array $row): string => trim($row['serial_number']),
            $this->bulkRows,
        ), static fn (string $value): bool => $value !== ''));

        $existingAssets = $assets === []
            ? []
            : StockLevel::query()->whereIn('asset_number', $assets)->pluck('asset_number')->all();

        $existingSerials = $serials === []
            ? []
            : StockLevel::query()->whereIn('serial_number', $serials)->pluck('serial_number')->all();

        $assetCounts = array_count_values($assets);
        $serialCounts = array_count_values($serials);

        foreach ($this->bulkRows as $i => $row) {
            $this->bulkRows[$i]['asset_status'] = $this->statusFor(
                $row['asset_number'],
                $existingAssets,
                $assetCounts,
            );
            $this->bulkRows[$i]['serial_status'] = $this->statusFor(
                $row['serial_number'],
                $existingSerials,
                $serialCounts,
            );
        }
    }

    /**
     * @param  list<string>  $existing
     * @param  array<string, int>  $counts
     */
    private function statusFor(string $value, array $existing, array $counts): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        if (in_array($value, $existing, true) || ($counts[$value] ?? 0) > 1) {
            return 'duplicate';
        }

        return 'valid';
    }

    /**
     * Classify a bulk row by how many of its two fields are filled.
     *
     * @param  array{asset_number: string, serial_number: string, asset_status: string, serial_status: string}  $row
     * @return 'empty'|'partial'|'complete'
     */
    private function rowState(array $row): string
    {
        $hasAsset = trim($row['asset_number']) !== '';
        $hasSerial = trim($row['serial_number']) !== '';

        if (! $hasAsset && ! $hasSerial) {
            return 'empty';
        }

        if ($hasAsset && $hasSerial) {
            return 'complete';
        }

        return 'partial';
    }

    /**
     * Count of fully-completed rows (both fields filled). Drives the submit label.
     */
    public function completeRowCount(): int
    {
        return count(array_filter(
            $this->bulkRows,
            fn (array $row): bool => $this->rowState($row) === 'complete',
        ));
    }

    /**
     * Whether the bulk form is in a submittable state.
     *
     * Empty rows (e.g. the trailing row left after pressing Enter) are ignored.
     * The form is submittable when there is at least one complete row, no
     * partial rows remain, and every complete row is unique (valid/valid).
     */
    public function bulkCanSubmit(): bool
    {
        if ($this->entryMode !== 'bulk' || $this->bulkRows === []) {
            return false;
        }

        $hasComplete = false;

        foreach ($this->bulkRows as $row) {
            $state = $this->rowState($row);

            if ($state === 'empty') {
                continue;
            }

            if ($state === 'partial') {
                return false;
            }

            // Complete row — must be unique on both fields.
            if ($row['asset_status'] !== 'valid' || $row['serial_status'] !== 'valid') {
                return false;
            }

            $hasComplete = true;
        }

        return $hasComplete;
    }

    public function save(): void
    {
        // Bulk serialised entry (create-only).
        if ($this->isSerialisedStock && $this->entryMode === 'bulk' && ! $this->stockLevelId) {
            $this->saveBulk();

            return;
        }

        $rules = [
            'productId' => ['required', 'integer', 'exists:products,id'],
            'storeId' => ['required', 'integer', 'exists:stores,id'],
            'assetNumber' => ['nullable', 'string', 'max:255', Rule::unique('stock_levels', 'asset_number')->ignore($this->stockLevelId)],
            'serialNumber' => ['nullable', 'string', 'max:255', Rule::unique('stock_levels', 'serial_number')->ignore($this->stockLevelId)],
        ];

        if (! $this->stockLevelId) {
            $rules['quantityHeld'] = ['required', 'integer', 'min:0'];
        }

        $this->validate($rules, [
            'assetNumber.unique' => 'This asset / barcode number is already in use.',
            'serialNumber.unique' => 'This serial number is already in use.',
        ]);

        // Bulk products may only ever have a single stock level.
        if (! $this->stockLevelId) {
            $product = Product::find($this->productId);
            $isBulk = $product && $product->stock_method !== StockMethod::Serialised;

            if ($isBulk && StockLevel::where('product_id', $this->productId)->exists()) {
                throw ValidationException::withMessages([
                    'productId' => 'Bulk products can only have a single stock level.',
                ]);
            }
        }

        $payload = [
            'product_id' => $this->productId,
            'store_id' => $this->storeId,
            'item_name' => $this->itemName ?: null,
            'asset_number' => $this->assetNumber ?: null,
            'serial_number' => $this->serialNumber ?: null,
            'barcode' => $this->assetNumber ?: null,
        ];

        if ($this->stockLevelId) {
            $stockLevel = StockLevel::findOrFail($this->stockLevelId);
            $result = (new UpdateStockLevel)($stockLevel, UpdateStockLevelData::from($payload));
        } else {
            $payload['quantity_held'] = $this->quantityHeld;
            $result = (new CreateStockLevel)(CreateStockLevelData::from($payload));
        }

        $this->redirect(route('stock-levels.show', $result->id), navigate: true);
    }

    /**
     * Create one serialised StockLevel per bulk row inside a single transaction.
     */
    private function saveBulk(): void
    {
        $this->validate([
            'productId' => ['required', 'integer', 'exists:products,id'],
            'storeId' => ['required', 'integer', 'exists:stores,id'],
        ]);

        // Defensive: never trust the client — re-run uniqueness server-side.
        $this->revalidateBulkRows();

        // Empty rows (e.g. the trailing Enter row) are ignored; create only
        // the complete rows. bulkCanSubmit() guarantees no partial rows remain
        // and every complete row is unique.
        if (! $this->bulkCanSubmit()) {
            throw ValidationException::withMessages([
                'bulkRows' => 'Every row must have a unique asset number and serial number before you can create them.',
            ]);
        }

        $completeRows = array_filter(
            $this->bulkRows,
            fn (array $row): bool => $this->rowState($row) === 'complete',
        );

        DB::transaction(function () use ($completeRows): void {
            foreach ($completeRows as $row) {
                $asset = trim($row['asset_number']);
                $serial = trim($row['serial_number']);

                (new CreateStockLevel)(CreateStockLevelData::from([
                    'product_id' => $this->productId,
                    'store_id' => $this->storeId,
                    'item_name' => $this->itemName ?: null,
                    'asset_number' => $asset,
                    'serial_number' => $serial,
                    'barcode' => $asset,
                    'quantity_held' => 1,
                ]));
            }
        });

        $this->redirect(route('products.stock', $this->productId), navigate: true);
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        return [
            'isEditing' => $this->stockLevelId !== null,
            'stores' => Store::query()->orderBy('name')->get(['id', 'name']),
        ];
    }

    public function render(): ViewContract
    {
        return view('stock::form', $this->with());
    }
}
