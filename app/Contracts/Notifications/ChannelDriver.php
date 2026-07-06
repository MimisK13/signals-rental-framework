<?php

namespace App\Contracts\Notifications;

use App\Data\Notifications\ChannelConfigFieldData;
use App\Data\Notifications\ChannelSendData;
use App\Data\Notifications\ChannelTestResultData;
use App\Data\Notifications\TestChannelProviderData;
use App\Jobs\SendChannelNotification;
use App\Services\Plugins\PluginRegistrar;

/**
 * Delivery driver for a notification channel (P23).
 *
 * Core ships email, database, and broadcast drivers; plugins register
 * additional drivers (Twilio, Slack, etc.) through {@see PluginRegistrar}.
 */
interface ChannelDriver
{
    /**
     * Unique driver key (e.g. email, database, broadcast, twilio_sms).
     */
    public function key(): string;

    /**
     * Laravel notification channel name this driver delivers on
     * (e.g. mail, database, broadcast).
     */
    public function channel(): string;

    /**
     * Human-readable label for settings UI and diagnostics.
     */
    public function label(): string;

    /**
     * Declarative configuration schema for tenant/provider setup forms.
     *
     * @return list<ChannelConfigFieldData>
     */
    public function configSchema(): array;

    /**
     * Resolve the driver's effective configuration at send/test time.
     *
     * Core email reads {@see settings()} via EmailSettings; plugin drivers
     * receive config from the caller until channel_providers lands in P24+.
     *
     * @return array<string, mixed>
     */
    public function resolveConfig(): array;

    /**
     * Deliver a notification payload through this driver.
     *
     * Called synchronously by {@see SendChannelNotification}; the
     * registry queues that job on the appropriate named queue.
     */
    public function send(ChannelSendData $payload): void;

    /**
     * Self-test connectivity and credentials for this driver.
     */
    public function test(TestChannelProviderData $data): ChannelTestResultData;
}
