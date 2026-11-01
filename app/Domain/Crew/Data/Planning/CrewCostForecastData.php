<?php

declare(strict_types=1);

namespace App\Domain\Crew\Data\Planning;

use Spatie\LaravelData\Data;

final class CrewCostForecastData extends Data
{
    /**
     * @param  array<string,array{confirmed_minor:int,proposed_minor:int,approved_minor:int,unestimated_assignments:int}>  $currencies
     * @param  array<string, int>  $cancellation_minor
     */
    public function __construct(public int $project_id, public array $currencies, public array $cancellation_minor = []) {}
}
