<?php

declare(strict_types=1);

namespace App\Domain\System\Services;

use Illuminate\Support\Env;
use Illuminate\Support\Facades\Artisan;
use Throwable;

final class InfrastructureEnvironmentWriter
{
    /** @param array<string, string> $variables */
    public function write(array $variables): void
    {
        $environmentPath = app()->basePath('.env');
        $backup = file_get_contents($environmentPath);

        try {
            Env::writeVariables($variables, $environmentPath, overwrite: true);
            Artisan::call('config:clear');
        } catch (Throwable $exception) {
            if ($backup !== false) {
                file_put_contents($environmentPath, $backup);
                Artisan::call('config:clear');
            }

            report($exception);

            throw $exception;
        }
    }
}
