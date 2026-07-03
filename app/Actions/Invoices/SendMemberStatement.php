<?php

namespace App\Actions\Invoices;

use App\Actions\Notifications\DispatchBillingCustomerCommunication;
use App\Data\Invoices\SendMemberStatementData;
use App\Enums\BillingCommunicationKind;
use App\Models\Member;
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
        );
    }
}
