<?php

namespace App\Data\Notifications;

use App\Services\Notifications\ChannelProviderRegistry;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Attributes\Validation\Email;
use Spatie\LaravelData\Data;

/**
 * Input for self-testing a channel provider (P23).
 */
class TestChannelProviderData extends Data
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        public string $channel,
        public ?string $driverKey = null,
        #[Email]
        public ?string $recipient = null,
        public array $config = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        $registry = app(ChannelProviderRegistry::class);

        return [
            'channel' => ['required', 'string', Rule::in($registry->channels())],
            'driverKey' => ['nullable', 'string', Rule::in($registry->keys())],
            'recipient' => ['nullable', 'email'],
            'config' => ['array'],
        ];
    }
}
