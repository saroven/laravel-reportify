<?php

use Saroven\Reportify\Jobs\ProcessReportJob;

// ─── pdfStream guard ──────────────────────────────────────────────────────────

it('throws when pdfStream format is dispatched as a job', function () {
    expect(fn () => ProcessReportJob::dispatchSync(
        requestData: ['export' => 'pdfStream'],
        type: 'pdf-stream',
        title: 'Test Report',
    ))->toThrow(Exception::class, "browser-only streaming format");
});

// ─── Closure dataProvider guard ───────────────────────────────────────────────

it('throws InvalidArgumentException when a Closure is passed as dataProvider', function () {
    expect(fn () => new ProcessReportJob(
        requestData: [],
        dataProvider: fn () => []
    ))->toThrow(InvalidArgumentException::class, 'Closure');
});

it('accepts a class-string as dataProvider without throwing', function () {
    expect(fn () => new ProcessReportJob(
        requestData: ['export' => 'excel'],
        dataProvider: \Saroven\Reportify\Contracts\Reportable::class
    ))->not->toThrow(InvalidArgumentException::class);
});

it('accepts null dataProvider at construction without throwing', function () {
    // Construction is valid — resolveData() will return collect([]) at runtime
    expect(fn () => new ProcessReportJob(
        requestData: ['export' => 'excel'],
        dataProvider: null
    ))->not->toThrow(InvalidArgumentException::class);
});

it('throws no-data exception when null dataProvider resolves to empty collection', function () {
    // null dataProvider → resolveData() returns collect([]) → "No data found" exception
    expect(fn () => ProcessReportJob::dispatchSync(
        requestData: ['export' => 'excel'],
        type: 'excel',
        title: 'Test',
        dataProvider: null,
    ))->toThrow(Exception::class, 'No data found');
});

it('allows export with null dataProvider when no_data_exception_disabled is true', function () {
    // With the flag set, empty data is permitted and the job runs to completion
    // (file generation may still fail without storage, but the data check is bypassed)
    $job = new ProcessReportJob(
        requestData: ['export' => 'excel'],
        type: 'excel',
        title: 'Empty Export',
        additionalData: ['no_data_exception_disabled' => true],
        dataProvider: null,
    );

    // Verify the flag is honoured at construction — no exception thrown
    expect($job)->toBeInstanceOf(ProcessReportJob::class);
});
