<?php

declare(strict_types=1);

use App\Domain\Administration\Database\Seeders\PermissionSeeder;
use App\Domain\Administration\Database\Seeders\RoleSeeder;
use App\Domain\Administration\Models\User;
use App\Domain\Members\Models\Member;
use App\Domain\Projects\Models\Project;
use App\Domain\Quotations\Actions\AcceptQuotationVersion;
use App\Domain\Quotations\Actions\CreateQuotation;
use App\Domain\Quotations\Data\AcceptQuotationVersionData;
use App\Domain\Quotations\Data\CreateQuotationData;
use App\Domain\Quotations\Models\Quotation;
use App\Domain\Quotations\Models\QuotationItem;
use App\Domain\Quotations\Models\QuotationVersion;
use App\Domain\Quotations\Verbs\Events\QuotationVersionAccepted;
use App\Domain\Stores\Models\Store;
use Illuminate\Console\Application;
use Illuminate\Process\Pool;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Laravel\SerializableClosure\SerializableClosure;
use Tests\Concerns\UsesPostgres;
use Thunk\Verbs\Models\VerbEvent;

uses(UsesPostgres::class)->group('pgsql');

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->actingAs(User::factory()->owner()->create());
});

/** @return list<array<string, mixed>> */
function runConcurrentQuotationAcceptances(int $quotationId, int $versionId, int $userId): array
{
    $closure = function () use ($quotationId, $versionId, $userId): array {
        Auth::loginUsingId($userId);

        return app(AcceptQuotationVersion::class)(
            Quotation::query()->findOrFail($quotationId),
            QuotationVersion::query()->findOrFail($versionId),
            AcceptQuotationVersionData::from([]),
        )->toArray();
    };
    $command = Application::formatCommandString('invoke-serialized-closure');
    $results = Process::pool(function (Pool $pool) use ($closure, $command): void {
        for ($index = 0; $index < 2; $index++) {
            $pool->as((string) $index)->path(base_path())->env([
                'APP_ENV' => 'testing',
                'DB_CONNECTION' => 'pgsql_testing',
                'LARAVEL_INVOKABLE_CLOSURE' => base64_encode(serialize(new SerializableClosure($closure))),
            ])->command($command);
        }
    })->start()->wait();

    return $results->collect()->map(function ($result): array {
        if ($result->failed()) {
            throw new RuntimeException($result->errorOutput());
        }

        $output = $result->output();
        if (($position = strpos($output, "\x1f\x8b")) !== false) {
            $output = substr($output, 0, $position);
        }

        $payload = json_decode($output, true);
        if (! is_array($payload) || ! ($payload['successful'] ?? false)) {
            throw new RuntimeException(is_array($payload) ? ($payload['message'] ?? 'Acceptance failed.') : 'Invalid worker output.');
        }

        return unserialize($payload['result']);
    })->values()->all();
}

it('creates exactly one Project when concurrent acceptance retries target the same version', function (): void {
    $customer = Member::factory()->organisation()->create();
    $store = Store::factory()->create();
    $created = app(CreateQuotation::class)(CreateQuotationData::from([
        'subject' => 'Concurrent acceptance',
        'store_id' => $store->id,
        'member_id' => $customer->id,
        'starts_at' => '2027-11-10T08:00:00Z',
        'ends_at' => '2027-11-12T18:00:00Z',
    ]));
    $quotation = Quotation::query()->findOrFail($created->id);
    $version = QuotationVersion::query()->findOrFail($quotation->active_version_id);
    QuotationItem::factory()->create([
        'quotation_id' => $quotation->id,
        'version_id' => $version->id,
    ]);
    $quotationId = $quotation->id;
    $versionId = $version->id;
    $userId = (int) auth()->id();

    DB::connection('pgsql_testing')->commit();

    $acceptances = collect(runConcurrentQuotationAcceptances($quotationId, $versionId, $userId));

    expect($acceptances)->toHaveCount(2)
        ->and($acceptances->pluck('project_id')->unique()->values()->all())->toHaveCount(1)
        ->and(Project::query()->count())->toBe(1)
        ->and(VerbEvent::query()->where('type', QuotationVersionAccepted::class)->count())->toBe(1);

    DB::connection('pgsql_testing')->beginTransaction();
});
