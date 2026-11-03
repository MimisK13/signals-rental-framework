<?php

declare(strict_types=1);

namespace App\Domain\Quotations\Services;

use App\Domain\Notifications\Enums\NotificationAudience;

final class QuotationEventDefinitions
{
    /** @return list<string> */
    public static function all(): array
    {
        return ['quotation.sent', 'quotation.accepted', 'quotation.declined'];
    }

    /** @return array<string, array<string, mixed>> */
    public static function notifications(): array
    {
        $definitions = [];

        foreach (self::all() as $name) {
            $title = (string) str($name)->replace('.', ' ')->headline();
            $definitions[$name] = [
                'category' => 'Quotations',
                'name' => $title,
                'description' => $title.'.',
                'audience' => NotificationAudience::Internal,
                'available_channels' => ['database', 'mail'],
                'default_channels' => ['database'],
                'default_recipients' => [['type' => 'subject_owner']],
                'is_system' => true,
            ];
        }

        return $definitions;
    }
}
