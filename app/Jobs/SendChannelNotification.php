<?php

namespace App\Jobs;

use App\Data\Notifications\ChannelSendData;
use App\Services\Notifications\ChannelProviderRegistry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendChannelNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(
        public ChannelSendData $payload,
    ) {}

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [30, 120, 600];
    }

    public function handle(ChannelProviderRegistry $registry): void
    {
        $driver = $registry->resolve(driverKey: $this->payload->driverKey);

        try {
            $driver->send($this->payload);
        } catch (\Throwable $exception) {
            Log::error('SendChannelNotification: delivery failed', [
                'driver' => $this->payload->driverKey,
                'notifiable_type' => $this->payload->notifiableType,
                'notifiable_id' => $this->payload->notifiableId,
                'exception' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    public function failed(?\Throwable $exception): void
    {
        Log::error('SendChannelNotification permanently failed after all retries', [
            'driver' => $this->payload->driverKey,
            'notifiable_type' => $this->payload->notifiableType,
            'notifiable_id' => $this->payload->notifiableId,
            'exception' => $exception?->getMessage(),
        ]);
    }
}
