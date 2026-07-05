<?php

use App\Services\Invoices\CreditNoteStatusRegistry;

describe('CreditNoteStatusRegistry', function () {
    it('assertKnown accepts configured statuses', function () {
        app(CreditNoteStatusRegistry::class)->assertKnown('issued');
    })->throwsNoExceptions();

    it('assertKnown rejects unknown statuses', function () {
        app(CreditNoteStatusRegistry::class)->assertKnown('mystery');
    })->throws(InvalidArgumentException::class, 'Unknown credit note status');
});
