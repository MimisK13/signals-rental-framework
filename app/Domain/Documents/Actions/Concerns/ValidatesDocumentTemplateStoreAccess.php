<?php

declare(strict_types=1);

namespace App\Domain\Documents\Actions\Concerns;

use App\Models\User;

trait ValidatesDocumentTemplateStoreAccess
{
    protected function assertUserCanAccessStore(int $storeId): void
    {
        /** @var User|null $user */
        $user = auth()->user();

        if ($user === null) {
            abort(403);
        }

        $storeIds = $user->accessibleStoreIds();

        if ($storeIds === null) {
            return;
        }

        if (! in_array($storeId, $storeIds, true)) {
            abort(404);
        }
    }
}
