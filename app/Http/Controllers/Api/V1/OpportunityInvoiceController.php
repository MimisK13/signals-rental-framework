<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Invoices\GenerateDepositInvoice;
use App\Actions\Invoices\GenerateInvoiceFromOpportunity;
use App\Actions\Invoices\GenerateProformaInvoice;
use App\Data\Invoices\GenerateDepositInvoiceData;
use App\Data\Invoices\GenerateInvoiceFromOpportunityData;
use App\Data\Invoices\GenerateProformaInvoiceData;
use App\Enums\InvoiceType;
use App\Http\Controllers\Api\Controller;
use App\Models\Opportunity;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * Checkout endpoint: generate invoices from an opportunity.
 */
class OpportunityInvoiceController extends Controller
{
    /**
     * Generate a standard, deposit, or pro-forma invoice from an opportunity.
     */
    #[ApiResponse(201, 'Generated invoice', type: 'array{invoice: array<string, mixed>}')]
    public function store(Request $request, Opportunity $opportunity): JsonResponse
    {
        $this->authorizeApi('invoices.create', 'invoices:write');
        Gate::authorize('view', $opportunity);

        $type = InvoiceType::tryFrom((string) $request->input('type', InvoiceType::Standard->value))
            ?? InvoiceType::Standard;

        $payload = [
            ...$request->all(),
            'opportunity_id' => $opportunity->id,
        ];

        $result = match ($type) {
            InvoiceType::Standard => (new GenerateInvoiceFromOpportunity)(
                GenerateInvoiceFromOpportunityData::validateAndCreate($payload),
            ),
            InvoiceType::Deposit => (new GenerateDepositInvoice)(
                GenerateDepositInvoiceData::validateAndCreate($payload),
            ),
            InvoiceType::Proforma => (new GenerateProformaInvoice)(
                GenerateProformaInvoiceData::validateAndCreate($payload),
            ),
        };

        return $this->respondWith(
            $result->toArray(),
            'invoice',
            Response::HTTP_CREATED,
        );
    }
}
