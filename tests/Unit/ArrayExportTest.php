<?php

use Saroven\Reportify\Exports\ArrayExport;

// ─── headings() — array_key_first() instead of reset() ───────────────────────

it('returns correct headings from the first row keys', function () {
    $export = new ArrayExport([
        ['id' => 1, 'name' => 'Alice', 'email' => 'alice@example.com'],
        ['id' => 2, 'name' => 'Bob',   'email' => 'bob@example.com'],
    ]);

    expect($export->headings())->toBe(['id', 'name', 'email']);
});

it('returns empty headings for empty data', function () {
    $export = new ArrayExport([]);

    expect($export->headings())->toBe([]);
});

it('returns Value heading for scalar row data', function () {
    $export = new ArrayExport(['row one', 'row two']);

    expect($export->headings())->toBe(['Value']);
});

it('does not mutate internal array pointer — headings is stable across multiple calls', function () {
    $export = new ArrayExport([
        ['col' => 'A'],
        ['col' => 'B'],
        ['col' => 'C'],
    ]);

    $first  = $export->headings();
    $second = $export->headings();

    expect($first)->toBe($second)->toBe(['col']);
});

// ─── array() output ───────────────────────────────────────────────────────────

it('converts collection to array for export preserving row values', function () {
    $export = new ArrayExport(collect([
        ['id' => 1, 'name' => 'Alice'],
        ['id' => 2, 'name' => 'Bob'],
    ]));

    $rows = $export->array();

    expect($rows)->toHaveCount(2);
    // ArrayExport preserves associative keys — values are what matter for Excel
    expect(array_values($rows[0]))->toBe([1, 'Alice']);
    expect(array_values($rows[1]))->toBe([2, 'Bob']);
});

it('strips nested array values from rows', function () {
    $export = new ArrayExport([
        ['name' => 'Alice', 'meta' => ['nested' => 'data']],
    ]);

    $rows = $export->array();

    // 'meta' is stripped; only scalar 'name' remains
    expect($rows[0])->not->toHaveKey('meta');
    expect(array_values($rows[0]))->toBe(['Alice']);
});
