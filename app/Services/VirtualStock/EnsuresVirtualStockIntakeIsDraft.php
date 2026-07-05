<?php

namespace App\Services\VirtualStock;

use App\Models\VirtualStockIntake;
use Illuminate\Validation\ValidationException;

class EnsuresVirtualStockIntakeIsDraft
{
    public function assert(VirtualStockIntake $intake): void
    {
        if ($intake->status !== 'draft') {
            throw ValidationException::withMessages([
                'virtual_stock_intake' => ['Only draft virtual stock intakes can be edited.'],
            ]);
        }
    }
}
