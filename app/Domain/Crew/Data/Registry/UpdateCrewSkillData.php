<?php

declare(strict_types=1);

namespace App\Domain\Crew\Data\Registry;

use Spatie\LaravelData\Data;

final class UpdateCrewSkillData extends Data
{
    public function __construct(public string $name,
        public ?string $slug = null,
        public string $category = 'general',
        public ?string $description = null,
        public bool $is_active = true) {}
}
