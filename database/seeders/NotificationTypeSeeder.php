<?php

namespace Database\Seeders;

use App\Enums\NotificationAudience;
use App\Services\NotificationRegistry;
use Illuminate\Database\Seeder;

class NotificationTypeSeeder extends Seeder
{
    /**
     * Core notification type definitions.
     *
     * @return array<string, array{
     *     category: string,
     *     name: string,
     *     description: string,
     *     audience: NotificationAudience,
     *     available_channels: list<string>,
     *     default_channels: list<string>,
     *     default_recipients?: list<array<string, mixed>>,
     *     data_schema?: array<string, mixed>,
     *     is_system?: bool
     * }>
     */
    public static function types(): array
    {
        return array_merge(
            self::userTypes(),
            self::systemTypes(),
            self::opportunityTypes(),
            self::shortageTypes(),
            self::invoicingTypes(),
            self::documentTypes(),
        );
    }

    public function run(): void
    {
        $registry = app(NotificationRegistry::class);
        $registry->registerMany(self::types());
        $registry->syncToDatabase();
    }

    /**
     * @return array<string, array{
     *     category: string,
     *     name: string,
     *     description: string,
     *     audience: NotificationAudience,
     *     available_channels: list<string>,
     *     default_channels: list<string>,
     *     default_recipients?: list<array<string, mixed>>,
     *     data_schema?: array<string, mixed>,
     *     is_system?: bool
     * }>
     */
    private static function userTypes(): array
    {
        return [
            'user.invited' => [
                'category' => 'Users',
                'name' => 'User Invited',
                'description' => 'Sent when a new user is invited to the system.',
                'audience' => NotificationAudience::Internal,
                'available_channels' => ['database', 'mail'],
                'default_channels' => ['database', 'mail'],
                'default_recipients' => [['type' => 'trigger_actor']],
                'data_schema' => self::userSchema(),
            ],
            'user.deactivated' => [
                'category' => 'Users',
                'name' => 'User Deactivated',
                'description' => 'Sent when a user account is deactivated.',
                'audience' => NotificationAudience::Internal,
                'available_channels' => ['database', 'mail'],
                'default_channels' => ['database'],
                'default_recipients' => [['type' => 'trigger_actor']],
                'data_schema' => self::userSchema(),
            ],
            'user.reactivated' => [
                'category' => 'Users',
                'name' => 'User Reactivated',
                'description' => 'Sent when a user account is reactivated.',
                'audience' => NotificationAudience::Internal,
                'available_channels' => ['database', 'mail'],
                'default_channels' => ['database'],
                'default_recipients' => [['type' => 'trigger_actor']],
                'data_schema' => self::userSchema(),
            ],
        ];
    }

    /**
     * @return array<string, array{
     *     category: string,
     *     name: string,
     *     description: string,
     *     audience: NotificationAudience,
     *     available_channels: list<string>,
     *     default_channels: list<string>,
     *     default_recipients?: list<array<string, mixed>>,
     *     data_schema?: array<string, mixed>,
     *     is_system?: bool
     * }>
     */
    private static function systemTypes(): array
    {
        return [
            'password.reset' => [
                'category' => 'System',
                'name' => 'Password Reset',
                'description' => 'Sent when a password reset is requested.',
                'audience' => NotificationAudience::Internal,
                'available_channels' => ['mail'],
                'default_channels' => ['mail'],
                'default_recipients' => [['type' => 'trigger_actor']],
                'data_schema' => self::userSchema(),
                'is_system' => true,
            ],
            'system.test_email' => [
                'category' => 'System',
                'name' => 'Test Email',
                'description' => 'Test notification to verify email delivery.',
                'audience' => NotificationAudience::Internal,
                'available_channels' => ['mail'],
                'default_channels' => ['mail'],
                'default_recipients' => [['type' => 'trigger_actor']],
                'data_schema' => self::userSchema(),
                'is_system' => true,
            ],
        ];
    }

    /**
     * @return array<string, array{
     *     category: string,
     *     name: string,
     *     description: string,
     *     audience: NotificationAudience,
     *     available_channels: list<string>,
     *     default_channels: list<string>,
     *     default_recipients?: list<array<string, mixed>>,
     *     data_schema?: array<string, mixed>,
     *     is_system?: bool
     * }>
     */
    private static function opportunityTypes(): array
    {
        $schema = self::opportunitySchema();

        return [
            'opportunity.created' => [
                'category' => 'Opportunities',
                'name' => 'Opportunity Created',
                'description' => 'Sent when a new opportunity is created.',
                'audience' => NotificationAudience::Internal,
                'available_channels' => ['database', 'mail'],
                'default_channels' => ['database'],
                'default_recipients' => [['type' => 'subject_owner']],
                'data_schema' => $schema,
            ],
            'opportunity.confirmed' => [
                'category' => 'Opportunities',
                'name' => 'Opportunity Confirmed',
                'description' => 'Sent when an opportunity is confirmed.',
                'audience' => NotificationAudience::Both,
                'available_channels' => ['database', 'mail'],
                'default_channels' => ['database', 'mail'],
                'default_recipients' => [['type' => 'subject_owner'], ['type' => 'contact']],
                'data_schema' => $schema,
            ],
            'opportunity.cancelled' => [
                'category' => 'Opportunities',
                'name' => 'Opportunity Cancelled',
                'description' => 'Sent when an opportunity is cancelled.',
                'audience' => NotificationAudience::Both,
                'available_channels' => ['database', 'mail'],
                'default_channels' => ['database', 'mail'],
                'default_recipients' => [['type' => 'subject_owner'], ['type' => 'contact']],
                'data_schema' => $schema,
            ],
            'opportunity.on_hold' => [
                'category' => 'Opportunities',
                'name' => 'Opportunity On Hold',
                'description' => 'Sent when an opportunity is placed on hold.',
                'audience' => NotificationAudience::Internal,
                'available_channels' => ['database', 'mail'],
                'default_channels' => ['database'],
                'default_recipients' => [['type' => 'subject_owner']],
                'data_schema' => $schema,
            ],
            'opportunity.checked_out' => [
                'category' => 'Opportunities',
                'name' => 'Opportunity Checked Out',
                'description' => 'Sent when equipment is dispatched for an opportunity.',
                'audience' => NotificationAudience::Both,
                'available_channels' => ['database', 'mail'],
                'default_channels' => ['database', 'mail'],
                'default_recipients' => [['type' => 'subject_owner'], ['type' => 'contact']],
                'data_schema' => $schema,
            ],
            'opportunity.checked_in' => [
                'category' => 'Opportunities',
                'name' => 'Opportunity Checked In',
                'description' => 'Sent when equipment is returned for an opportunity.',
                'audience' => NotificationAudience::Internal,
                'available_channels' => ['database', 'mail'],
                'default_channels' => ['database'],
                'default_recipients' => [['type' => 'subject_owner']],
                'data_schema' => $schema,
            ],
            'opportunity.late_return' => [
                'category' => 'Opportunities',
                'name' => 'Late Return',
                'description' => 'Sent when equipment is not returned by the expected date.',
                'audience' => NotificationAudience::Internal,
                'available_channels' => ['database', 'mail'],
                'default_channels' => ['database', 'mail'],
                'default_recipients' => [['type' => 'subject_owner']],
                'data_schema' => $schema,
            ],
            'opportunity.assigned' => [
                'category' => 'Opportunities',
                'name' => 'Opportunity Assigned',
                'description' => 'Sent when opportunity ownership is assigned or changed.',
                'audience' => NotificationAudience::Internal,
                'available_channels' => ['database', 'mail'],
                'default_channels' => ['database'],
                'default_recipients' => [['type' => 'subject_owner']],
                'data_schema' => $schema,
            ],
            'opportunity.quote_expiring' => [
                'category' => 'Opportunities',
                'name' => 'Quote Expiring',
                'description' => 'Sent when a quote is approaching its expiry date.',
                'audience' => NotificationAudience::Both,
                'available_channels' => ['database', 'mail'],
                'default_channels' => ['database', 'mail'],
                'default_recipients' => [['type' => 'subject_owner'], ['type' => 'contact']],
                'data_schema' => $schema,
            ],
        ];
    }

    /**
     * @return array<string, array{
     *     category: string,
     *     name: string,
     *     description: string,
     *     audience: NotificationAudience,
     *     available_channels: list<string>,
     *     default_channels: list<string>,
     *     default_recipients?: list<array<string, mixed>>,
     *     data_schema?: array<string, mixed>,
     *     is_system?: bool
     * }>
     */
    private static function shortageTypes(): array
    {
        $schema = self::shortageSchema();

        return [
            'shortage.detected' => [
                'category' => 'Shortages',
                'name' => 'Shortage Detected',
                'description' => 'Sent when a stock shortage is detected on an opportunity.',
                'audience' => NotificationAudience::Internal,
                'available_channels' => ['database', 'mail'],
                'default_channels' => ['database', 'mail'],
                'default_recipients' => [['type' => 'subject_owner']],
                'data_schema' => $schema,
            ],
            'shortage.escalated' => [
                'category' => 'Shortages',
                'name' => 'Shortage Escalated',
                'description' => 'Sent when a shortage is escalated for attention.',
                'audience' => NotificationAudience::Internal,
                'available_channels' => ['database', 'mail'],
                'default_channels' => ['database', 'mail'],
                'default_recipients' => [['type' => 'role', 'role' => 'manager']],
                'data_schema' => $schema,
            ],
            'shortage.cleared' => [
                'category' => 'Shortages',
                'name' => 'Shortage Cleared',
                'description' => 'Sent when a shortage is fully resolved.',
                'audience' => NotificationAudience::Internal,
                'available_channels' => ['database', 'mail'],
                'default_channels' => ['database'],
                'default_recipients' => [['type' => 'subject_owner']],
                'data_schema' => $schema,
            ],
            'shortage.acknowledged' => [
                'category' => 'Shortages',
                'name' => 'Shortage Acknowledged',
                'description' => 'Sent when a shortage acknowledgement is recorded.',
                'audience' => NotificationAudience::Internal,
                'available_channels' => ['database', 'mail'],
                'default_channels' => ['database'],
                'default_recipients' => [['type' => 'subject_owner']],
                'data_schema' => $schema,
            ],
            'shortage.resolution.confirmed' => [
                'category' => 'Shortages',
                'name' => 'Shortage Resolution Confirmed',
                'description' => 'Sent when a shortage resolution option is confirmed.',
                'audience' => NotificationAudience::Internal,
                'available_channels' => ['database', 'mail'],
                'default_channels' => ['database'],
                'default_recipients' => [['type' => 'subject_owner']],
                'data_schema' => $schema,
            ],
            'shortage.waitlist.created' => [
                'category' => 'Shortages',
                'name' => 'Waitlist Monitor Created',
                'description' => 'Sent when a shortage is added to the waitlist.',
                'audience' => NotificationAudience::Internal,
                'available_channels' => ['database', 'mail'],
                'default_channels' => ['database'],
                'default_recipients' => [['type' => 'subject_owner']],
                'data_schema' => $schema,
            ],
            'shortage.waitlist.matched' => [
                'category' => 'Shortages',
                'name' => 'Waitlist Match',
                'description' => 'Sent when stock a waitlist monitor is waiting on becomes available.',
                'audience' => NotificationAudience::Internal,
                'available_channels' => ['database', 'mail'],
                'default_channels' => [],
                'default_recipients' => [['type' => 'subject_owner']],
                'data_schema' => $schema,
            ],
            'shortage.waitlist.expired' => [
                'category' => 'Shortages',
                'name' => 'Waitlist Expired',
                'description' => 'Sent when a waitlist monitor expires without a match.',
                'audience' => NotificationAudience::Internal,
                'available_channels' => ['database', 'mail'],
                'default_channels' => ['database'],
                'default_recipients' => [['type' => 'subject_owner']],
                'data_schema' => $schema,
            ],
        ];
    }

    /**
     * @return array<string, array{
     *     category: string,
     *     name: string,
     *     description: string,
     *     audience: NotificationAudience,
     *     available_channels: list<string>,
     *     default_channels: list<string>,
     *     default_recipients?: list<array<string, mixed>>,
     *     data_schema?: array<string, mixed>,
     *     is_system?: bool
     * }>
     */
    private static function invoicingTypes(): array
    {
        $schema = self::invoiceSchema();

        return [
            'invoice.created' => [
                'category' => 'Invoicing',
                'name' => 'Invoice Created',
                'description' => 'Sent when an invoice is generated and delivered.',
                'audience' => NotificationAudience::External,
                'available_channels' => ['mail'],
                'default_channels' => ['mail'],
                'default_recipients' => [['type' => 'contact']],
                'data_schema' => $schema,
            ],
            'invoice.overdue' => [
                'category' => 'Invoicing',
                'name' => 'Invoice Overdue',
                'description' => 'Sent when an invoice is past its due date.',
                'audience' => NotificationAudience::Both,
                'available_channels' => ['database', 'mail'],
                'default_channels' => ['database', 'mail'],
                'default_recipients' => [['type' => 'subject_owner'], ['type' => 'contact']],
                'data_schema' => $schema,
            ],
            'invoice.payment_received' => [
                'category' => 'Invoicing',
                'name' => 'Payment Received',
                'description' => 'Sent when a payment is recorded against an invoice.',
                'audience' => NotificationAudience::Both,
                'available_channels' => ['database', 'mail'],
                'default_channels' => ['database', 'mail'],
                'default_recipients' => [['type' => 'subject_owner'], ['type' => 'contact']],
                'data_schema' => $schema,
            ],
            'invoice.credit_note' => [
                'category' => 'Invoicing',
                'name' => 'Credit Note Issued',
                'description' => 'Sent when a credit note is issued to a customer.',
                'audience' => NotificationAudience::External,
                'available_channels' => ['mail'],
                'default_channels' => ['mail'],
                'default_recipients' => [['type' => 'contact']],
                'data_schema' => $schema,
            ],
        ];
    }

    /**
     * @return array<string, array{
     *     category: string,
     *     name: string,
     *     description: string,
     *     audience: NotificationAudience,
     *     available_channels: list<string>,
     *     default_channels: list<string>,
     *     default_recipients?: list<array<string, mixed>>,
     *     data_schema?: array<string, mixed>,
     *     is_system?: bool
     * }>
     */
    private static function documentTypes(): array
    {
        $schema = self::documentSchema();

        return [
            'document.finalised' => [
                'category' => 'Documents',
                'name' => 'Document Finalised',
                'description' => 'Sent when a generated document is finalised.',
                'audience' => NotificationAudience::Internal,
                'available_channels' => ['database', 'mail'],
                'default_channels' => ['database'],
                'default_recipients' => [['type' => 'trigger_actor']],
                'data_schema' => $schema,
            ],
            'document.emailed' => [
                'category' => 'Documents',
                'name' => 'Document Emailed',
                'description' => 'Sent when a document is emailed to a recipient.',
                'audience' => NotificationAudience::External,
                'available_channels' => ['mail'],
                'default_channels' => ['mail'],
                'default_recipients' => [['type' => 'contact']],
                'data_schema' => $schema,
            ],
            'document.public_link_created' => [
                'category' => 'Documents',
                'name' => 'Document Public Link Created',
                'description' => 'Sent when a public sharing link is created for a document.',
                'audience' => NotificationAudience::Both,
                'available_channels' => ['database', 'mail'],
                'default_channels' => ['database'],
                'default_recipients' => [['type' => 'trigger_actor'], ['type' => 'contact']],
                'data_schema' => $schema,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function userSchema(): array
    {
        return [
            'groups' => [
                [
                    'label' => 'User',
                    'fields' => [
                        ['path' => 'user.name', 'type' => 'string', 'label' => 'Name'],
                        ['path' => 'user.email', 'type' => 'string', 'label' => 'Email'],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function opportunitySchema(): array
    {
        return [
            'groups' => [
                [
                    'label' => 'Opportunity',
                    'fields' => [
                        ['path' => 'opportunity.id', 'type' => 'number', 'label' => 'ID'],
                        ['path' => 'opportunity.subject', 'type' => 'string', 'label' => 'Subject'],
                        ['path' => 'opportunity.reference', 'type' => 'string', 'label' => 'Reference'],
                        ['path' => 'opportunity.starts_at', 'type' => 'datetime', 'label' => 'Start Date'],
                        ['path' => 'opportunity.ends_at', 'type' => 'datetime', 'label' => 'End Date'],
                    ],
                ],
                [
                    'label' => 'Contact',
                    'fields' => [
                        ['path' => 'contact.name', 'type' => 'string', 'label' => 'Name'],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function shortageSchema(): array
    {
        return [
            'groups' => [
                [
                    'label' => 'Shortage',
                    'fields' => [
                        ['path' => 'shortage.id', 'type' => 'number', 'label' => 'ID'],
                        ['path' => 'shortage.product_name', 'type' => 'string', 'label' => 'Product'],
                        ['path' => 'shortage.quantity', 'type' => 'number', 'label' => 'Quantity Short'],
                    ],
                ],
                [
                    'label' => 'Opportunity',
                    'fields' => [
                        ['path' => 'opportunity.id', 'type' => 'number', 'label' => 'ID'],
                        ['path' => 'opportunity.subject', 'type' => 'string', 'label' => 'Subject'],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function invoiceSchema(): array
    {
        return [
            'groups' => [
                [
                    'label' => 'Invoice',
                    'fields' => [
                        ['path' => 'invoice.id', 'type' => 'number', 'label' => 'ID'],
                        ['path' => 'invoice.number', 'type' => 'string', 'label' => 'Number'],
                        ['path' => 'invoice.total', 'type' => 'currency', 'label' => 'Total'],
                        ['path' => 'invoice.due_at', 'type' => 'datetime', 'label' => 'Due Date'],
                    ],
                ],
                [
                    'label' => 'Contact',
                    'fields' => [
                        ['path' => 'contact.name', 'type' => 'string', 'label' => 'Name'],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function documentSchema(): array
    {
        return [
            'groups' => [
                [
                    'label' => 'Document',
                    'fields' => [
                        ['path' => 'document.id', 'type' => 'number', 'label' => 'ID'],
                        ['path' => 'document.document_number', 'type' => 'string', 'label' => 'Number'],
                        ['path' => 'document.document_type', 'type' => 'string', 'label' => 'Type'],
                        ['path' => 'document.public_url', 'type' => 'string', 'label' => 'Public URL'],
                    ],
                ],
            ],
        ];
    }
}
