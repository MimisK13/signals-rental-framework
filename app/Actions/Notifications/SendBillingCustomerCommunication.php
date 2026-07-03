<?php

namespace App\Actions\Notifications;

use App\Actions\Documents\GenerateBillingIssueDocument;
use App\Actions\Documents\RecordDocumentEmailDelivery;
use App\Data\Documents\DocumentData;
use App\Data\Notifications\SendBillingCustomerCommunicationData;
use App\Data\Notifications\SendCustomerCommunicationData;
use App\Enums\BillingCommunicationKind;
use App\Models\CreditNote;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Member;
use App\Services\Documents\DocumentTemplateResolver;
use App\Services\Notifications\BillingCommunicationMergeDataBuilder;
use App\Services\Notifications\CommunicationTemplateResolver;
use Illuminate\Support\Facades\Auth;

/**
 * Sends a billing customer communication with an attached PDF document (P38).
 */
class SendBillingCustomerCommunication
{
    public function __construct(
        private readonly BillingCommunicationMergeDataBuilder $mergeDataBuilder,
        private readonly CommunicationTemplateResolver $communicationTemplateResolver,
        private readonly DocumentTemplateResolver $documentTemplateResolver,
        private readonly GenerateBillingIssueDocument $generateBillingIssueDocument,
        private readonly SendCustomerCommunication $sendCustomerCommunication,
        private readonly RecordDocumentEmailDelivery $recordDocumentEmailDelivery,
    ) {}

    public function __invoke(SendBillingCustomerCommunicationData $data): void
    {
        if ($this->shouldSkip($data)) {
            return;
        }

        if ($data->userId !== null && auth()->id() !== $data->userId) {
            Auth::guard('web')->loginUsingId($data->userId);
        }

        $context = $this->resolveContext($data);

        if ($context === null) {
            return;
        }

        [
            'member' => $member,
            'mergeData' => $mergeData,
            'document' => $document,
            'notificationTypeKey' => $notificationTypeKey,
            'eyebrow' => $eyebrow,
        ] = $context;

        $template = $this->communicationTemplateResolver->resolveForNotification(
            $data->storeId,
            $notificationTypeKey,
        );

        if ($template === null) {
            return;
        }

        ($this->sendCustomerCommunication)(new SendCustomerCommunicationData(
            recipient: $member,
            template: $template,
            notificationTypeKey: $notificationTypeKey,
            subject: $member,
            mergeData: $mergeData,
            document: $document,
            eyebrow: $eyebrow,
        ));

        $recipientAddress = $member->routeNotificationForMail();

        if (is_string($recipientAddress) && $recipientAddress !== '') {
            ($this->recordDocumentEmailDelivery)($document, $recipientAddress);
        }
    }

    private function shouldSkip(SendBillingCustomerCommunicationData $data): bool
    {
        if (! $data->explicit) {
            $settingsKey = $data->kind->settingsKey();

            if ($settingsKey !== null && ! (bool) settings("invoices.{$settingsKey}", true)) {
                return true;
            }
        }

        if (! $this->communicationTemplateResolver->hasActiveTemplate(
            $data->storeId,
            $data->kind->notificationTypeKey(),
        )) {
            return true;
        }

        $documentType = $this->documentTypeForKind($data);

        return ! $this->documentTemplateResolver->hasActiveTemplate($data->storeId, $documentType);
    }

    private function documentTypeForKind(SendBillingCustomerCommunicationData $data): string
    {
        if ($data->kind === BillingCommunicationKind::Invoice) {
            $invoice = Invoice::query()->find($data->sourceId);

            if ($invoice === null) {
                return $data->kind->documentType();
            }

            return $invoice->typeEnum()->documentType();
        }

        return $data->kind->documentType();
    }

    /**
     * @return array{
     *     member: Member,
     *     mergeData: array<string, mixed>,
     *     document: Document,
     *     notificationTypeKey: string,
     *     eyebrow: string|null
     * }|null
     */
    private function resolveContext(SendBillingCustomerCommunicationData $data): ?array
    {
        return match ($data->kind) {
            BillingCommunicationKind::Invoice => $this->resolveInvoiceContext($data),
            BillingCommunicationKind::CreditNote => $this->resolveCreditNoteContext($data),
            BillingCommunicationKind::Receipt => $this->resolveReceiptContext($data),
            BillingCommunicationKind::Statement => $this->resolveStatementContext($data),
        };
    }

    /**
     * @return array{
     *     member: Member,
     *     mergeData: array<string, mixed>,
     *     document: Document,
     *     notificationTypeKey: string,
     *     eyebrow: string|null
     * }|null
     */
    private function resolveInvoiceContext(SendBillingCustomerCommunicationData $data): ?array
    {
        $invoice = Invoice::query()->with('member')->find($data->sourceId);

        if ($invoice === null) {
            return null;
        }

        $document = $this->documentModel(
            $this->generateBillingIssueDocument->forInvoice($invoice, $data->userId),
        );

        return [
            'member' => $invoice->member,
            'mergeData' => $this->mergeDataBuilder->forInvoice($invoice),
            'document' => $document,
            'notificationTypeKey' => BillingCommunicationKind::Invoice->notificationTypeKey(),
            'eyebrow' => 'Invoice',
        ];
    }

    /**
     * @return array{
     *     member: Member,
     *     mergeData: array<string, mixed>,
     *     document: Document,
     *     notificationTypeKey: string,
     *     eyebrow: string|null
     * }|null
     */
    private function resolveCreditNoteContext(SendBillingCustomerCommunicationData $data): ?array
    {
        $creditNote = CreditNote::query()->with('invoice.member')->find($data->sourceId);

        if ($creditNote === null) {
            return null;
        }

        $document = $this->documentModel(
            $this->generateBillingIssueDocument->forCreditNote($creditNote, $data->userId),
        );

        return [
            'member' => $creditNote->invoice->member,
            'mergeData' => $this->mergeDataBuilder->forCreditNote($creditNote),
            'document' => $document,
            'notificationTypeKey' => BillingCommunicationKind::CreditNote->notificationTypeKey(),
            'eyebrow' => 'Credit Note',
        ];
    }

    /**
     * @return array{
     *     member: Member,
     *     mergeData: array<string, mixed>,
     *     document: Document,
     *     notificationTypeKey: string,
     *     eyebrow: string|null
     * }|null
     */
    private function resolveReceiptContext(SendBillingCustomerCommunicationData $data): ?array
    {
        $payment = InvoicePayment::query()->with('invoice.member')->find($data->sourceId);

        if ($payment === null || $payment->amount <= 0) {
            return null;
        }

        $document = $this->documentModel(
            $this->generateBillingIssueDocument->forReceipt($payment, $data->userId),
        );

        return [
            'member' => $payment->invoice->member,
            'mergeData' => $this->mergeDataBuilder->forReceipt($payment),
            'document' => $document,
            'notificationTypeKey' => BillingCommunicationKind::Receipt->notificationTypeKey(),
            'eyebrow' => 'Payment Received',
        ];
    }

    /**
     * @return array{
     *     member: Member,
     *     mergeData: array<string, mixed>,
     *     document: Document,
     *     notificationTypeKey: string,
     *     eyebrow: string|null
     * }|null
     */
    private function resolveStatementContext(SendBillingCustomerCommunicationData $data): ?array
    {
        $member = Member::query()->find($data->sourceId);

        if ($member === null) {
            return null;
        }

        $document = $this->documentModel(
            $this->generateBillingIssueDocument->forStatement($member, $data->storeId, $data->userId),
        );

        $closingBalance = Invoice::query()
            ->where('member_id', $member->id)
            ->where('store_id', $data->storeId)
            ->where('amount_outstanding', '>', 0)
            ->whereNotIn('status', ['draft', 'void', 'credited'])
            ->sum('amount_outstanding');

        return [
            'member' => $member,
            'mergeData' => $this->mergeDataBuilder->forStatement($member, (int) $closingBalance),
            'document' => $document,
            'notificationTypeKey' => BillingCommunicationKind::Statement->notificationTypeKey(),
            'eyebrow' => 'Account Statement',
        ];
    }

    private function documentModel(DocumentData $documentData): Document
    {
        return Document::query()->findOrFail($documentData->id);
    }
}
