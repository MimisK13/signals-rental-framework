<?php

declare(strict_types=1);

namespace App\Domain\Webhooks\Data;

use App\Domain\Webhooks\Services\WebhookEventRegistry;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;

final class UpdateWebhookData extends Data
{
    /**
     * @param  list<string>|null  $events
     */
    public function __construct(
        public ?string $url = null,
        public ?array $events = null,
        public ?bool $is_active = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'url' => ['sometimes', 'url', 'max:2048'],
            'events' => ['sometimes', 'array', 'min:1'],
            'events.*' => ['string', Rule::in([...app(WebhookEventRegistry::class)->names(), '*'])],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
