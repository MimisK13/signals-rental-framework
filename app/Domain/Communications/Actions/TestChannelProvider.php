<?php

declare(strict_types=1);

namespace App\Domain\Communications\Actions;

use App\Domain\Communications\Data\ChannelTestResultData;
use App\Domain\Communications\Data\TestChannelProviderData;
use App\Domain\Communications\Services\ChannelProviderRegistry;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

/**
 * Self-test a notification channel provider (P23).
 */
final class TestChannelProvider
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
