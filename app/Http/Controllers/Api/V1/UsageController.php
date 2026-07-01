<?php

namespace App\Http\Controllers\Api\V1;

use App\Data\Api\ApiUsageData;
use App\Enums\MeteringChannel;
use App\Http\Controllers\Api\Controller;
use App\Jobs\ExportApiUsage;
use App\Models\User;
use App\Queries\Api\UsageQuery;
use App\Support\Api\Attributes\PriceWeight;
use App\Support\DateWindow;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UsageController extends Controller
{
    /**
     * Return grouped API usage aggregates for the requested window.
     *
     * @operationId listUsage
     */
    #[ApiResponse(200, 'Usage aggregates', type: 'array{usage: list<array{key: string, label: string|null, request_count: int, weighted_units: int}>, meta: array{totals: array{request_count: int, weighted_units: int}, window: array{preset: string, from: string, to: string}, dimension: string}}')]
    #[PriceWeight(0)]
    public function index(Request $request, UsageQuery $usageQuery): JsonResponse
    {
        $this->authorizeApi('api.usage.view', 'api-usage:read');

        $validated = $this->validatedFilters($request);
        $window = DateWindow::fromRequest(
            $validated['window'],
            $validated['from'] ?? null,
            $validated['to'] ?? null,
        );

        $channel = isset($validated['channel'])
            ? MeteringChannel::from($validated['channel'])
            : null;

        $result = $usageQuery(
            window: $window,
            dimension: $validated['dimension'],
            channel: $channel,
            tokenId: $validated['token_id'] ?? null,
        );

        $usage = array_map(
            static fn (array $row): array => ApiUsageData::fromRow($row)->toArray(),
            $result->rows,
        );

        return response()->json([
            'usage' => $usage,
            'meta' => [
                'totals' => $result->totals,
                'window' => $window->toMeta(),
                'dimension' => $validated['dimension'],
            ],
        ]);
    }

    /**
     * Export usage aggregates as CSV (async).
     *
     * @operationId exportUsage
     */
    #[PriceWeight(0)]
    public function export(Request $request): JsonResponse
    {
        $this->authorizeApi('api.usage.view', 'api-usage:read');

        $validated = $this->validatedFilters($request);

        /** @var User $user */
        $user = $request->user();

        ExportApiUsage::dispatch($user->id, $validated);

        return $this->respondAccepted();
    }

    /**
     * @return array{
     *     channel?: string,
     *     dimension: string,
     *     window: string,
     *     from?: string,
     *     to?: string,
     *     token_id?: int
     * }
     */
    private function validatedFilters(Request $request): array
    {
        /** @var array{
         *     channel?: string,
         *     dimension?: string,
         *     window?: string,
         *     from?: string,
         *     to?: string,
         *     token_id?: int
         * } $validated
         */
        $validated = $request->validate([
            'channel' => ['nullable', Rule::enum(MeteringChannel::class)],
            'dimension' => ['nullable', 'string', Rule::in(['token', 'endpoint', 'resource', 'day', 'status'])],
            'window' => ['nullable', 'string', Rule::in(['24h', '7d', '30d', 'custom'])],
            'from' => ['required_if:window,custom', 'nullable', 'date'],
            'to' => ['required_if:window,custom', 'nullable', 'date', 'after_or_equal:from'],
            'token_id' => ['nullable', 'integer'],
        ]);

        $validated['dimension'] ??= 'endpoint';
        $validated['window'] ??= '7d';

        return $validated;
    }
}
