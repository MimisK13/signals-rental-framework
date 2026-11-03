<?php

declare(strict_types=1);

namespace App\Domain\Invoices\Actions;

use App\Domain\Communications\Actions\DispatchBillingCustomerCommunication;
use App\Domain\Communications\Enums\BillingCommunicationKind;
use App\Domain\Invoices\Data\SendMemberStatementData;
use App\Domain\Members\Models\Member;
use Illuminate\Support\Facades\Gate;

/**
 * Generates and emails a member account statement document (P38).
 *
 * Delivery is queued; template/document guards run in the delivery job so a
 * store without statement templates no-ops cleanly.
 */
class SendMemberStatement
{
    public function __invoke(Member $member, SendMemberStatementData $data): void
    {
        Gate::authorize('sendCommunication', $member);

        app(DispatchBillingCustomerCommunication::class)(
            BillingCommunicationKind::Statement,
            (int) $member->id,
            $data->store_id,
            explicit: true,
        );
    }
}
