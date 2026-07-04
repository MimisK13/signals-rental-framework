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
use Dedoc\Scramble\Attributes\HeaderParameter;
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
     *
     * Supports optional idempotent checkout via the `Idempotency-Key` header.
     * When present, the first successful or validation-failed (422) response is
     * stored and replayed verbatim on subsequent requests with the same key and
     * payload fingerprint. Replays include the `Idempotency-Replayed: true`
     * response header. Reusing the key with a different payload returns 409.
     * Concurrent requests with the same new key return 425 while the first is
     * still processing. Keys are scoped to the bearer token.
     */
    #[HeaderParameter(
        'Idempotency-Key',
        'Optional client-generated key for safe request retries. Scoped to the bearer token; replays stored 2xx/422 responses for up to 24 hours.',
        required: false,
        type: 'string',
    )]
    #[ApiResponse(201, 'Generated invoice', type: 'array{invoice: array<string, mixed>}')]
    #[ApiResponse(409, 'Idempotency key reused with a different payload', type: 'array{message: string, errors: array<string, list<string>>}')]
    #[ApiResponse(425, 'Original request with this key is still processing', type: 'array{message: string}')]
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
