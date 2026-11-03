<?php

declare(strict_types=1);

namespace App\Domain\Communications\Presentation\Http\Controllers\Api\V1;

use App\Domain\API\Presentation\Http\Concerns\FiltersQueries;
use App\Domain\API\Presentation\Http\Controllers\Controller;
use App\Domain\API\Support\Attributes\PriceWeight;
use App\Domain\Communications\Data\CommunicationLogData;
use App\Domain\Communications\Models\CommunicationLog;
use App\Domain\Communications\Services\CommunicationLogQuery;
use App\Models\User;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class CommunicationLogController extends Controller
{
    use FiltersQueries;

    /** @var list<string> */
    protected array $allowedFilters = [
        'notification_type_key',
        'channel',
        'provider_id',
        'template_id',
        'notifiable_type',
        'notifiable_id',
        'subject_type',
        'subject_id',
        'status',
        'is_pinned',
        'queued_at',
        'sent_at',
        'delivered_at',
        'opened_at',
        'created_at',
        'updated_at',
    ];

    /** @var list<string> */
    protected array $allowedSorts = [
        'channel',
        'status',
        'queued_at',
        'sent_at',
        'delivered_at',
        'opened_at',
        'created_at',
        'updated_at',
    ];

    /**
     * List communication log entries with Ransack filtering and pagination.
     *
     * @response array{communication_logs: list<CommunicationLogData>, meta: array{total: int, per_page: int, page: int}}
     */
    #[ApiResponse(200, 'Paginated communication log collection', type: 'array{communication_logs: list<array<string, mixed>>, meta: array{total: int, per_page: int, page: int}}')]
    #[PriceWeight(0)]
    public function index(Request $request, CommunicationLogQuery $communicationLogQuery): JsonResponse
    {
        $this->authorizeApi('notifications.manage', 'communications:read');

        /** @var User $user */
        $user = $request->user();

        $query = $communicationLogQuery->forUser($user);
        $query = $this->applyFilters($query, $request);
        $query = $this->applySort($query, $request);

        if (! $request->has('sort')) {
            $query->latest('id');
        }

        $paginator = $this->paginateQuery($query, $request);

        $items = $paginator->getCollection()
            ->map(fn (CommunicationLog $log): array => CommunicationLogData::fromModel($log)->toArray())
            ->all();

        return $this->respondWithCollection($items, 'communication_logs', $paginator);
    }

    /**
     * Show a single communication log entry.
     *
     * @response array{communication_log: CommunicationLogData}
     */
    #[ApiResponse(200, 'Communication log resource', type: 'array{communication_log: array<string, mixed>}')]
    #[PriceWeight(0)]
    public function show(Request $request, int $communicationLog, CommunicationLogQuery $communicationLogQuery): JsonResponse
    {
        $this->authorizeApi('notifications.manage', 'communications:read');

        /** @var User $user */
        $user = $request->user();

        $log = $communicationLogQuery->forUser($user)
            ->whereKey($communicationLog)
            ->firstOrFail();

        Gate::authorize('view', $log);

        return $this->respondWith(
            CommunicationLogData::fromModel($log)->toArray(),
            'communication_log',
        );
    }
}
