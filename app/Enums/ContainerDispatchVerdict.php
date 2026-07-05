<?php

namespace App\Enums;

/**
 * Per-product outcome of transport container dispatch validation
 * (serialised-containers.md §"Dispatch Validation Logic").
 */
enum ContainerDispatchVerdict: string
{
    case Pass = 'pass';
    case WarehouseAutoAdd = 'warehouse_auto_add';
    case Prompt = 'prompt';
    case Fail = 'fail';

    public function label(): string
    {
        return match ($this) {
            self::Pass => 'Pass',
            self::WarehouseAutoAdd => 'Warehouse auto-add',
            self::Prompt => 'Prompt',
            self::Fail => 'Fail',
        };
    }
}
