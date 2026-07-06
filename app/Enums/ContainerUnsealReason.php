<?php

namespace App\Enums;

/**
 * Why a sealed container was reopened (serialised-containers.md §"Container
 * Lifecycle" — unsealing returns the container to open for modifications).
 *
 * Values are config-driven via {@see config('containers.unseal_reasons')}; this
 * enum mirrors the canonical set for type-safe internal use.
 */
enum ContainerUnsealReason: string
{
    case Manual = 'manual';
    case TransferTriggered = 'transfer_triggered';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'Manual',
            self::TransferTriggered => 'Transfer triggered',
        };
    }

    /**
     * @return list<string>
     */
    public static function configValues(): array
    {
        /** @var list<string> $reasons */
        $reasons = config('containers.unseal_reasons', []);

        return $reasons;
    }
}
