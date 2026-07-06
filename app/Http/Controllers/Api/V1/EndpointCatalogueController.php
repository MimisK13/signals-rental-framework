<?php

namespace App\Http\Controllers\Api\V1;

use App\Data\Api\ApiEndpointData;
use App\Http\Controllers\Api\Controller;
use App\Services\Api\ApiEndpointCatalogue;
use App\Support\Api\Attributes\PriceWeight;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\JsonResponse;

class EndpointCatalogueController extends Controller
{
    /**
     * List all registered API endpoints with metering metadata.
     *
     * @operationId listEndpoints
     */
    #[ApiResponse(200, 'API endpoint catalogue', type: 'array{endpoints: list<array{name: string, method: string, uri: string, resource: string|null, operation: string|null, ability: string|null, price_weight: int}>}')]
    #[PriceWeight(0)]
    public function index(ApiEndpointCatalogue $catalogue): JsonResponse
    {
        $this->authorizeApi('api.access', 'system:read');

        $endpoints = array_map(
            static fn (array $entry): array => ApiEndpointData::fromCatalogueEntry($entry)->toArray(),
            $catalogue->all(),
        );

        return response()->json([
            'endpoints' => $endpoints,
        ]);
    }
}
