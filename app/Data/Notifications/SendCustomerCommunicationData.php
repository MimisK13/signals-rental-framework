<?php

namespace App\Data\Notifications;

use App\Models\CommunicationTemplate;
use App\Models\Document;
use App\Models\Member;
use Illuminate\Database\Eloquent\Model;
use Spatie\LaravelData\Data;

/**
 * Input for sending a customer communication email (P27).
 */
class SendCustomerCommunicationData extends Data
{
    /**
     * @param  array<string, mixed>  $mergeData
     */
    public function __construct(
        public Member $recipient,
        public CommunicationTemplate $template,
        public string $notificationTypeKey,
        public Model $subject,
        public array $mergeData = [],
        public ?Document $document = null,
        public ?string $eyebrow = null,
        public ?string $preheader = null,
    ) {}
}
