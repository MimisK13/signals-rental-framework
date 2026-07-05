<?php

namespace App\Enums;

use App\Support\BackedEnumHelper;

enum TaxBuyerType: string
{
    case Business = 'business';
    case Individual = 'individual';
    case Government = 'government';
    case Charity = 'charity';

    public function label(): string
    {
        return match ($this) {
            self::Business => 'Business',
            self::Individual => 'Individual',
            self::Government => 'Government',
            self::Charity => 'Charity',
        };
    }

    /**
     * Resolve a value to its canonical backing value, accepting legacy b2b/b2c aliases.
     *
     * @return ($value is null ? null : mixed)
     */
    public static function coerce(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        return BackedEnumHelper::coerce(self::class, $value, fn (mixed $original): mixed => match (mb_strtolower((string) $original)) {
            'b2b' => self::Business->value,
            'b2c' => self::Individual->value,
            default => $original,
        });
    }

    /**
     * @return list<string>
     */
    public static function validationValues(): array
    {
        return [
            self::Business->value,
            self::Individual->value,
            self::Government->value,
            self::Charity->value,
            'b2b',
            'b2c',
        ];
    }
}
