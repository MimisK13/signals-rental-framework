<?php

declare(strict_types=1);

use App\Domain\SupplierCosts\Actions\CreateSupplierCost;
use App\Domain\SupplierCosts\Actions\SetPreferredSupplier;
use App\Domain\SupplierCosts\Data\CreateSupplierCostData;
use App\Domain\SupplierCosts\Models\ProductSupplierCost;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 3).'/vendor/autoload.php';

$app = require dirname(__DIR__, 3).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
config(['database.default' => 'pgsql_testing', 'queue.default' => 'sync', 'broadcasting.default' => 'null']);
DB::setDefaultConnection('pgsql_testing');
[$script, $operation, $target, $product, $supplier, $actor, $barrier] = $argv;
Auth::login(User::query()->findOrFail((int) $actor));

fwrite(STDOUT, "ready\n");
fflush(STDOUT);

try {
    $deadline = microtime(true) + 15;
    while (! file_exists($barrier)) {
        if (microtime(true) > $deadline) {
            throw new RuntimeException('Supplier cost concurrency barrier timed out.');
        }
        usleep(1000);
    }

    match ($operation) {
        'create' => (new CreateSupplierCost)(new CreateSupplierCostData(
            product_id: (int) $product,
            supplier_id: (int) $supplier,
            cost_basis: 'per_unit',
            cost: 5000,
            currency_code: 'GBP',
        )),
        'prefer' => (new SetPreferredSupplier)(ProductSupplierCost::query()->findOrFail((int) $target)),
        default => throw new RuntimeException('Unsupported supplier cost concurrency operation.'),
    };

    fwrite(STDOUT, json_encode(['ok' => true], JSON_THROW_ON_ERROR)."\n");
} catch (Throwable $exception) {
    fwrite(STDOUT, json_encode([
        'ok' => false,
        'exception' => $exception::class,
        'message' => $exception->getMessage(),
    ], JSON_THROW_ON_ERROR)."\n");
}
