<?php

namespace App\Services\Notifications\Drivers;

use App\Contracts\Notifications\ChannelDriver;
use App\Data\Notifications\ChannelConfigFieldData;
use App\Data\Notifications\ChannelSendData;
use App\Data\Notifications\ChannelTestResultData;
use App\Data\Notifications\TestChannelProviderData;
use App\Models\Member;
use App\Notifications\ChannelInfrastructureDatabaseNotification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;

/**
 * Database (in-app) channel driver wrapping Laravel's database notifications (P23).
 */
class DatabaseDriver implements ChannelDriver
{
    public function key(): string
    {
        return 'database';
    }

    public function channel(): string
    {
        return 'database';
    }

    public function label(): string
    {
        return 'In-App (Database)';
    }

    /**
     * @return list<ChannelConfigFieldData>
     */
    public function configSchema(): array
    {
        return [];
    }

    /**
     * @return array<string, mixed>
     */
    public function resolveConfig(): array
    {
        return [];
    }

    public function send(ChannelSendData $payload): void
    {
        $notifiable = $this->resolveNotifiable($payload);

        Notification::send($notifiable, new ChannelInfrastructureDatabaseNotification(
            title: $payload->title,
            body: $payload->body,
            payload: $payload->data,
        ));
    }

    public function test(TestChannelProviderData $data): ChannelTestResultData
    {
        if (! Schema::hasTable('notifications')) {
            return ChannelTestResultData::failed(
                $this,
                'The notifications table is not available; run migrations before testing the database channel.',
            );
        }

        $member = Member::factory()->create();

        try {
            Notification::send($member, new ChannelInfrastructureDatabaseNotification(
                title: 'Channel test',
                body: 'Database channel self-test notification.',
                payload: ['test' => true],
            ));
        } catch (\Throwable $exception) {
            $member->forceDelete();

            return ChannelTestResultData::failed(
                $this,
                'Database notification delivery failed: '.$exception->getMessage(),
            );
        }

        $stored = $member->notifications()->where('type', ChannelInfrastructureDatabaseNotification::class)->exists();
        $member->notifications()->delete();
        $member->forceDelete();

        if (! $stored) {
            return ChannelTestResultData::failed(
                $this,
                'Database notification was not persisted to the notifications table.',
            );
        }

        return ChannelTestResultData::passed(
            $this,
            'Database channel wrote and read a test notification successfully.',
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
