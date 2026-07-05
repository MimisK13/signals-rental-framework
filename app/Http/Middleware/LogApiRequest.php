<?php

namespace App\Http\Middleware;

use App\Enums\MeteringChannel;
use App\Services\Api\PriceWeightResolver;
use App\Services\Api\UsageRecorder;
use App\Support\Api\MeteringBodyRedactor;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class LogApiRequest
{
    private const START_ATTRIBUTE = 'api_metering.start';

    public function __construct(
        private readonly UsageRecorder $usageRecorder,
        private readonly PriceWeightResolver $priceWeightResolver,
        private readonly MeteringBodyRedactor $bodyRedactor,
    ) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $request->attributes->set(self::START_ATTRIBUTE, microtime(true));

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        if (! config('api_metering.logging_enabled')) {
            return;
        }

        try {
            $routeName = $request->route()?->getName();
            [$resource, $operation] = is_string($routeName) ? $this->parseRouteName($routeName) : [null, null];
            $priceWeight = is_string($routeName) ? $this->priceWeightResolver->get($routeName) : 1;
            $token = $request->user()?->currentAccessToken();
            $tokenId = $token instanceof PersonalAccessToken ? $token->id : null;
            $captureBodies = (bool) config('api_metering.capture_bodies');
            $maxBytes = (int) config('api_metering.body_max_bytes');
            $redactionKeys = config('api_metering.redaction', []);

            $this->usageRecorder->record([
                'channel' => MeteringChannel::Api->value,
                'request_id' => $this->resolveRequestId($request),
                'method' => $request->method(),
                'uri' => '/'.ltrim($request->path(), '/'),
                'route_name' => $routeName,
                'resource' => $resource,
                'operation' => $operation,
                'rollup_key' => $routeName,
                'personal_access_token_id' => $tokenId,
                'user_id' => $request->user()?->id,
                'ability' => $this->resolveAbility($token),
                'status_code' => $response->getStatusCode(),
                'duration_ms' => $this->resolveDurationMs($request),
                'request_bytes' => strlen($request->getContent()),
                'response_bytes' => strlen($response->getContent()),
                'price_weight' => $priceWeight,
                'weighted_units' => $priceWeight,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'request_body' => $captureBodies
                    ? $this->bodyRedactor->redactAndTruncate($request->getContent(), $redactionKeys, $maxBytes)
                    : null,
                'response_body' => $captureBodies
                    ? $this->bodyRedactor->redactAndTruncate($response->getContent(), $redactionKeys, $maxBytes)
                    : null,
                'recorded_at' => now(),
            ]);
        } catch (\Throwable $exception) {
            $this->usageRecorder->reportFailure($exception);
        }
    }

    private function resolveRequestId(Request $request): string
    {
        $header = $request->header('X-Request-Id');

        if (is_string($header) && $header !== '') {
            return $header;
        }

        return (string) Str::uuid();
    }

    /**
     * Cheaply derive [resource, operation] from an `api.v1.<resource>.<operation>` route name
     * without building the (Scramble-backed) endpoint catalogue in the request hot path.
     *
     * @return array{0: string|null, 1: string|null}
     */
    private function parseRouteName(string $routeName): array
    {
        $segments = explode('.', $routeName);

        if (count($segments) < 4) {
            return [null, null];
        }

        return [$segments[2], $segments[3]];
    }

    private function resolveAbility(mixed $token): ?string
    {
        if ($token instanceof PersonalAccessToken) {
            $abilities = $token->abilities ?? [];

            if ($abilities !== [] && $abilities !== ['*']) {
                return $abilities[0];
            }
        }

        return null;
    }

    private function resolveDurationMs(Request $request): int
    {
        $startedAt = $request->attributes->get(self::START_ATTRIBUTE);

        if (is_float($startedAt)) {
            return (int) round((microtime(true) - $startedAt) * 1000);
        }

        if (defined('LARAVEL_START')) {
            return (int) round((microtime(true) - LARAVEL_START) * 1000);
        }

        return 0;
    }
}
