<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Notifications\DeleteChannelProvider;
use App\Actions\Notifications\SaveChannelProvider;
use App\Actions\Notifications\TestChannelProvider;
use App\Data\Notifications\ChannelProviderData;
use App\Data\Notifications\ChannelTestResultData;
use App\Data\Notifications\SaveChannelProviderData;
use App\Data\Notifications\TestChannelProviderData;
use App\Http\Controllers\Api\Controller;
use App\Http\Traits\FiltersQueries;
use App\Models\ChannelProvider;
use App\Services\Notifications\ChannelProviderConfigService;
use App\Services\Notifications\ChannelProviderRegistry;
use App\Support\Api\Attributes\PriceWeight;
use Dedoc\Scramble\Attributes\Response as ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ChannelProviderController extends Controller
{
    use FiltersQueries;

    /** @var list<string> */
    protected array $allowedFilters = [
        'channel',
        'driver',
        'name',
        'is_default',
        'is_active',
        'last_test_status',
        'created_at',
        'updated_at',
    ];

    /** @var array<string, string> */
    protected array $filterAliases = [
        'active' => 'is_active',
        'default' => 'is_default',
    ];

    /** @var list<string> */
    protected array $allowedSorts = [
        'channel',
        'driver',
        'name',
        'last_test_status',
        'last_tested_at',
        'created_at',
        'updated_at',
    ];

    /**
     * List channel providers with Ransack filtering and pagination.
     *
     * Provider config is always returned masked — secrets never appear in responses.
     *
     * @response array{channel_providers: list<ChannelProviderData>, meta: array{total: int, per_page: int, page: int}}
     */
    #[ApiResponse(200, 'Paginated channel provider collection', type: 'array{channel_providers: list<array<string, mixed>>, meta: array{total: int, per_page: int, page: int}}')]
    #[PriceWeight(0)]
    public function index(Request $request): JsonResponse
    {
        $this->authorizeApi('notifications.manage', 'channel-providers:read');

        $query = ChannelProvider::query();
        $query = $this->applyFilters($query, $request);
        $query = $this->applySort($query, $request);

        if (! $request->has('sort')) {
            $query->orderBy('channel')->orderBy('name');
        }

        $paginator = $this->paginateQuery($query, $request);

        $items = $paginator->getCollection()
            ->map(fn (ChannelProvider $provider): array => ChannelProviderData::fromModel($provider)->toArray())
            ->all();

        return $this->respondWithCollection($items, 'channel_providers', $paginator);
    }

    /**
     * Show a channel provider.
     *
     * @response array{channel_provider: ChannelProviderData}
     */
    #[ApiResponse(200, 'Channel provider resource', type: 'array{channel_provider: array<string, mixed>}')]
    #[PriceWeight(0)]
    public function show(ChannelProvider $channelProvider): JsonResponse
    {
        $this->authorizeApi('notifications.manage', 'channel-providers:read');

        return $this->respondWith(
            ChannelProviderData::fromModel($channelProvider)->toArray(),
            'channel_provider',
        );
    }

    /**
     * Create a channel provider.
     *
     * @response 201 array{channel_provider: ChannelProviderData}
     */
    #[ApiResponse(201, 'Created channel provider', type: 'array{channel_provider: array<string, mixed>}')]
    #[PriceWeight(1)]
    public function store(Request $request): JsonResponse
    {
        $this->authorizeApi('notifications.manage', 'channel-providers:write');

        $validated = $request->validate(SaveChannelProviderData::rules());
        $dto = SaveChannelProviderData::from($validated);

        $provider = app(SaveChannelProvider::class)(null, $dto);

        return $this->respondWith(
            ChannelProviderData::fromModel($provider)->toArray(),
            'channel_provider',
            Response::HTTP_CREATED,
        );
    }

    /**
     * Update a channel provider.
     *
     * Supports masked-value round-trip: submit masked placeholders for unchanged secrets.
     *
     * @response array{channel_provider: ChannelProviderData}
     */
    #[ApiResponse(200, 'Updated channel provider', type: 'array{channel_provider: array<string, mixed>}')]
    #[PriceWeight(1)]
    public function update(Request $request, ChannelProvider $channelProvider): JsonResponse
    {
        $this->authorizeApi('notifications.manage', 'channel-providers:write');

        $validated = $request->validate(SaveChannelProviderData::rules());
        $dto = SaveChannelProviderData::from([
            ...$validated,
            'channel' => $validated['channel'] ?? $channelProvider->channel,
            'driver' => $validated['driver'] ?? $channelProvider->driver,
        ]);

        $provider = app(SaveChannelProvider::class)($channelProvider, $dto);

        return $this->respondWith(
            ChannelProviderData::fromModel($provider)->toArray(),
            'channel_provider',
        );
    }

    /**
     * Delete a channel provider.
     */
    #[ApiResponse(204, 'Channel provider deleted')]
    #[PriceWeight(1)]
    public function destroy(ChannelProvider $channelProvider): JsonResponse
    {
        $this->authorizeApi('notifications.manage', 'channel-providers:write');

        (new DeleteChannelProvider)($channelProvider);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Self-test a channel provider.
     *
     * @response array{channel_test_result: ChannelTestResultData}
     */
    #[ApiResponse(200, 'Channel provider test result', type: 'array{channel_test_result: array{success: bool, message: string, driverKey: string, channel: string}}')]
    #[PriceWeight(1)]
    public function test(
        Request $request,
        ChannelProvider $channelProvider,
        ChannelProviderConfigService $configService,
    ): JsonResponse {
        $this->authorizeApi('notifications.manage', 'channel-providers:write');

        $validated = $request->validate([
            'recipient' => ['nullable', 'email'],
            'config' => ['array'],
        ]);

        $driver = app(ChannelProviderRegistry::class)->resolve(driverKey: $channelProvider->driver);
        $incomingConfig = is_array($validated['config'] ?? null) ? $validated['config'] : [];
        $config = $configService->mergeConfig($driver, $incomingConfig, $channelProvider);

        $result = app(TestChannelProvider::class)(new TestChannelProviderData(
            channel: $channelProvider->channel,
            driverKey: $channelProvider->driver,
            recipient: $validated['recipient'] ?? null,
            config: $config,
        ));

        if ($channelProvider->exists) {
            $channelProvider->update([
                'last_test_status' => $result->success ? 'passed' : 'failed',
                'last_test_message' => $result->message,
                'last_tested_at' => now(),
            ]);
        }

        return $this->respondWith($result->toArray(), 'channel_test_result');
    }
}
