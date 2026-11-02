<?php

declare(strict_types=1);

namespace App\Domain\Quotations\Services;

use App\Domain\Quotations\Models\QuotationItem;

final class QuotationItemTreeService
{
    private const int SEGMENT_WIDTH = 4;

    public function nextTopLevelPath(int $versionId): string
    {
        return $this->nextSiblingPath($versionId, '');
    }

    public function nextChildPath(int $versionId, string $parentPath): string
    {
        return $this->nextSiblingPath($versionId, $parentPath);
    }

    private function nextSiblingPath(int $versionId, string $parentPrefix): string
    {
        $childLength = strlen($parentPrefix) + self::SEGMENT_WIDTH;
        $max = QuotationItem::query()
            ->where('version_id', $versionId)
            ->when($parentPrefix !== '', fn ($query) => $query->where('path', 'like', $parentPrefix.'%'))
            ->whereRaw('LENGTH(path) = ?', [$childLength])
            ->orderByDesc('path')
            ->value('path');
        $next = $max === null ? 1 : ((int) substr((string) $max, -self::SEGMENT_WIDTH)) + 1;

        return $parentPrefix.str_pad((string) $next, self::SEGMENT_WIDTH, '0', STR_PAD_LEFT);
    }
}
