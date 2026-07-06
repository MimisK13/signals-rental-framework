<?php

namespace App\Data\Containers;

use Illuminate\Validation\Rule;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;

class UnsealContainerData extends Data
{
    public function __construct(
        #[Required]
        public string $reason,
    ) {}

    /**
     * @return array<string, list<mixed>>
     */
    public static function rules(): array
    {
        return [
            'reason' => ['required', 'string', Rule::in(config('containers.unseal_reasons', []))],
        ];
    }
}
