<?php

namespace App\Actions\Notifications;

use App\Models\ChannelProvider;
use Illuminate\Support\Facades\Gate;

class DeleteChannelProvider
{
    public function __invoke(ChannelProvider $provider): void
    {
        Gate::authorize('notifications.manage');

        $provider->delete();
    }
}
