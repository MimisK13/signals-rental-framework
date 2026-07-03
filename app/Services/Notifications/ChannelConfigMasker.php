<?php

namespace App\Services\Notifications;

use App\Data\Notifications\ChannelConfigFieldData;

/**
 * Masks secret credential values before they appear in DTO/API responses (P23).
 */
class ChannelConfigMasker
{
    /**
     * @param  array<string, mixed>  $config
     * @param  list<ChannelConfigFieldData>  $schema
     * @return array<string, mixed>
     */
    public static function mask(array $config, array $schema): array
    {
        $masked = $config;

        foreach ($schema as $field) {
            if (! $field->secret) {
                continue;
            }

            $value = $masked[$field->key] ?? null;

            if (! is_string($value) || $value === '') {
                continue;
            }

            $masked[$field->key] = self::maskValue($value);
        }

        return $masked;
    }

    public static function maskValue(string $value): string
    {
        $length = strlen($value);

        if ($length <= 4) {
            return str_repeat('•', 8);
        }

        $middleLength = min($length - 4, 12);

        return substr($value, 0, 2).str_repeat('•', $middleLength).substr($value, -2);
    }
}
