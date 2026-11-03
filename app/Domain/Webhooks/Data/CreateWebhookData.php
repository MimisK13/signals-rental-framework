<?php

declare(strict_types=1);

namespace App\Domain\Webhooks\Data;

use App\Domain\Webhooks\Services\WebhookEventRegistry;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;

final class CreateWebhookData extends Data
{
    /**
     * @param  list<string>  $events
     */
    public function __construct(
        public string $url,
        public array $events,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'url' => ['required', 'url', 'max:2048'],
            'events' => ['required', 'array', 'min:1'],
            'events.*' => ['string', Rule::in([...app(WebhookEventRegistry::class)->names(), '*'])],
        ];
    }
}
