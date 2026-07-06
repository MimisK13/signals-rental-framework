<?php

namespace Database\Factories;

use App\Models\CommunicationLog;
use App\Models\Member;
use App\Models\Opportunity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommunicationLog>
 */
class CommunicationLogFactory extends Factory
{
    protected $model = CommunicationLog::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $member = Member::factory()->create();
        $opportunity = Opportunity::factory()->create();

        return [
            'notification_type_key' => 'opportunity.confirmed',
            'channel' => 'in_app',
            'provider_id' => null,
            'template_id' => null,
            'notifiable_type' => Member::class,
            'notifiable_id' => $member->id,
            'subject_type' => Opportunity::class,
            'subject_id' => $opportunity->id,
            'recipient_address' => null,
            'subject_line' => fake()->sentence(),
            'rendered_body' => fake()->paragraph(),
            'attachments' => null,
            'status' => 'queued',
            'failure_reason' => null,
            'message_id' => null,
            'is_pinned' => false,
            'queued_at' => now(),
            'sent_at' => null,
            'delivered_at' => null,
            'opened_at' => null,
        ];
    }

    public function sent(): static
    {
        return $this->state(fn (): array => [
            'status' => 'sent',
            'sent_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (): array => [
            'status' => 'failed',
            'failure_reason' => 'Delivery failed.',
        ]);
    }

    public function pinned(): static
    {
        return $this->state(fn (): array => ['is_pinned' => true]);
    }
}
