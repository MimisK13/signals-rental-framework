<?php

namespace App\Services\Notifications;

/**
 * Canonical system communication template definitions seeded per store.
 */
class SystemCommunicationTemplates
{
    /**
     * Notification type keys that receive bundled email templates on store seed.
     *
     * @return list<string>
     */
    public static function seededTypeKeys(): array
    {
        return [
            'opportunity.created',
            'opportunity.confirmed',
            'opportunity.cancelled',
            'opportunity.checked_out',
            'opportunity.late_return',
            'opportunity.quote_expiring',
            'shortage.detected',
            'shortage.escalated',
            'shortage.waitlist.matched',
            'virtual_stock.overrun',
            'invoice.created',
            'invoice.overdue',
            'invoice.payment_received',
            'invoice.credit_note',
            'statement.emailed',
            'document.emailed',
        ];
    }

    /**
     * @return list<array{
     *     notification_type_key: string,
     *     slug: string,
     *     name: string,
     *     channel: string,
     *     subject: string|null,
     *     body: string,
     *     attachments_config: array<string, mixed>|null,
     *     eyebrow: string|null
     * }>
     */
    public static function definitions(): array
    {
        return array_map(
            fn (string $key): array => self::definition($key),
            self::seededTypeKeys(),
        );
    }

    /**
     * @return array{
     *     notification_type_key: string,
     *     slug: string,
     *     name: string,
     *     channel: string,
     *     subject: string|null,
     *     body: string,
     *     attachments_config: array<string, mixed>|null,
     *     eyebrow: string|null
     * }
     */
    public static function definition(string $notificationTypeKey): array
    {
        return match ($notificationTypeKey) {
            'opportunity.created' => self::emailDefinition(
                key: $notificationTypeKey,
                name: 'Opportunity Created Email',
                subject: 'New Opportunity — {{ opportunity.reference | default:"New booking" }}',
                eyebrow: 'Opportunity Created',
                intro: 'A new opportunity has been created and assigned to you.',
                fields: [
                    '- **Subject:** {{ opportunity.subject }}',
                    '- **Reference:** {{ opportunity.reference | default:"—" }}',
                    '- **Starts:** {{ opportunity.starts_at | date }}',
                    '- **Ends:** {{ opportunity.ends_at | date }}',
                ],
            ),
            'opportunity.confirmed' => self::emailDefinition(
                key: $notificationTypeKey,
                name: 'Booking Confirmation Email',
                subject: 'Booking Confirmed — {{ opportunity.reference | default:"Your booking" }}',
                eyebrow: 'Booking Confirmed',
                intro: 'Thank you — your booking has been confirmed.',
                fields: [
                    '- **Subject:** {{ opportunity.subject }}',
                    '- **Reference:** {{ opportunity.reference | default:"—" }}',
                    '- **Event dates:** {{ opportunity.starts_at | date }} – {{ opportunity.ends_at | date }}',
                    '- **Contact:** {{ contact.name | default:"—" }}',
                ],
            ),
            'opportunity.cancelled' => self::emailDefinition(
                key: $notificationTypeKey,
                name: 'Booking Cancelled Email',
                subject: 'Booking Cancelled — {{ opportunity.reference | default:"Your booking" }}',
                eyebrow: 'Booking Cancelled',
                intro: 'This booking has been cancelled.',
                fields: [
                    '- **Subject:** {{ opportunity.subject }}',
                    '- **Reference:** {{ opportunity.reference | default:"—" }}',
                ],
            ),
            'opportunity.checked_out' => self::emailDefinition(
                key: $notificationTypeKey,
                name: 'Dispatch Notification Email',
                subject: 'Equipment Dispatched — {{ opportunity.reference | default:"Your booking" }}',
                eyebrow: 'Dispatch',
                intro: 'Equipment for your booking has been dispatched.',
                fields: [
                    '- **Subject:** {{ opportunity.subject }}',
                    '- **Reference:** {{ opportunity.reference | default:"—" }}',
                ],
            ),
            'opportunity.late_return' => self::emailDefinition(
                key: $notificationTypeKey,
                name: 'Late Return Email',
                subject: 'Late Return — {{ opportunity.reference | default:"Booking" }}',
                eyebrow: 'Late Return',
                intro: 'Equipment has not been returned by the expected date.',
                fields: [
                    '- **Subject:** {{ opportunity.subject }}',
                    '- **Reference:** {{ opportunity.reference | default:"—" }}',
                    '- **Expected end:** {{ opportunity.ends_at | date }}',
                ],
            ),
            'opportunity.quote_expiring' => self::emailDefinition(
                key: $notificationTypeKey,
                name: 'Quote Expiring Email',
                subject: 'Quote Expiring — {{ opportunity.reference | default:"Your quote" }}',
                eyebrow: 'Quote Expiring',
                intro: 'Your quotation is approaching its expiry date.',
                fields: [
                    '- **Subject:** {{ opportunity.subject }}',
                    '- **Reference:** {{ opportunity.reference | default:"—" }}',
                ],
            ),
            'shortage.detected' => self::emailDefinition(
                key: $notificationTypeKey,
                name: 'Shortage Detected Email',
                subject: 'Shortage Detected — {{ opportunity.reference | default:"Booking" }}',
                eyebrow: 'Shortage',
                intro: 'A stock shortage has been detected on an opportunity.',
                fields: [
                    '- **Product:** {{ shortage.product_name | default:"—" }}',
                    '- **Quantity short:** {{ shortage.quantity | number:0 }}',
                    '- **Opportunity:** {{ opportunity.subject }}',
                ],
            ),
            'shortage.escalated' => self::emailDefinition(
                key: $notificationTypeKey,
                name: 'Shortage Escalated Email',
                subject: 'Shortage Escalated — {{ opportunity.reference | default:"Booking" }}',
                eyebrow: 'Shortage Escalated',
                intro: 'A shortage requires manager attention.',
                fields: [
                    '- **Product:** {{ shortage.product_name | default:"—" }}',
                    '- **Quantity short:** {{ shortage.quantity | number:0 }}',
                ],
            ),
            'shortage.waitlist.matched' => self::emailDefinition(
                key: $notificationTypeKey,
                name: 'Waitlist Match Email',
                subject: 'Stock Available — {{ shortage.product_name | default:"Item" }}',
                eyebrow: 'Waitlist Match',
                intro: 'Stock you were waiting for is now available.',
                fields: [
                    '- **Product:** {{ shortage.product_name | default:"—" }}',
                    '- **Opportunity:** {{ opportunity.subject }}',
                    '- **Reference:** {{ opportunity.reference | default:"—" }}',
                ],
            ),
            'virtual_stock.overrun' => self::emailDefinition(
                key: $notificationTypeKey,
                name: 'Virtual Stock Overrun Email',
                subject: 'Virtual Stock Overrun — {{ virtual_stock_intake.number | default:"Intake" }}',
                eyebrow: 'Virtual Stock Overrun',
                intro: 'Virtual stock is still on hire past its intake window.',
                fields: [
                    '- **Intake:** {{ virtual_stock_intake.number | default:"—" }}',
                    '- **Available until:** {{ virtual_stock_intake.available_until | date }}',
                    '- **Quantity:** {{ virtual_stock_intake.quantity | number:0 }}',
                ],
            ),
            'invoice.created' => self::emailDefinition(
                key: $notificationTypeKey,
                name: 'Invoice Delivery Email',
                subject: 'Invoice {{ invoice.number | default:"" }} from {{ company.name | default:"Us" }}',
                eyebrow: 'Invoice',
                intro: 'Please find your invoice details below.',
                fields: [
                    '- **Invoice number:** {{ invoice.number | default:"—" }}',
                    '- **Total:** {{ invoice.total | currency }}',
                    '- **Due date:** {{ invoice.due_at | date }}',
                ],
            ),
            'invoice.overdue' => self::emailDefinition(
                key: $notificationTypeKey,
                name: 'Invoice Overdue Email',
                subject: 'Overdue Invoice — {{ invoice.number | default:"" }}',
                eyebrow: 'Payment Overdue',
                intro: 'This invoice is now overdue. Please arrange payment at your earliest convenience.',
                fields: [
                    '- **Invoice number:** {{ invoice.number | default:"—" }}',
                    '- **Total:** {{ invoice.total | currency }}',
                    '- **Due date:** {{ invoice.due_at | date }}',
                ],
            ),
            'invoice.payment_received' => self::emailDefinition(
                key: $notificationTypeKey,
                name: 'Payment Received Email',
                subject: 'Payment Received — Invoice {{ invoice.number | default:"" }}',
                eyebrow: 'Payment Received',
                intro: 'Thank you — we have received your payment.',
                fields: [
                    '- **Invoice number:** {{ invoice.number | default:"—" }}',
                    '- **Payment amount:** {{ payment.amount | currency }}',
                    '- **Invoice total:** {{ invoice.total | currency }}',
                ],
            ),
            'invoice.credit_note' => self::emailDefinition(
                key: $notificationTypeKey,
                name: 'Credit Note Email',
                subject: 'Credit Note {{ credit_note.number | default:"" }} — Invoice {{ invoice.number | default:"" }}',
                eyebrow: 'Credit Note',
                intro: 'A credit note has been issued to your account.',
                fields: [
                    '- **Credit note:** {{ credit_note.number | default:"—" }}',
                    '- **Invoice:** {{ invoice.number | default:"—" }}',
                    '- **Amount:** {{ credit_note.total | currency }}',
                ],
            ),
            'statement.emailed' => self::emailDefinition(
                key: $notificationTypeKey,
                name: 'Account Statement Email',
                subject: 'Account Statement from {{ company.name | default:"Us" }}',
                eyebrow: 'Account Statement',
                intro: 'Please find your account statement attached.',
                fields: [
                    '- **Contact:** {{ contact.name | default:"—" }}',
                    '- **Closing balance:** {{ statement.closing_balance | currency }}',
                ],
            ),
            'document.emailed' => self::emailDefinition(
                key: $notificationTypeKey,
                name: 'Document Emailed Notification',
                subject: 'Document {{ document.document_number | default:"" }} from {{ company.name | default:"Us" }}',
                eyebrow: 'Document',
                intro: 'A document has been sent to you.',
                fields: [
                    '- **Type:** {{ document.document_type | title }}',
                    '- **Number:** {{ document.document_number | default:"—" }}',
                ],
            ),
            default => throw new \InvalidArgumentException("Unsupported notification type key [{$notificationTypeKey}]."),
        };
    }

    /**
     * @param  list<string>  $fields
     * @return array{
     *     notification_type_key: string,
     *     slug: string,
     *     name: string,
     *     channel: string,
     *     subject: string|null,
     *     body: string,
     *     attachments_config: array<string, mixed>|null,
     *     eyebrow: string|null
     * }
     */
    private static function emailDefinition(
        string $key,
        string $name,
        string $subject,
        string $eyebrow,
        string $intro,
        array $fields,
    ): array {
        $detailLines = implode("\n", $fields);

        return [
            'notification_type_key' => $key,
            'slug' => str_replace('.', '-', $key).'-email',
            'name' => $name,
            'channel' => 'email',
            'subject' => $subject,
            'body' => "# {$eyebrow}\n\n{$intro}\n\n{$detailLines}\n\nIf you have any questions, please reply to this email.\n\nThank you,\n\n{{ company.name | default:\"The Team\" }}",
            'attachments_config' => null,
            'eyebrow' => $eyebrow,
        ];
    }
}
