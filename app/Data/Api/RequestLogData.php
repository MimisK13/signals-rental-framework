<?php

namespace App\Data\Api;

use App\Models\RequestLog;
use Illuminate\Support\Carbon;
use Spatie\LaravelData\Data;

class RequestLogData extends Data
{
    public function __construct(
        public int $id,
        public string $channel,
        public string $request_id,
        public string $method,
        public string $uri,
        public ?string $route_name,
        public ?string $resource,
        public ?string $operation,
        public ?int $personal_access_token_id,
        public ?int $user_id,
        public ?string $ability,
        public int $status_code,
        public int $duration_ms,
        public int $request_bytes,
        public int $response_bytes,
        public int $price_weight,
        public int $weighted_units,
        public ?string $ip_address,
        public ?string $user_agent,
        public ?string $created_at,
    ) {}

    public static function fromModel(RequestLog $log): self
    {
        /** @var Carbon|null $createdAt */
        $createdAt = $log->created_at;

        return new self(
            id: $log->id,
            channel: $log->channel,
            request_id: $log->request_id,
            method: $log->method,
            uri: $log->uri,
            route_name: $log->route_name,
            resource: $log->resource,
            operation: $log->operation,
            personal_access_token_id: $log->personal_access_token_id,
            user_id: $log->user_id,
            ability: $log->ability,
            status_code: $log->status_code,
            duration_ms: $log->duration_ms,
            request_bytes: $log->request_bytes,
            response_bytes: $log->response_bytes,
            price_weight: $log->price_weight,
            weighted_units: $log->weighted_units,
            ip_address: $log->ip_address,
            user_agent: $log->user_agent,
            created_at: $createdAt?->toIso8601String(),
        );
    }
}
