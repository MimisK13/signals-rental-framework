<?php

declare(strict_types=1);

namespace App\Domain\Quotations\Presentation\Http\Controllers\Api\V1;

use App\Domain\API\Presentation\Http\Controllers\Controller;
use App\Domain\Quotations\Actions\AcceptQuotationVersion;
use App\Domain\Quotations\Actions\ActivateQuotationVersion;
use App\Domain\Quotations\Actions\CreateQuotationVersion;
use App\Domain\Quotations\Actions\DeclineQuotationVersion;
use App\Domain\Quotations\Actions\SendQuotationVersion;
use App\Domain\Quotations\Data\AcceptQuotationVersionData;
use App\Domain\Quotations\Data\CreateQuotationVersionData;
use App\Domain\Quotations\Data\DeclineQuotationVersionData;
use App\Domain\Quotations\Data\SendQuotationVersionData;
use App\Domain\Quotations\Models\Quotation;
use App\Domain\Quotations\Models\QuotationVersion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class QuotationVersionController extends Controller
{
    public function store(Request $request, Quotation $quotation, CreateQuotationVersion $create): JsonResponse
    {
        $this->authorizeApi('quotations.edit', 'quotations:write');

        return $this->respondWith($create($quotation, CreateQuotationVersionData::validateAndCreate($request->all()))->toArray(), 'version', Response::HTTP_CREATED);
    }

    public function activate(Quotation $quotation, QuotationVersion $version, ActivateQuotationVersion $activate): JsonResponse
    {
        $this->authorizeApi('quotations.edit', 'quotations:write');
        $this->assertVersion($quotation, $version);

        return $this->respondWith($activate($quotation, $version)->toArray(), 'version');
    }

    public function send(Request $request, Quotation $quotation, QuotationVersion $version, SendQuotationVersion $send): JsonResponse
    {
        $this->authorizeApi('quotations.send', 'quotations:write');
        $this->assertVersion($quotation, $version);

        return $this->respondWith($send($quotation, $version, SendQuotationVersionData::validateAndCreate($request->all()))->toArray(), 'version');
    }

    public function accept(Request $request, Quotation $quotation, QuotationVersion $version, AcceptQuotationVersion $accept): JsonResponse
    {
        $this->authorizeApi('quotations.accept', 'quotations:write');
        $this->assertVersion($quotation, $version);

        return $this->respondWith($accept($quotation, $version, AcceptQuotationVersionData::validateAndCreate($request->all()))->toArray(), 'quotation');
    }

    public function decline(Request $request, Quotation $quotation, QuotationVersion $version, DeclineQuotationVersion $decline): JsonResponse
    {
        $this->authorizeApi('quotations.edit', 'quotations:write');
        $this->assertVersion($quotation, $version);

        return $this->respondWith($decline($quotation, $version, DeclineQuotationVersionData::validateAndCreate($request->all()))->toArray(), 'version');
    }

    private function assertVersion(Quotation $quotation, QuotationVersion $version): void
    {
        abort_unless($version->quotation_id === $quotation->id, Response::HTTP_NOT_FOUND);
    }
}
