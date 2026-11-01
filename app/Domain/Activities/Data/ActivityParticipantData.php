<?php

declare(strict_types=1);

namespace App\Domain\Activities\Data;

use App\Domain\Activities\Models\ActivityParticipant;
use Spatie\LaravelData\Data;

final class ActivityParticipantData extends Data
{
    public function __construct(
        public int $id,
        public int $member_id,
        public string $member_name,
        public bool $mute,
    ) {}

    public static function fromModel(ActivityParticipant $participant): self
    {
        return new self(
            id: $participant->id,
            member_id: $participant->member_id,
            member_name: $participant->member->name ?? '',
            mute: $participant->mute,
        );
    }
}
