<?php

declare(strict_types=1);

namespace App\Domain\Warehouse\Containers\Enums;

/**
 * Why a container was dissolved (serialised-containers.md §Containers Table).
 */
enum ContainerDissolveReason: string
{
    case Dispatched = 'dispatched';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::Dispatched => 'Dispatched',
            self::Manual => 'Manual',
        };
    }

    /**
     * @return list<string>
     */
    public static function configValues(): array
    {
        /** @var list<string> $reasons */
        $reasons = config('containers.dissolve_reasons', []);

        return $reasons;
    }
}
