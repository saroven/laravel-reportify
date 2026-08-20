<?php

use Illuminate\Support\Facades\Event;
use Saroven\Reportify\Events\ExportStarted;
use Saroven\Reportify\Events\ExportCompleted;
use Saroven\Reportify\Events\ExportFailed;

it('dispatches ExportStarted event', function () {
    Event::fake();

    ExportStarted::dispatch(1, 'Client List', 'excel', ['status' => 'active']);

    Event::assertDispatched(ExportStarted::class, function ($event) {
        return $event->title === 'Client List' && $event->exportFormat === 'excel';
    });
});

it('dispatches ExportCompleted event', function () {
    Event::fake();

    ExportCompleted::dispatch(1, 'Client List', 'excel', 'exports/clients.xlsx', []);

    Event::assertDispatched(ExportCompleted::class, function ($event) {
        return $event->filePath === 'exports/clients.xlsx';
    });
});

it('dispatches ExportFailed event', function () {
    Event::fake();

    ExportFailed::dispatch(1, 'Client List', 'excel', 'Memory limit exceeded', []);

    Event::assertDispatched(ExportFailed::class, function ($event) {
        return $event->errorMessage === 'Memory limit exceeded';
    });
});
