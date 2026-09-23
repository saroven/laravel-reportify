<?php

use Saroven\Reportify\ReportifyService;

// Access the private method via Reflection
function callGetModifiedResponse(mixed $response, array $additionalData = []): array
{
    $service = new ReportifyService();
    $method  = new ReflectionMethod(ReportifyService::class, 'getModifiedResponse');
    $method->setAccessible(true);
    return $method->invoke($service, $response, $additionalData);
}

// ─── plain array with _data key ───────────────────────────────────────────────

it('extracts _data from a plain array response', function () {
    $response = ['_data' => [['id' => 1], ['id' => 2]]];
    [$data, $extra] = callGetModifiedResponse($response);

    expect($data)->toBe([['id' => 1], ['id' => 2]]);
});

it('merges _additionalData from a plain array response', function () {
    $response = [
        '_data'           => [['id' => 1]],
        '_additionalData' => ['headerMargin' => 50],
    ];
    [$data, $extra] = callGetModifiedResponse($response, ['title' => 'Test']);

    expect($extra['headerMargin'])->toBe(50);
    expect($extra['title'])->toBe('Test');
});

// ─── Collection passthrough ───────────────────────────────────────────────────

it('returns a Collection as-is when it has no _data key', function () {
    $collection = collect([['id' => 1], ['id' => 2]]);
    [$data, $extra] = callGetModifiedResponse($collection);

    expect($data)->toBe($collection);
});

it('does not extract _data from a Collection even if a _data key exists', function () {
    // A Collection with a literal '_data' key — must NOT be extracted
    $collection = collect(['_data' => ['should', 'not', 'be', 'extracted']]);
    [$data, $extra] = callGetModifiedResponse($collection);

    // data must be the full Collection, not the inner array
    expect($data)->toBe($collection);
});

// ─── plain array without _data key ───────────────────────────────────────────

it('returns a plain array as-is when no _data key is present', function () {
    $response = [['id' => 1], ['id' => 2]];
    [$data, $extra] = callGetModifiedResponse($response);

    expect($data)->toBe($response);
});

// ─── additionalData passthrough ───────────────────────────────────────────────

it('passes through original additionalData unchanged when no _additionalData key', function () {
    $response = ['_data' => []];
    [$data, $extra] = callGetModifiedResponse($response, ['paper_size' => 'A3']);

    expect($extra)->toBe(['paper_size' => 'A3']);
});
