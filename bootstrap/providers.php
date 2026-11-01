<?php

use App\Domain\Activities\ActivitiesServiceProvider;
use App\Domain\Crew\CrewServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\SettingsServiceProvider;
use App\Providers\VoltServiceProvider;
use Ui\Dense\DenseServiceProvider;

return [
    AppServiceProvider::class,
    SettingsServiceProvider::class,
    CrewServiceProvider::class,
    ActivitiesServiceProvider::class,
    VoltServiceProvider::class,
    DenseServiceProvider::class,
];
