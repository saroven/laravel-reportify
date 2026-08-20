# Reportify 🚀 (`saroven/laravel-reportify`)

**Reportify** is a unified, high-performance report and document export engine for Laravel applications. It encapsulates multi-format exports (**PDF via mPDF**, **Excel & CSV via Laravel Excel**, **TXT**, and **ZIP archives**) with built-in support for PDF streaming, data chunking, PDF merging, event-driven background queue processing, and UI action components.

---

## 📦 Key Features

- 📑 **Multi-Format Export Engine**: Generate PDF, Excel (.xlsx), CSV, TXT, and ZIP packages.
- ⚡ **Synchronous PDF Streaming**: Directly stream formatted PDF documents in the browser.
- 🔄 **Event-Driven Asynchronous Queues**: Process heavy exports in background queue jobs (`ProcessReportJob`) with native Laravel Events (`ExportStarted`, `ExportCompleted`, `ExportFailed`).
- 🧩 **PDF Chunking & Merging**: Automatically chunk huge datasets into smaller PDF files and seamlessly merge them using `PDFMerger`.
- 🎨 **Blade Views & Customizable Headers/Footers**: Configurable company PDF headers, page footers, print dates, user stamps, and page numbers (`Page X of Y`).
- 🛠 **Artisan Generator Command**: `php artisan reportify:make {name}` generates clean `Reportable` export classes.
- 🎮 **Exportable Controller Trait**: `HasReportify` trait enables 1-line export handling in your Laravel controllers (`$this->exportReport()`).
- 🔘 **Blade Action Component**: Standard UI export button dropdown `<x-reportify-buttons />` and helper scripts `<x-reportify-scripts />`.

---

## 💻 Installation & Setup

### Option 1: Standard Installation (GitHub / Packagist)

If the package is published on Packagist or hosted on GitHub:

```bash
composer require saroven/laravel-reportify
```

---

### Option 2: Local Development & Testing (Path Repository)

If you are developing or testing the package locally on your computer alongside another Laravel application, add a `path` repository to your app's `composer.json`:

```json
"repositories": [
    {
        "type": "path",
        "url": "../laravel-reportify"
    }
]
```

Then run:

```bash
composer require saroven/laravel-reportify:dev-main
```

### 2. Publish Config & Views (Optional)

```bash
php artisan vendor:publish --tag=reportify-config
php artisan vendor:publish --tag=reportify-views
```

---

## ⚙️ Configuration Reference (`config/reportify.php`)

```php
return [
    // Storage disk where export files are saved (default: 'public')
    'storage_disk' => env('REPORTIFY_STORAGE_DISK', 'public'),

    // Set to true for client environments without background queue worker daemons
    'force_sync' => (bool) env('REPORTIFY_FORCE_SYNC', false),

    // Export output base folder relative to storage disk
    'export_directory' => 'exports',

    // Default query chunk size for batch processing and PDF chunk merging
    'chunk_size' => (int) env('REPORTIFY_CHUNK_SIZE', 2000),

    // mPDF engine configuration
    'mpdf' => [
        'backtrack_limit' => '1000000000',
        'recursion_limit' => '1000000000',
        'default_paper_size' => 'A4',
        'default_orientation' => 'P',
        'author' => env('APP_NAME', 'Laravel'),
    ],

    // Default Blade templates
    'views' => [
        'pdf_header' => 'reportify::pdf-header',
        'pdf_footer' => 'reportify::pdf-footer',
        'empty_pdf'  => 'reportify::empty-pdf',
    ],
];
```

---

## 📚 Complete Usage Examples

### Example 1: Make any Controller Exportable (`Reportable` + `HasReportify`)

Simply implement `Reportable` and use `HasReportify` trait on your controller for **1-line export handling**:

```php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Saroven\Reportify\Contracts\Reportable;
use Saroven\Reportify\Traits\HasReportify;
use App\Models\Client;

class ClientController extends Controller implements Reportable
{
    use HasReportify; // ◄ Provides $this->exportReport() and $this->streamReport()

    public function index(Request $request)
    {
        if ($request->export) {
            return $this->exportReport($request, 'Client List', 'reports.client-pdf');
        }

        return view('client.index');
    }

    // Resolves export data automatically:
    public function getExportData(array $payload, string $exportType, int|string|null $userId = null): mixed
    {
        return Client::query()
            ->when($payload['status'] ?? null, fn($q, $s) => $q->where('status', $s))
            ->get();
    }
}
```

---

### Example 2: Dedicated Export Classes (`php artisan reportify:make`)

Generate a dedicated `Reportable` export class:

```bash
php artisan reportify:make ClientLedger
```

This creates `app/Exports/ClientLedgerExport.php`:

```php
namespace App\Exports;

use Saroven\Reportify\Contracts\Reportable;
use App\Models\ClientLedger;

class ClientLedgerExport implements Reportable
{
    public function getExportData(array $payload, string $exportType, int|string|null $userId = null): mixed
    {
        return ClientLedger::query()
            ->where('client_id', $payload['client_id'])
            ->whereBetween('date', [$payload['from_date'], $payload['to_date']])
            ->get();
    }
}
```

Dispatch using the class name:

```php
use App\Exports\ClientLedgerExport;
use Saroven\Reportify\Jobs\ProcessReportJob;

public function export(Request $request)
{
    ProcessReportJob::dispatch(
        requestData: $request->all(),
        type: 'client-ledger',
        title: 'Client Ledger Statement',
        user: auth()->id(),
        view: 'reports.client-ledger-pdf',
        additionalData: ['orientation' => 'L'],
        dataProvider: ClientLedgerExport::class // ◄ Resolved via Laravel Container
    );

    return back()->with('success', 'Ledger export started! Check your download notifications.');
}
```

---

### Example 3: Inline Closures & Callback Resolution

For quick reports where you don't need a dedicated class:

```php
use Saroven\Reportify\Jobs\ProcessReportJob;
use App\Models\Attendance;

public function export(Request $request)
{
    ProcessReportJob::dispatch(
        requestData: $request->all(),
        type: 'attendance-summary',
        title: 'Attendance Summary',
        user: auth()->id(),
        view: 'hris::attendances.pdf',
        additionalData: [],
        dataProvider: fn($payload) => Attendance::whereDate('date', $payload['date'])->get() // ◄ Inline Closure
    );

    return back()->with('success', 'Export queued successfully!');
}
```

---

### Example 4: Synchronous PDF Streaming

Stream a generated PDF directly to the browser for inline preview or printing:

```php
use Saroven\Reportify\Facades\Reportify;
// or use global helper: reportify()

public function print(Request $request)
{
    $data = Client::where('status', 'active')->get();

    return Reportify::streamPdf(
        request: $request->all(),
        response: $data,
        title: 'Active Clients Printout',
        type: 'active-clients',
        view: 'reports.client-pdf',
        additionalData: [
            'orientation' => 'P',
            'paper_size' => 'A4',
            'headerHtml' => '<h2>Company Active Clients List</h2>'
        ]
    );
}
```

---

### Example 5: PDF Chunking & Merging for Large Datasets

When exporting tens of thousands of rows into PDF, chunking avoids mPDF memory limits:

```php
use Saroven\Reportify\Facades\Reportify;

public function exportHeavyPdf(Request $request)
{
    $largeDataCollection = ClientLedger::query()->lazy(); // or Chunked collection

    $filePath = Reportify::exportPdfChunk(
        request: $request->all(),
        response: $largeDataCollection,
        context: 'exports/ledgers',
        title: 'Annual Ledger Report',
        view: 'reports.ledger-pdf',
        additionalData: ['orientation' => 'L']
    );

    return response()->download(storage_path("app/public/{$filePath}"));
}
```

---

### Example 6: Direct Multi-Format Exports (Excel, CSV, TXT, ZIP)

Export directly using the `Reportify` Facade or `reportify()` global helper:

```php
use Saroven\Reportify\Facades\Reportify;

// 1. Export Excel (.xlsx)
$excelPath = Reportify::exportExcel($request->all(), $data, 'exports/excel', 'Stock Balance', 'reports.stock');

// 2. Export CSV (.csv)
$csvPath = Reportify::exportCsv($request->all(), $data, 'exports/csv', 'Stock Balance');

// 3. Export Text File (.txt with custom separator)
$txtPath = Reportify::exportTxt($request->all(), $data, 'exports/txt', 'Stock Balance', null, ['separator' => '|']);

// 4. Export Chunked ZIP Package (.zip)
$zipPath = Reportify::prepareZip('pdf', $request->all(), $largeData, 'exports/zips', 'All Statements', 'reports.statement-pdf');
```

---

### Example 7: Event Listener for Download Management (`ExportCompleted` Event)

`Reportify` dispatches pure Laravel events. Register an event listener in your app's `EventServiceProvider`:

```php
namespace App\Listeners;

use Saroven\Reportify\Events\ExportCompleted;
use Saroven\Reportify\Events\ExportFailed;
use App\Models\DownloadManager;

class HandleReportifyEvents
{
    public function handleExportCompleted(ExportCompleted $event): void
    {
        // 1. Record completed export in your database table
        DownloadManager::create([
            'user_id'   => $event->userId,
            'title'     => $event->title,
            'file_path' => $event->filePath,
            'format'    => $event->exportFormat,
            'status'    => 'Completed',
        ]);

        // 2. Send email or WebSocket notification
        // broadcast(new UserExportReady($event->userId, $event->filePath));
    }

    public function handleExportFailed(ExportFailed $event): void
    {
        Log::error("Export '{$event->title}' failed: {$event->errorMessage}");
    }
}
```

---

### Example 8: Blade Action Buttons Component

Embed standard export dropdown action buttons in your views:

```html
<!-- Use any tag alias: <x-reportify-buttons />, <x-reportify-actions />, or <x-reportify-export-group /> -->
<x-reportify-buttons
    :pdfStream="['url' => '#', 'onClick' => 'exportLinkRedirectWithUrlParams(event, {type: `pdfStream`})']"
    :pdf="['url' => '#', 'onClick' => 'exportLinkRedirectWithUrlParams(event, {type: `pdfChunk`})']"
    :excel="['url' => '#', 'onClick' => 'exportLinkRedirectWithUrlParams(event, {type: `excel`})']"
    :csv="['url' => '#', 'onClick' => 'exportLinkRedirectWithUrlParams(event, {type: `csv`})']"
    :txt="['url' => '#', 'onClick' => 'exportLinkRedirectWithUrlParams(event, {type: `txt`})']"
/>
```

---

### Example 9: Master Blade Layout Script Setup (`<x-reportify-scripts />`)

Add `<x-reportify-scripts />` to the bottom of your master Blade layout file (e.g. `layouts/app.blade.php`) to enable global query parameter URL redirection:

```html
    <!-- Master Blade Layout -->
    @yield('content')

    <x-reportify-scripts />
</body>
</html>
```

---

## 📜 License

The MIT License (MIT). See License File for details.
