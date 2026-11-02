<?php

declare(strict_types=1);

namespace App\Domain\Communications\Actions;

use App\Domain\Communications\Models\ChannelProvider;
use Illuminate\Support\Facades\Gate;

final class DeleteChannelProvider
{
    public function __invoke(ChannelProvider $provider): void
    {
        Gate::authorize('notifications.manage');

        $provider->delete();
    }
}
