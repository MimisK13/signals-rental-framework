<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

class DateWindow
{
    public function __construct(
        public CarbonInterface $from,
        public CarbonInterface $to,
        public string $preset,
    ) {}

    public static function fromRequest(string $preset, ?string $from = null, ?string $to = null): self
    {
        $now = now();

        return match ($preset) {
            '24h' => new self($now->copy()->startOfDay(), $now->copy(), '24h'),
            '7d' => new self($now->copy()->subDays(6)->startOfDay(), $now->copy(), '7d'),
            '30d' => new self($now->copy()->subDays(29)->startOfDay(), $now->copy(), '30d'),
            'custom' => self::fromCustomRange($from, $to),
            default => throw new InvalidArgumentException("Unknown date window preset: {$preset}"),
        };
    }

    public function fromDate(): string
    {
        return $this->from->toDateString();
    }

    public function toDate(): string
    {
        return $this->to->toDateString();
    }

    /**
     * @return array{preset: string, from: string, to: string}
     */
    public function toMeta(): array
    {
        return [
            'preset' => $this->preset,
            'from' => $this->from->toIso8601String(),
            'to' => $this->to->toIso8601String(),
        ];
    }

    private static function fromCustomRange(?string $from, ?string $to): self
    {
        if ($from === null || $to === null) {
            throw new InvalidArgumentException('Custom date windows require both from and to dates.');
        }

        return new self(
            Carbon::parse($from)->startOfDay(),
            Carbon::parse($to)->endOfDay(),
            'custom',
        );
    }
}
