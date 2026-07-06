<?php

namespace App\Http\Middleware;

use App\Models\ApiIdempotencyKey;
use App\Services\Api\IdempotencyKeyService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class HandleIdempotencyKey
{
    public function __construct(
        private readonly IdempotencyKeyService $idempotencyKeys,
    ) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $idempotencyKey = $request->header('Idempotency-Key');

        if (! is_string($idempotencyKey) || $idempotencyKey === '') {
            return $next($request);
        }

        if (strlen($idempotencyKey) > 255) {
            return response()->json([
                'message' => 'The Idempotency-Key header must not exceed 255 characters.',
                'errors' => [
                    'Idempotency-Key' => ['The Idempotency-Key header must not exceed 255 characters.'],
                ],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $tokenId = $this->resolveTokenId($request);

        if ($tokenId === null) {
            return $next($request);
        }

        $fingerprint = $this->idempotencyKeys->fingerprint($request);
        $claim = $this->idempotencyKeys->claim($idempotencyKey, $tokenId, $fingerprint);

        return match ($claim['outcome']) {
            'conflict' => $this->conflictResponse(),
            'in_flight' => $this->inFlightResponse(),
            'replay' => $this->replayResponse($claim['record']),
            'process' => $this->processRequest($request, $next, $claim['record']),
        };
    }

    /**
     * @param  Closure(Request): (Response)  $next
     */
    private function processRequest(Request $request, Closure $next, ApiIdempotencyKey $record): Response
    {
        try {
            $response = $next($request);
        } catch (ValidationException $exception) {
            $response = response()->json([
                'message' => $exception->getMessage(),
                'errors' => $exception->errors(),
            ], $exception->status);
        }

        if ($this->idempotencyKeys->shouldPersist($response->getStatusCode())) {
            $this->idempotencyKeys->persistResponse($record, $response);
        } else {
            $this->idempotencyKeys->releaseClaim($record);
        }

        return $response;
    }

    private function replayResponse(ApiIdempotencyKey $record): JsonResponse
    {
        return response()->json(
            $record->response_body ?? [],
            $record->response_status ?? Response::HTTP_OK,
            [IdempotencyKeyService::REPLAY_HEADER => 'true'],
        );
    }

    private function conflictResponse(): JsonResponse
    {
        return response()->json([
            'message' => 'The Idempotency-Key was already used with a different request payload.',
            'errors' => [
                'Idempotency-Key' => ['The Idempotency-Key was already used with a different request payload.'],
            ],
        ], Response::HTTP_CONFLICT);
    }

    private function inFlightResponse(): JsonResponse
    {
        return response()->json([
            'message' => 'A request with this Idempotency-Key is still being processed. Retry after a short delay.',
        ], Response::HTTP_TOO_EARLY);
    }

    private function resolveTokenId(Request $request): ?int
    {
        $token = $request->user()?->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            return $token->id;
        }

        $bearerToken = $request->bearerToken();

        if (! is_string($bearerToken) || $bearerToken === '') {
            return null;
        }

        $resolved = PersonalAccessToken::findToken($bearerToken);

        return $resolved instanceof PersonalAccessToken ? $resolved->id : null;
    }
}
