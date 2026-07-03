<?php

namespace App\Data\Invoices;

use Spatie\LaravelData\Attributes\Validation\Exists;
use Spatie\LaravelData\Data;

/**
 * Input for generating and emailing a member statement (P38).
 */
class SendMemberStatementData extends Data
{
    public function __construct(
        #[Exists('members', 'id')]
        public int $member_id,
        #[Exists('stores', 'id')]
        public int $store_id,
    ) {}
}
