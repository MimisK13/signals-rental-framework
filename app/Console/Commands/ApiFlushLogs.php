<?php

namespace App\Console\Commands;

use App\Services\Api\UsageRecorder;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'api:flush-logs')]
class ApiFlushLogs extends Command
{
    protected $signature = 'api:flush-logs';

    protected $description = 'Flush buffered API metering entries into persistent storage';

    public function handle(UsageRecorder $usageRecorder): int
    {
        $total = 0;

        do {
            $flushed = $usageRecorder->flush();
            $total += $flushed;
        } while ($flushed > 0);

        $this->components->info("Flushed {$total} buffered API log entries.");

        return self::SUCCESS;
    }
}
