<?php

namespace App\Services\VirtualStock;

use App\Data\VirtualStock\VirtualStockIntakeData;
use App\Events\AuditableEvent;
use App\Models\VirtualStockIntake;
use App\Services\Api\WebhookService;
use Thunk\Verbs\Facades\Verbs;

/**
 * Bridges virtual stock intake mutations onto the audit log and outbound webhook bus.
 *
 * Emitted from actions after {@see CommitsVerbsEvents::commitVerbs()} — never
 * from Verbs apply()/handle() replay paths.
 */
class VirtualStockIntakeEventRecorder
{
    /**
     * @var list<string>
     */
    private const ENTITY_ACTIONS = [
        'virtual_stock_intake.created',
        'virtual_stock_intake.updated',
    ];

    public function created(VirtualStockIntake $intake): void
    {
        $this->record($intake, 'virtual_stock_intake.created');
    }

    public function updated(VirtualStockIntake $intake): void
    {
        $this->record($intake, 'virtual_stock_intake.updated');
    }

    public function confirmed(VirtualStockIntake $intake): void
    {
        $this->record($intake, 'virtual_stock_intake.confirmed', [
            'status' => $intake->status,
            'confirmed_at' => $intake->confirmed_at?->toIso8601String(),
        ]);
    }

    public function received(VirtualStockIntake $intake): void
    {
        $this->record($intake, 'virtual_stock_intake.received', [
            'status' => $intake->status,
            'received_at' => $intake->received_at?->toIso8601String(),
        ]);
    }

    public function returned(VirtualStockIntake $intake): void
    {
        $this->record($intake, 'virtual_stock_intake.returned', [
            'status' => $intake->status,
            'returned_at' => $intake->returned_at?->toIso8601String(),
        ]);
    }

    public function cancelled(VirtualStockIntake $intake, ?string $reason = null): void
    {
        $this->record($intake, 'virtual_stock_intake.cancelled', [
            'status' => $intake->status,
            'cancelled_at' => $intake->cancelled_at?->toIso8601String(),
            'reason' => $reason,
        ]);
    }

    public function deleted(VirtualStockIntake $intake): void
    {
        $this->record($intake, 'virtual_stock_intake.deleted');
    }

    /**
     * @param  array<string, mixed>|null  $newValues
     */
    private function record(VirtualStockIntake $intake, string $action, ?array $newValues = null): void
    {
        if (Verbs::isReplaying()) {
            return;
        }

        event(new AuditableEvent(
            model: $intake,
            action: $action,
            newValues: $newValues,
        ));

        app(WebhookService::class)->dispatch($action, $this->payloadFor($intake, $action));
    }

    /**
     * @return array<string, mixed>
     */
    private function payloadFor(VirtualStockIntake $intake, string $action): array
    {
        if (in_array($action, self::ENTITY_ACTIONS, true)) {
            $fresh = VirtualStockIntake::query()->find($intake->getKey()) ?? $intake;

            return ['virtual_stock_intake' => VirtualStockIntakeData::fromModel($fresh)->toArray()];
        }

        return [
            'id' => $intake->getKey(),
            'action' => $action,
        ];
    }
}
