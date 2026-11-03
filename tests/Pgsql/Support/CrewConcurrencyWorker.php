<?php

declare(strict_types=1);

use App\Domain\Administration\Models\User;
use App\Domain\Crew\Actions\Assignments\ConfirmCrewAssignment;
use App\Domain\Crew\Actions\Assignments\ProposeCrewAssignment;
use App\Domain\Crew\Actions\Timesheets\UpdateCrewTimeEntry;
use App\Domain\Crew\Data\Assignments\ProposeCrewAssignmentData;
use App\Domain\Crew\Data\Timesheets\UpdateCrewTimeEntryData;
use App\Domain\Crew\Models\CrewAssignment;
use App\Domain\Crew\Models\CrewRequestLine;
use App\Domain\Crew\Models\CrewTimeEntry;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 3).'/vendor/autoload.php';

$app = require dirname(__DIR__, 3).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
config(['database.default' => 'pgsql_testing', 'queue.default' => 'sync', 'broadcasting.default' => 'null']);
DB::setDefaultConnection('pgsql_testing');
[$script, $operation, $target, $member, $actor, $barrier, $version] = $argv;
Auth::login(User::query()->findOrFail((int) $actor));

fwrite(STDOUT, "ready\n");
fflush(STDOUT);

try {
    $deadline = microtime(true) + 15;
    while (! file_exists($barrier)) {
        if (microtime(true) > $deadline) {
            throw new RuntimeException('Crew concurrency barrier timed out.');
        }
        usleep(1000);
    }

    match ($operation) {
        'propose' => app(ProposeCrewAssignment::class)(CrewRequestLine::findOrFail((int) $target), new ProposeCrewAssignmentData((int) $member)),
        'confirm' => app(ConfirmCrewAssignment::class)(CrewAssignment::findOrFail((int) $target)),
        'time_entry' => app(UpdateCrewTimeEntry::class)(CrewTimeEntry::findOrFail((int) $target), new UpdateCrewTimeEntryData('2027-02-10T09:00:00Z', '2027-02-10T16:00:00Z', version: (int) $version)),
        default => throw new RuntimeException('Unsupported Crew concurrency operation.'),
    };
    fwrite(STDOUT, json_encode(['ok' => true], JSON_THROW_ON_ERROR)."\n");
} catch (Throwable $exception) {
    fwrite(STDOUT, json_encode(['ok' => false, 'exception' => $exception::class, 'message' => $exception->getMessage()], JSON_THROW_ON_ERROR)."\n");
}
