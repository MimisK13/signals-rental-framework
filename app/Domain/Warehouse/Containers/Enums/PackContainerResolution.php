<?php

declare(strict_types=1);

namespace App\Domain\Warehouse\Containers\Enums;

/**
 * Resolution keys accepted by {@see PackContainerItemData::$resolve} when a pack
 * conflict is returned as HTTP 409 (serialised-containers.md §Pack Endpoint).
 */
enum PackContainerResolution: string
{
    case Transfer = 'transfer';
    case AutoReturnAndPack = 'auto_return_and_pack';
    case ConfirmNesting = 'confirm_nesting';
}
