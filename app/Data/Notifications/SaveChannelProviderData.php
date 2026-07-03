<?php

namespace App\Data\Notifications;

use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;

class SaveChannelProviderData extends Data
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        #[Required, Max(50)]
        public string $channel,
        #[Required, Max(50)]
        public string $driver,
        #[Required, Max(255)]
        public string $name,
        public array $config = [],
        public bool $is_default = true,
        public bool $is_active = true,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'channel' => ['required', 'string', 'max:50'],
            'driver' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:255'],
            'config' => ['array'],
            'is_default' => ['boolean'],
            'is_active' => ['boolean'],
        ];
    }
}
