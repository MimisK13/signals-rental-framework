<?php

declare(strict_types=1);

namespace App\Domain\Warehouse\Containers\Data;

use Illuminate\Validation\Rule;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;

final class UnsealContainerData extends Data
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
