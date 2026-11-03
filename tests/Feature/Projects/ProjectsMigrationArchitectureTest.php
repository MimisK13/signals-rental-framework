<?php

declare(strict_types=1);

it('keeps every Projects migration scoped to one table', function (): void {
    $migrationFiles = glob(app_path('Domain/Projects/Database/Migrations/*.php')) ?: [];

    expect($migrationFiles)->not->toBeEmpty();

    foreach ($migrationFiles as $migrationFile) {
        $contents = file_get_contents($migrationFile);

        if (! is_string($contents)) {
            throw new RuntimeException("Unable to read {$migrationFile}.");
        }

        preg_match_all("/Schema::(?:create|table|dropIfExists)\\('([^']+)'/", $contents, $matches);
        $tables = array_values(array_unique($matches[1] ?? []));

        expect($tables)
            ->toHaveCount(1, basename($migrationFile).' must target exactly one table.');
    }
});
