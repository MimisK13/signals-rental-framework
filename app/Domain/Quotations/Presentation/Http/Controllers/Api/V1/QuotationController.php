<?php

declare(strict_types=1);

namespace App\Domain\Quotations\Presentation\Http\Controllers\Api\V1;

use App\Domain\API\Presentation\Http\Controllers\Controller;
use App\Domain\Quotations\Actions\ClearQuotationDealPrice;
use App\Domain\Quotations\Actions\CreateQuotation;
use App\Domain\Quotations\Actions\SetQuotationDealPrice;
use App\Domain\Quotations\Data\CreateQuotationData;
use App\Domain\Quotations\Data\QuotationData;
use App\Domain\Quotations\Data\SetQuotationDealPriceData;
use App\Domain\Quotations\Models\Quotation;
use App\Domain\Quotations\Models\QuotationVersion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class QuotationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorizeApi('quotations.view', 'quotations:read');
        $query = Quotation::query()->latest('id');
        $paginator = $query->paginate(min(max($request->integer('per_page', 25), 1), 100));

        return $this->respondWithCollection(
            $paginator->getCollection()->map(fn (Quotation $quotation): array => QuotationData::fromModel($quotation)->toArray()),
            'quotations',
            $paginator,
        );
    }

    public function show(Quotation $quotation): JsonResponse
    {
        $this->authorizeApi('quotations.view', 'quotations:read');
        abort_unless(request()->user()?->can('view', $quotation), Response::HTTP_FORBIDDEN);

        return $this->respondWith(QuotationData::fromModel($quotation)->toArray(), 'quotation');
    }

    public function store(Request $request, CreateQuotation $create): JsonResponse
    {
        $this->authorizeApi('quotations.create', 'quotations:write');
        $quotation = $create(CreateQuotationData::validateAndCreate($request->all()));

        return $this->respondWith($quotation->toArray(), 'quotation', Response::HTTP_CREATED);
    }

    public function setDealPrice(Request $request, Quotation $quotation, QuotationVersion $version, SetQuotationDealPrice $set): JsonResponse
    {
        $this->authorizeApi('quotations.edit', 'quotations:write');
        $this->assertVersion($quotation, $version);
        $result = $set($quotation, $version, SetQuotationDealPriceData::validateAndCreate($request->all()));

        return $this->respondWith(QuotationData::fromModel($result)->toArray(), 'quotation');
    }

    public function clearDealPrice(Quotation $quotation, QuotationVersion $version, ClearQuotationDealPrice $clear): JsonResponse
    {
        $this->authorizeApi('quotations.edit', 'quotations:write');
        $this->assertVersion($quotation, $version);

        return $this->respondWith(QuotationData::fromModel($clear($quotation, $version))->toArray(), 'quotation');
    }

    private function assertVersion(Quotation $quotation, QuotationVersion $version): void
    {
        abort_unless($version->quotation_id === $quotation->id, Response::HTTP_NOT_FOUND);
    }
}
