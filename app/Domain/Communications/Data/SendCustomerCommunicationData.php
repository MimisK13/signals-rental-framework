<?php

declare(strict_types=1);

namespace App\Domain\Communications\Data;

use App\Domain\Communications\Models\CommunicationTemplate;
use App\Domain\Documents\Models\Document;
use App\Domain\Members\Models\Member;
use Illuminate\Database\Eloquent\Model;
use Spatie\LaravelData\Data;

/**
 * Input for sending a customer communication email (P27).
 */
final class SendCustomerCommunicationData extends Data
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
