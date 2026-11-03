<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

it('keeps every migration scoped to at most one table', function (): void {
    $roots = [
        database_path('migrations'),
        app_path('Domain'),
    ];

    foreach ($roots as $root) {
        foreach (File::allFiles($root) as $file) {
            if (! str_contains($file->getPath(), 'Migrations')) {
                continue;
            }

            $contents = $file->getContents();
            preg_match_all('/Schema::create\(/', $contents, $createMatches);
            preg_match_all("/Schema::(?:create|table|dropIfExists|rename)\\('([^']+)'/", $contents, $tableMatches);
            $literalTables = array_values(array_unique($tableMatches[1]));

            expect(count($createMatches[0]))
                ->toBeLessThanOrEqual(1, "{$file->getFilename()} creates multiple tables.")
                ->and(count($literalTables))
                ->toBeLessThanOrEqual(1, "{$file->getFilename()} targets multiple tables: ".implode(', ', $literalTables));
        }
    }
});
