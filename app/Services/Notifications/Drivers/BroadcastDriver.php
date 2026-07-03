<?php

namespace App\Services\Notifications\Drivers;

use App\Contracts\Notifications\ChannelDriver;
use App\Data\Notifications\ChannelConfigFieldData;
use App\Data\Notifications\ChannelSendData;
use App\Data\Notifications\ChannelTestResultData;
use App\Data\Notifications\TestChannelProviderData;
use App\Models\Member;
use App\Notifications\ChannelInfrastructureBroadcastNotification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Notification;

/**
 * Broadcast channel driver for real-time delivery via Reverb (P23).
 */
class BroadcastDriver implements ChannelDriver
{
    public function key(): string
    {
        return 'broadcast';
    }

    public function channel(): string
    {
        return 'broadcast';
    }

    public function label(): string
    {
        return 'Real-Time (Broadcast)';
    }

    /**
     * @return list<ChannelConfigFieldData>
     */
    public function configSchema(): array
    {
        return [
            new ChannelConfigFieldData(
                key: 'connection',
                label: 'Broadcast Connection',
                type: 'string',
                required: true,
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function resolveConfig(): array
    {
        return [
            'connection' => (string) config('broadcasting.default', 'null'),
        ];
    }

    public function send(ChannelSendData $payload): void
    {
        $notifiable = $this->resolveNotifiable($payload);

        Notification::send($notifiable, new ChannelInfrastructureBroadcastNotification(
            title: $payload->title,
            body: $payload->body,
            payload: $payload->data,
        ));
    }

    public function test(TestChannelProviderData $data): ChannelTestResultData
    {
        $connection = (string) config('broadcasting.default', 'null');

        if ($connection === 'null' || $connection === '') {
            return ChannelTestResultData::failed(
                $this,
                'Broadcasting is disabled (connection is null). Configure Reverb before testing the broadcast channel.',
            );
        }

        $member = Member::factory()->create();

        try {
            Notification::send($member, new ChannelInfrastructureBroadcastNotification(
                title: 'Channel test',
                body: 'Broadcast channel self-test notification.',
                payload: ['test' => true],
            ));
        } catch (\Throwable $exception) {
            $member->forceDelete();

            return ChannelTestResultData::failed(
                $this,
                'Broadcast delivery failed: '.$exception->getMessage(),
            );
        } finally {
            if ($member->exists) {
                $member->forceDelete();
            }
        }

        return ChannelTestResultData::passed(
            $this,
            "Broadcast channel dispatched a test notification via the {$connection} connection.",
        );
    }

    private function resolveNotifiable(ChannelSendData $payload): Model
    {
        $notifiableClass = $payload->notifiableType;

        if (! class_exists($notifiableClass)) {
            throw new \RuntimeException("Notifiable class [{$notifiableClass}] does not exist.");
        }

        /** @var Model|null $notifiable */
        $notifiable = $notifiableClass::query()->find($payload->notifiableId);

        if ($notifiable === null) {
            throw new \RuntimeException("Notifiable [{$notifiableClass}:{$payload->notifiableId}] was not found.");
        }

        if (! method_exists($notifiable, 'notify')) {
            throw new \RuntimeException("Notifiable [{$notifiableClass}] does not use the Notifiable trait.");
        }

        return $notifiable;
    }
}
