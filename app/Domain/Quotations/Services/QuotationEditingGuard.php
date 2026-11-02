<?php

declare(strict_types=1);

namespace App\Domain\Quotations\Services;

use App\Domain\Quotations\Enums\QuotationVersionStatus;
use App\Domain\Quotations\Models\Quotation;
use App\Domain\Quotations\Models\QuotationVersion;
use Illuminate\Validation\ValidationException;

final class QuotationEditingGuard
{
    public function assertEditable(Quotation $quotation, QuotationVersion $version): void
    {
        $this->assertDraftVersion($quotation, $version);

        if ($quotation->deal_total !== null) {
            throw ValidationException::withMessages(['deal_total' => 'Clear the deal price before editing quotation items.']);
        }
    }

    public function assertDraftVersion(Quotation $quotation, QuotationVersion $version): void
    {
        if ($quotation->status->isTerminal()) {
            throw ValidationException::withMessages(['quotation' => 'A terminal quotation cannot be edited.']);
        }

        if ($version->quotation_id !== $quotation->id || $quotation->active_version_id !== $version->id || ! $version->is_active) {
            throw ValidationException::withMessages(['version_id' => 'Only the active quotation version can be edited.']);
        }

        if ($version->status !== QuotationVersionStatus::Draft) {
            throw ValidationException::withMessages(['version_id' => 'Create or activate a draft version before editing items.']);
        }
    }
}
