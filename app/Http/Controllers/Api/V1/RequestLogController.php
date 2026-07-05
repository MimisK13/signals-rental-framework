<?php

namespace App\Http\Controllers\Api\V1;

use App\Data\Api\RequestLogData;
use App\Http\Controllers\Api\Controller;
use App\Http\Traits\FiltersQueries;
use App\Models\RequestLog;
use App\Support\Api\Attributes\PriceWeight;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RequestLogController extends Controller
{
    use FiltersQueries;

    /** @var list<string> */
    protected array $allowedFilters = [
        'route_name',
        'status_code',
        'personal_access_token_id',
        'channel',
        'created_at',
    ];

    /** @var list<string> */
    protected array $allowedSorts = [
        'created_at',
        'status_code',
        'route_name',
    ];

    /**
     * List request log entries with optional filtering and pagination.
     *
     * @operationId listRequestLogs
     */
    #[ApiResponse(200, 'Paginated request log entries', type: 'array{request_logs: list<array{id: int, channel: string, request_id: string, method: string, uri: string, route_name: string|null, resource: string|null, operation: string|null, personal_access_token_id: int|null, user_id: int|null, ability: string|null, status_code: int, duration_ms: int, request_bytes: int, response_bytes: int, price_weight: int, weighted_units: int, ip_address: string|null, user_agent: string|null, created_at: string|null}>, meta: array{total: int, per_page: int, page: int}}')]
    #[PriceWeight(0)]
    public function index(Request $request): JsonResponse
    {
        $this->authorizeApi('api.logs.view', 'api-log:read');

        $query = RequestLog::query();
        $query = $this->applyFilters($query, $request);
        $query = $this->applySort($query, $request);

        if (! $request->has('sort')) {
            $query->latest();
        }

        $paginator = $this->paginateQuery($query, $request);

        $requestLogs = $paginator->getCollection()->map(
            fn (RequestLog $log): array => RequestLogData::fromModel($log)->toArray()
        )->all();

        return $this->respondWithCollection($requestLogs, 'request_logs', $paginator);
    }
}
