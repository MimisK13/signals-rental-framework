<?php

use Symfony\Component\Process\Process;

it('coalesces overlapping editor pulls and preserves rows for acknowledgements', function (): void {
    $process = new Process([
        'node',
        base_path('tests/js/experimental-quotation-editor-sync-runner.mjs'),
    ], base_path());

    $process->mustRun();
    $result = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);

    expect($result)
        ->toMatchArray([
            'calls' => 2,
            'maximumInFlight' => 1,
            'rows' => 1,
            'uploadedRowCounts' => [0, 0],
            'fieldBatchCalls' => 1,
            'submittedFieldMutations' => 1,
        ]);
});

it('coalesces pointer movement and reuses drag measurements', function (): void {
    $process = new Process([
        'node',
        base_path('tests/js/experimental-tree-drag-runner.mjs'),
    ], base_path());

    $process->mustRun();
    $result = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);

    expect($result)
        ->toMatchArray([
            'passed' => true,
            'rectReadCount' => 4,
            'droppedOrder' => [1, 3, 2],
            'persistedOrder' => [13, 11, 12],
        ]);
});
