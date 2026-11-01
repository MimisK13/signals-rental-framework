<?php

use App\Providers\AppServiceProvider;
use App\Providers\SettingsServiceProvider;
use App\Providers\VoltServiceProvider;
use Ui\Dense\DenseServiceProvider;

return [
    AppServiceProvider::class,
    SettingsServiceProvider::class,
    VoltServiceProvider::class,
    DenseServiceProvider::class,
];
