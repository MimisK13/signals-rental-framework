<?php

use App\Actions\Notifications\SendCustomerCommunication;
use App\Contracts\Documents\DocumentRenderer;
use App\Data\Notifications\SendCustomerCommunicationData;
use App\Enums\DocumentOutputFormat;
use App\Jobs\DeliverCustomerCommunication;
use App\Models\CommunicationLog;
use App\Models\CommunicationTemplate;
use App\Models\Currency;
use App\Models\Document;
use App\Models\Member;
use App\Models\NotificationType;
use App\Models\Opportunity;
use App\Models\User;
use App\Notifications\CustomerCommunication;
use App\Services\Notifications\CustomerCommunicationAttachmentResolver;
use App\ValueObjects\Documents\DocumentRenderResult;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;

beforeEach(function () {
    config(['signals.installed' => true, 'signals.setup_complete' => true]);
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);

    Currency::factory()->create(['code' => 'GBP', 'symbol' => '£', 'name' => 'British Pound']);
    settings()->set('company.name', 'Acme Rentals');
    settings()->set('company.base_currency', 'GBP');

    $this->user = User::factory()->owner()->create();
    $this->actingAs($this->user);
});

describe('SendCustomerCommunication', function () {
    it('sends a branded customer email and logs a sent communication_log row', function () {
        Mail::fake();

        $recipient = Member::factory()->contact()->create();
        $recipient->emails()->create(['address' => 'customer@example.com', 'is_primary' => true]);

        $opportunity = Opportunity::factory()->create();
        $type = NotificationType::factory()->create(['key' => 'opportunity.confirmed']);

        $template = CommunicationTemplate::factory()->email()->create([
            'notification_type_id' => $type->id,
            'store_id' => $opportunity->store_id,
            'subject' => 'Confirmed {{ contact.name }}',
            'body' => 'Reference **{{ opportunity.reference }}**',
        ]);

        app(SendCustomerCommunication::class)(new SendCustomerCommunicationData(
            recipient: $recipient,
            template: $template,
            notificationTypeKey: $type->key,
            subject: $opportunity,
            mergeData: [
                'contact' => ['name' => 'Jane'],
                'opportunity' => ['reference' => 'Q-9001'],
            ],
            eyebrow: 'Booking Confirmed',
        ));

        $log = CommunicationLog::query()->where('notification_type_key', $type->key)->sole();

        expect($log->status)->toBe('sent')
            ->and($log->channel)->toBe('email')
            ->and($log->template_id)->toBe($template->id)
            ->and($log->notifiable_id)->toBe($recipient->id)
            ->and($log->recipient_address)->toBe('customer@example.com')
            ->and($log->rendered_body)->toContain('Q-9001')
            ->and($log->subject_line)->toBe('Confirmed Jane');

        $mail = (new CustomerCommunication(
            communicationLogId: $log->id,
            template: $template,
            notificationTypeKey: $type->key,
            mergeData: [
                'contact' => ['name' => 'Jane'],
                'opportunity' => ['reference' => 'Q-9001'],
            ],
            eyebrow: 'Booking Confirmed',
        ))->toMail($recipient);

        expect($mail->subject)->toBe('Confirmed Jane')
            ->and(data_get($mail->viewData, 'bodyHtml'))->toContain('Q-9001')
            ->and(data_get($mail->viewData, 'unsubscribeUrl'))->toContain('communications/unsubscribe');
    });

    it('queues delivery on the communications queue', function () {
        Queue::fake();

        $recipient = Member::factory()->contact()->create();
        $recipient->emails()->create(['address' => 'queued@example.com', 'is_primary' => true]);
        $opportunity = Opportunity::factory()->create();
        $type = NotificationType::factory()->create(['key' => 'invoice.created']);
        $template = CommunicationTemplate::factory()->email()->create([
            'notification_type_id' => $type->id,
            'store_id' => $opportunity->store_id,
        ]);

        app(SendCustomerCommunication::class)(new SendCustomerCommunicationData(
            recipient: $recipient,
            template: $template,
            notificationTypeKey: $type->key,
            subject: $opportunity,
        ));

        Queue::assertPushed(DeliverCustomerCommunication::class, function (DeliverCustomerCommunication $job): bool {
            return $job->queue === 'communications';
        });
    });

    it('uses a queued CustomerCommunication notification with communications queue semantics', function () {
        $notification = new CustomerCommunication(
            communicationLogId: 1,
            template: CommunicationTemplate::factory()->make(),
            notificationTypeKey: 'invoice.created',
            mergeData: [],
        );

        expect($notification->timeout)->toBe(180)
            ->and(in_array(ShouldQueue::class, class_implements($notification), true))->toBeTrue();
    });

    it('suppresses delivery for opted-out recipients but still logs suppressed status', function () {
        Mail::fake();
        Queue::fake();

        $recipient = Member::factory()->contact()->create([
            'communication_preferences' => [
                'opt_out' => ['invoice.created'],
            ],
        ]);
        $recipient->emails()->create(['address' => 'optedout@example.com', 'is_primary' => true]);

        $opportunity = Opportunity::factory()->create();
        $type = NotificationType::factory()->create(['key' => 'invoice.created']);
        $template = CommunicationTemplate::factory()->email()->create([
            'notification_type_id' => $type->id,
            'store_id' => $opportunity->store_id,
        ]);

        app(SendCustomerCommunication::class)(new SendCustomerCommunicationData(
            recipient: $recipient,
            template: $template,
            notificationTypeKey: $type->key,
            subject: $opportunity,
        ));

        Queue::assertNothingPushed();
        Mail::assertNothingSent();

        $log = CommunicationLog::query()->where('notification_type_key', $type->key)->sole();

        expect($log->status)->toBe('suppressed')
            ->and($log->failure_reason)->toContain('opted out');
    });

    it('re-checks opt-out when the queued delivery job runs and suppresses stale sends', function () {
        Notification::fake();

        $recipient = Member::factory()->contact()->create();
        $recipient->emails()->create(['address' => 'queued@example.com', 'is_primary' => true]);

        $opportunity = Opportunity::factory()->create();
        $type = NotificationType::factory()->create(['key' => 'invoice.created']);
        $template = CommunicationTemplate::factory()->email()->create([
            'notification_type_id' => $type->id,
            'store_id' => $opportunity->store_id,
        ]);

        $log = CommunicationLog::query()->create([
            'notification_type_key' => $type->key,
            'channel' => 'email',
            'template_id' => $template->id,
            'notifiable_type' => Member::class,
            'notifiable_id' => $recipient->id,
            'subject_type' => Opportunity::class,
            'subject_id' => $opportunity->id,
            'recipient_address' => 'queued@example.com',
            'subject_line' => 'Invoice ready',
            'rendered_body' => 'Body',
            'status' => 'queued',
            'queued_at' => now(),
        ]);

        $recipient->update([
            'communication_preferences' => [
                'opt_out' => ['invoice.created'],
            ],
        ]);

        (new DeliverCustomerCommunication(
            communicationLogId: $log->id,
            recipientId: $recipient->id,
            templateId: $template->id,
            notificationTypeKey: $type->key,
        ))->handle();

        Notification::assertNothingSent();

        expect($log->fresh()->status)->toBe('suppressed')
            ->and($log->fresh()->failure_reason)->toContain('opted out');
    });

    it('requires notifications.manage permission', function () {
        $salesUser = User::factory()->create();
        $salesUser->assignRole('Sales');
        $this->actingAs($salesUser);

        $recipient = Member::factory()->contact()->create();
        $opportunity = Opportunity::factory()->create();
        $type = NotificationType::factory()->create(['key' => 'opportunity.confirmed']);
        $template = CommunicationTemplate::factory()->email()->create([
            'notification_type_id' => $type->id,
            'store_id' => $opportunity->store_id,
        ]);

        app(SendCustomerCommunication::class)(new SendCustomerCommunicationData(
            recipient: $recipient,
            template: $template,
            notificationTypeKey: $type->key,
            subject: $opportunity,
        ));
    })->throws(AuthorizationException::class);

    it('attaches a PDF generated from the document snapshot when supplied', function () {
        $this->mock(DocumentRenderer::class, function ($mock): void {
            $mock->shouldReceive('renderFromSnapshot')
                ->once()
                ->andReturn(DocumentRenderResult::success(
                    html: '<html></html>',
                    renderedData: ['frozen' => true],
                    format: DocumentOutputFormat::Pdf,
                    pdfBinary: '%PDF-1.4-test-binary',
                ));
        });

        $recipient = Member::factory()->contact()->create();
        $recipient->emails()->create(['address' => 'attach@example.com', 'is_primary' => true]);
        $opportunity = Opportunity::factory()->create();
        $type = NotificationType::factory()->create(['key' => 'invoice.created']);

        $document = Document::factory()->forOpportunity($opportunity)->create([
            'document_number' => 'INV-1001',
            'rendered_data' => ['invoice' => ['total' => 5000]],
        ]);

        $template = CommunicationTemplate::factory()->email()->create([
            'notification_type_id' => $type->id,
            'store_id' => $opportunity->store_id,
            'attachments_config' => [
                'document_template_ids' => [$document->document_template_id],
            ],
        ]);

        $mail = (new CustomerCommunication(
            communicationLogId: 1,
            template: $template,
            notificationTypeKey: $type->key,
            mergeData: [],
            document: $document,
        ))->toMail($recipient);

        expect($mail->rawAttachments)->toHaveCount(1)
            ->and($mail->rawAttachments[0]['name'])->toBe('INV-1001.pdf');
    });

    it('skips attachment when the document template is not allowed by attachments_config', function () {
        $this->mock(CustomerCommunicationAttachmentResolver::class, function ($mock): void {
            $mock->shouldReceive('resolve')->once()->andReturn(null);
        });

        $recipient = Member::factory()->contact()->create();
        $opportunity = Opportunity::factory()->create();
        $type = NotificationType::factory()->create(['key' => 'invoice.created']);
        $document = Document::factory()->forOpportunity($opportunity)->create();
        $template = CommunicationTemplate::factory()->email()->create([
            'notification_type_id' => $type->id,
            'store_id' => $opportunity->store_id,
            'attachments_config' => ['document_template_ids' => [99999]],
        ]);

        $mail = (new CustomerCommunication(
            communicationLogId: 1,
            template: $template,
            notificationTypeKey: $type->key,
            mergeData: [],
            document: $document,
        ))->toMail($recipient);

        expect($mail->rawAttachments)->toBeEmpty();
    });
});

describe('CustomerCommunicationUnsubscribeUrl', function () {
    it('generates a signed unsubscribe link scoped to member and notification type', function () {
        $member = Member::factory()->contact()->create();

        $url = URL::signedRoute('communications.unsubscribe.show', [
            'member' => $member->id,
            'type' => 'invoice.created',
        ]);

        expect($url)->toContain('communications/unsubscribe/'.$member->id)
            ->and($url)->toContain('type=invoice.created')
            ->and($url)->toContain('signature=');
    });
});
