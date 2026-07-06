<?php

namespace App\Actions\Notifications;

use App\Data\Notifications\ChannelTestResultData;
use App\Data\Notifications\TestChannelProviderData;
use App\Services\Notifications\ChannelProviderRegistry;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

/**
 * Self-test a notification channel provider (P23).
 */
class TestChannelProvider
{
    public function __construct(
        private readonly ChannelProviderRegistry $registry,
    ) {}

    /**
     * @throws AuthorizationException
     */
    public function __invoke(TestChannelProviderData $data): ChannelTestResultData
    {
        Gate::authorize('notifications.manage');

        return $this->registry->test($data);
    }
}
