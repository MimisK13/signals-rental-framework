<?php

declare(strict_types=1);

namespace App\Domain\Quotations\Actions;

use App\Concerns\CommitsVerbsEvents;
use App\Domain\Quotations\Data\QuotationItemData;
use App\Domain\Quotations\Data\RestructureQuotationItemsData;
use App\Domain\Quotations\Models\Quotation;
use App\Domain\Quotations\Models\QuotationItem;
use App\Domain\Quotations\Models\QuotationVersion;
use App\Domain\Quotations\Services\QuotationEditingGuard;
use App\Domain\Quotations\Services\QuotationItemPathService;
use App\Domain\Quotations\Verbs\Events\QuotationItemPathChanged;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final class RestructureQuotationItems
{
    use CommitsVerbsEvents;

    public function __construct(
        private readonly QuotationEditingGuard $editingGuard,
        private readonly QuotationItemPathService $pathService,
    ) {}

    /** @return list<QuotationItemData> */
    public function __invoke(Quotation $quotation, QuotationVersion $version, RestructureQuotationItemsData $data): array
    {
        Gate::authorize('update', $quotation);
        $this->editingGuard->assertEditable($quotation, $version);
        $items = $version->items()->get()->keyBy('id');
        $nodeIds = array_map(static fn (array $node): int => (int) $node['id'], $data->nodes);

        if (count($nodeIds) !== count(array_unique($nodeIds)) || count($nodeIds) !== $items->count()) {
            throw ValidationException::withMessages(['nodes' => 'The tree must include every version item exactly once.']);
        }

        foreach ($nodeIds as $id) {
            if (! $items->has($id)) {
                throw ValidationException::withMessages(['nodes' => 'One or more items do not belong to this quotation version.']);
            }
        }

        $validationNodes = array_map(function (array $node) use ($items): array {
            /** @var QuotationItem $item */
            $item = $items->get((int) $node['id']);

            return ['id' => (int) $node['id'], 'depth' => (int) $node['depth'], 'item_type' => $item->item_type->value];
        }, $data->nodes);

        try {
            $this->pathService->validatePlacement($validationNodes);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['nodes' => $exception->getMessage()]);
        }

        $paths = $this->pathService->rebuild($data->nodes);
        $this->commitVerbs(function () use ($quotation, $items, $nodeIds, $paths): void {
            foreach ($nodeIds as $id) {
                /** @var QuotationItem $item */
                $item = $items->get($id);

                if ($item->path === $paths[$id]) {
                    continue;
                }

                QuotationItemPathChanged::fire(
                    item_id: $item->state_id,
                    quotation_id: $quotation->state_id,
                    path: $paths[$id],
                );
            }
        });

        return $version->items()->orderBy('path')->get()
            ->map(fn (QuotationItem $item): QuotationItemData => QuotationItemData::fromModel($item))
            ->all();
    }
}
