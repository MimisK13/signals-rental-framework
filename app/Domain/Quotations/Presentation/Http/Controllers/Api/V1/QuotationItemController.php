<?php

declare(strict_types=1);

namespace App\Domain\Quotations\Presentation\Http\Controllers\Api\V1;

use App\Domain\Quotations\Actions\AddQuotationItem;
use App\Domain\Quotations\Actions\RemoveQuotationItem;
use App\Domain\Quotations\Actions\RestructureQuotationItems;
use App\Domain\Quotations\Actions\UpdateQuotationItem;
use App\Domain\Quotations\Data\AddQuotationItemData;
use App\Domain\Quotations\Data\RestructureQuotationItemsData;
use App\Domain\Quotations\Data\UpdateQuotationItemData;
use App\Domain\Quotations\Models\Quotation;
use App\Domain\Quotations\Models\QuotationItem;
use App\Domain\Quotations\Models\QuotationVersion;
use App\Http\Controllers\Api\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class QuotationItemController extends Controller
{
    public function store(Request $request, Quotation $quotation, QuotationVersion $version, AddQuotationItem $add): JsonResponse
    {
        $this->authorizeApi('quotations.edit', 'quotations:write');
        $this->assertVersion($quotation, $version);

        return $this->respondWith($add($quotation, $version, AddQuotationItemData::validateAndCreate($request->all()))->toArray(), 'item', Response::HTTP_CREATED);
    }

    public function update(Request $request, Quotation $quotation, QuotationVersion $version, QuotationItem $item, UpdateQuotationItem $update): JsonResponse
    {
        $this->authorizeApi('quotations.edit', 'quotations:write');
        $this->assertItem($quotation, $version, $item);

        return $this->respondWith($update($item, UpdateQuotationItemData::validateAndCreate($request->all()))->toArray(), 'item');
    }

    public function destroy(Quotation $quotation, QuotationVersion $version, QuotationItem $item, RemoveQuotationItem $remove): JsonResponse
    {
        $this->authorizeApi('quotations.edit', 'quotations:write');
        $this->assertItem($quotation, $version, $item);
        $remove($item);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    public function restructure(Request $request, Quotation $quotation, QuotationVersion $version, RestructureQuotationItems $restructure): JsonResponse
    {
        $this->authorizeApi('quotations.edit', 'quotations:write');
        $this->assertVersion($quotation, $version);
        $items = $restructure($quotation, $version, RestructureQuotationItemsData::validateAndCreate($request->all()));

        return $this->respondWithCollection(array_map(static fn ($item): array => $item->toArray(), $items), 'items');
    }

    private function assertVersion(Quotation $quotation, QuotationVersion $version): void
    {
        abort_unless($version->quotation_id === $quotation->id, Response::HTTP_NOT_FOUND);
    }

    private function assertItem(Quotation $quotation, QuotationVersion $version, QuotationItem $item): void
    {
        abort_unless($version->quotation_id === $quotation->id && $item->quotation_id === $quotation->id && $item->version_id === $version->id, Response::HTTP_NOT_FOUND);
    }
}
