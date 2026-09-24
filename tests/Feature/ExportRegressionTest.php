<?php

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Saroven\Reportify\Contracts\Reportable;
use Saroven\Reportify\ReportifyService;
use Saroven\Reportify\Traits\HasReportify;

beforeEach(function () {
    Storage::fake('public');
    config(['reportify.storage_disk' => 'public']);
});

it('keeps page number and print date on the merged footer of a chunked PDF', function () {
    config(['reportify.chunk_size' => 2]);
    $footerData = [];
    View::composer('reportify::pdf-footer', function ($view) use (&$footerData) {
        $footerData[] = $view->getData();
    });

    $rows = collect(range(1, 5))->map(fn (int $i) => ['name' => "Row {$i}"]);

    $path = (new ReportifyService())->exportPdfChunk([], $rows, 'exports', 'Chunked', null, []);

    expect($path)->not->toBeNull();
    Storage::disk('public')->assertExists($path);

    $finalFooter = end($footerData);
    expect($finalFooter['hide_page_number'])->toBeFalse()
        ->and($finalFooter['hide_print_date'])->toBeFalse();
});

it('still honours a caller\'s own hide flags on a chunked PDF footer', function () {
    config(['reportify.chunk_size' => 2]);
    $footerData = [];
    View::composer('reportify::pdf-footer', function ($view) use (&$footerData) {
        $footerData[] = $view->getData();
    });

    $rows = collect(range(1, 3))->map(fn (int $i) => ['name' => "Row {$i}"]);

    (new ReportifyService())->exportPdfChunk([], $rows, 'exports', 'Chunked', null, ['hidePageNumber' => true]);

    expect(end($footerData)['hide_page_number'])->toBeTrue();
});

it('writes TXT exports with a heading line and readable separator', function () {
    $rows = [['Date' => '2026-01-01', 'Amount' => 100], ['Date' => '2026-01-02', 'Amount' => 250]];

    $path = (new ReportifyService())->exportTxt([], $rows, 'exports', 'Ledger');

    expect(Storage::disk('public')->get($path))->toBe("Date | Amount\n2026-01-01 | 100\n2026-01-02 | 250\n");
});

it('uses a custom TXT separator and skips headings for plain list rows', function () {
    $path = (new ReportifyService())->exportTxt([], [[1, 2], [3, 4]], 'exports', 'Plain', null, ['separator' => ',']);

    expect(Storage::disk('public')->get($path))->toBe("1,2\n3,4\n");
});

it('says a queued export is being processed, not that it finished', function () {
    config(['queue.default' => 'database']);
    \Illuminate\Support\Facades\Queue::fake();

    $controller = new class implements Reportable {
        use HasReportify;

        public function getExportData(array $payload, string $exportType, int|string|null $userId = null): mixed
        {
            return [['id' => 1]];
        }
    };

    $request = \Illuminate\Http\Request::create('/export', 'GET', ['export' => 'csv']);
    app()->instance('request', $request);
    app('session')->setDefaultDriver('array');
    $request->setLaravelSession(app('session')->driver());

    $controller->exportReport($request, 'Ledger');

    expect(session('success'))->toBe("Export for 'Ledger' is being processed. Check Download Manager.");
});
