<?php

declare(strict_types=1);

use App\Domain\Administration\Database\Seeders\PermissionSeeder;
use App\Domain\Administration\Models\User;
use App\Domain\Crew\Models\CrewAssignment;
use App\Domain\Crew\Models\CrewMember;
use App\Domain\Crew\Models\CrewRateCard;
use App\Domain\Crew\Models\CrewRequest;
use App\Domain\Crew\Models\CrewRequestLine;
use App\Domain\Crew\Models\CrewTimeEntry;
use App\Domain\Crew\Models\CrewTimesheet;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Process;
use Tests\Concerns\UsesPostgres;

uses(UsesPostgres::class)->group('pgsql');

/**
 * @param  list<int>  $targets
 * @return list<array{ok:bool,exception?:string,message?:string}>
 */
function runCrewConcurrencyWorkers(string $operation, array $targets, int $memberId, int $userId, int $version = 0): array
{
    $connection = DB::connection('pgsql_testing');
    while ($connection->transactionLevel() > 0) {
        $connection->commit();
    }
    $barrier = storage_path('framework/crew-concurrency-'.bin2hex(random_bytes(8)));
    $processes = array_map(static function (int $target) use ($operation, $memberId, $userId, $barrier, $version): Process {
        return new Process([PHP_BINARY, base_path('tests/Pgsql/Support/CrewConcurrencyWorker.php'), $operation, (string) $target, (string) $memberId, (string) $userId, $barrier, (string) $version], base_path(), timeout: 30);
    }, $targets);

    try {
        foreach ($processes as $process) {
            $process->start();
        }
        $ready = array_fill(0, count($processes), false);
        $deadline = microtime(true) + 15;
        while (in_array(false, $ready, true) && microtime(true) < $deadline) {
            foreach ($processes as $index => $process) {
                if (str_contains($process->getIncrementalOutput(), "ready\n")) {
                    $ready[$index] = true;
                }
                if (! $process->isRunning() && ! $ready[$index]) {
                    throw new RuntimeException('Crew concurrency worker failed before the barrier: '.$process->getErrorOutput().$process->getOutput());
                }
            }
            if (in_array(false, $ready, true)) {
                usleep(10000);
            }
        }
        expect($ready)->toBe([true, true]);
        file_put_contents($barrier, 'go');
        $results = [];
        foreach ($processes as $process) {
            $process->wait();
            $lines = array_values(array_filter(explode("\n", trim($process->getOutput()))));
            $results[] = json_decode((string) end($lines), true, flags: JSON_THROW_ON_ERROR);
        }

        return $results;
    } finally {
        if (file_exists($barrier)) {
            unlink($barrier);
        }
        foreach ($processes as $process) {
            if ($process->isRunning()) {
                $process->stop(1);
            }
        }
        $connection->beginTransaction();
    }
}

/** @return array{User,CrewMember,CrewRequestLine} */
function crewConcurrencyFixture(): array
{
    \Pest\Laravel\seed(PermissionSeeder::class);
    $user = User::factory()->create(['is_admin' => true]);
    $user->givePermissionTo(['crew.assignments.create', 'crew.assignments.confirm', 'crew.timesheets.create']);
    $member = CrewMember::factory()->create(['starts_on' => null, 'ends_on' => null]);
    $request = CrewRequest::factory()->open()->create(['starts_at' => '2027-02-10 09:00:00', 'ends_at' => '2027-02-10 17:00:00']);
    $line = CrewRequestLine::factory()->create(['crew_request_id' => $request->id, 'starts_at' => $request->starts_at, 'ends_at' => $request->ends_at]);
    $member->roles()->attach($line->crew_role_id, ['proficiency' => 'competent']);
    CrewRateCard::factory()->forMember($member)->create(['effective_from' => '2027-01-01']);

    return [$user, $member, $line];
}

it('serializes duplicate proposals for the same line and crew member', function (): void {
    [$user, $member, $line] = crewConcurrencyFixture();

    $results = runCrewConcurrencyWorkers('propose', [$line->id, $line->id], $member->id, $user->id);

    expect(collect($results)->where('ok', true))->toHaveCount(1);
    expect(collect($results)->where('exception', ValidationException::class))->toHaveCount(1);
    expect(CrewAssignment::where('crew_request_line_id', $line->id)->count())->toBe(1);
});

it('serializes overlapping confirmations across independent requests for the same member', function (): void {
    [$user, $member, $firstLine] = crewConcurrencyFixture();
    $otherRequest = CrewRequest::factory()->open()->create(['starts_at' => '2027-02-10 09:00:00', 'ends_at' => '2027-02-10 17:00:00']);
    $otherLine = CrewRequestLine::factory()->create(['crew_request_id' => $otherRequest->id, 'crew_role_id' => $firstLine->crew_role_id, 'starts_at' => $otherRequest->starts_at, 'ends_at' => $otherRequest->ends_at]);
    $first = CrewAssignment::factory()->forLine($firstLine)->forMember($member)->invited()->create();
    $second = CrewAssignment::factory()->forLine($otherLine)->forMember($member)->invited()->create();

    $results = runCrewConcurrencyWorkers('confirm', [$first->id, $second->id], $member->id, $user->id);

    expect(collect($results)->where('ok', true))->toHaveCount(1);
    expect(collect($results)->where('exception', ValidationException::class))->toHaveCount(1);
    expect(CrewAssignment::whereIn('id', [$first->id, $second->id])->where('status', 'confirmed')->count())->toBe(1);
});

it('rejects the second concurrent timesheet edit using a stale version', function (): void {
    [$user, $member, $line] = crewConcurrencyFixture();
    $assignment = CrewAssignment::factory()->forLine($line)->forMember($member)->confirmed()->create();
    $sheet = CrewTimesheet::factory()->create(['crew_assignment_id' => $assignment->id, 'crew_member_id' => $member->id, 'project_id' => $assignment->project_id, 'store_id' => $assignment->store_id, 'period_start' => '2027-02-10', 'period_end' => '2027-02-10', 'version' => 0]);
    $entry = CrewTimeEntry::factory()->create(['crew_timesheet_id' => $sheet->id, 'work_date' => '2027-02-10', 'started_at' => '2027-02-10 09:00:00', 'ended_at' => '2027-02-10 17:00:00']);

    $results = runCrewConcurrencyWorkers('time_entry', [$entry->id, $entry->id], $member->id, $user->id, 0);

    expect(collect($results)->where('ok', true))->toHaveCount(1);
    expect(collect($results)->where('exception', ValidationException::class))->toHaveCount(1);
    expect($sheet->refresh()->version)->toBe(1);
    expect($entry->refresh()->minutes)->toBe(420);
});
