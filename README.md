# Reportify 🚀

[![Latest Version on Packagist](https://img.shields.io/packagist/v/saroven/laravel-reportify.svg?style=flat-square)](https://packagist.org/packages/saroven/laravel-reportify)
[![Total Downloads](https://img.shields.io/packagist/dt/saroven/laravel-reportify.svg?style=flat-square)](https://packagist.org/packages/saroven/laravel-reportify)
[![License](https://img.shields.io/packagist/l/saroven/laravel-reportify.svg?style=flat-square)](LICENSE.md)
[![Demo Repository](https://img.shields.io/badge/Demo%20Repo-laravel--reportify--demo-blue?style=flat-square&logo=github)](https://github.com/saroven/laravel-reportify-demo)

**Reportify** is a unified, high-performance report generation and document export engine for Laravel applications. Easily stream or export **PDFs (via mPDF)**, **Excel (.xlsx)**, **CSV**, **TXT**, and **ZIP archives** using clean Laravel syntax, event-driven background queues, and customizable Blade templates.

---

## 🎮 Demo Application

A complete working demo showing User Directory exports, PDF streaming, and a full Download Manager lifecycle implementation is available at:

👉 **[https://github.com/saroven/laravel-reportify-demo](https://github.com/saroven/laravel-reportify-demo)**

```bash
# Clone and run the demo locally
git clone https://github.com/saroven/laravel-reportify-demo.git
cd laravel-reportify-demo
composer install
php artisan migrate:fresh --seed
php artisan serve
```

---

## 📦 Features

- 📑 **Multi-Format Export Engine**: Generate PDF, Excel (.xlsx), CSV, TXT, and ZIP packages.
- ⚡ **Synchronous PDF Streaming**: Directly stream formatted PDF documents in the browser tab.
- 🔄 **Event-Driven Background Queues**: Offload heavy exports to queue workers with native Laravel events (`ExportStarted`, `ExportCompleted`, `ExportFailed`).
- 🧩 **PDF Chunking & Merging**: Automatically chunk large datasets into smaller PDF files and merge them via `PDFMerger`.
- 🎨 **Customizable Blade Templates**: Configurable PDF headers, page footers, print dates, authenticated user stamps, and page numbers (`Page X of Y`).
- 🛠 **Artisan Generator Command**: `php artisan reportify:make {name}` generates clean `Reportable` export classes.
- 🎮 **Exportable Controller Trait**: `HasReportify` trait enables 1-line export handling in controllers (`$this->exportReport()`).
- 🔘 **Blade UI Component**: Drop-in export action buttons `<x-reportify-buttons />` and helper scripts `<x-reportify-scripts />`.

---

## 📖 About

This package started from a recurring problem: every Laravel project with reporting needs ends up with the same scattered code — mPDF calls in controllers, memory crashes on large datasets, separate queue jobs per format, and footer/header logic copied between files.

Reportify pulls all of that into one place. You define what data to export via a `Reportable` class, pick a format, and the rest is handled — chunking for large PDFs, queuing, event dispatching, Blade templates for headers and footers, and ZIP packaging. The same interface works for every format, so there's nothing new to learn when you add a second export type to a controller.

A few things worth knowing before you start:

- `pdfChunk` is what you want for anything over a few thousand rows. It splits the data, renders parts separately, then merges them — so mPDF never has to hold the full dataset in memory.
- Exports are queued by default. If your server doesn't run a queue worker, set `REPORTIFY_FORCE_SYNC=true` and everything runs inline.
- The `Reportable` interface has one method. If your controller already has the data, you can implement it directly on the controller and skip the separate export class entirely.
- Header margins are auto-calculated from your header HTML. If the result is off, `headerMargin` and `additionalHeaderMargin` in `$additionalData` let you correct it per-report without touching the config.

---

## ⚡ Installation

Install the package via Composer:

```bash
composer require saroven/laravel-reportify
```

Publish the configuration file and Blade views (optional):

```bash
php artisan vendor:publish --tag=reportify-config
php artisan vendor:publish --tag=reportify-views
```

---

## 🚀 Quick Start

### 1. Make any Controller Exportable

Implement the `Reportable` interface and use the `HasReportify` trait on your controller:

```php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Saroven\Reportify\Contracts\Reportable;
use Saroven\Reportify\Traits\HasReportify;
use App\Exports\UserExport;
use App\Models\User;

class UserController extends Controller implements Reportable
{
    use HasReportify;

    public function index(Request $request)
    {
        // Intercept export requests (e.g. ?export=pdfStream or ?export=excel)
        if ($request->has('export')) {
            $view = in_array($request->get('export'), ['pdfStream', 'pdf']) ? 'reports.users-pdf' : null;
            return $this->exportReport($request, 'User Directory Report', view: $view, dataProvider: UserExport::class);
        }

        $users = User::latest('id')->paginate(10);
        return view('users.index', compact('users'));
    }

    public function getExportData(array $payload, string $exportType, int|string|null $userId = null): mixed
    {
        return User::query()->get();
    }
}
```

---

### 2. Generate Dedicated Export Classes

Generate a dedicated `Reportable` export class using the Artisan generator command:

```bash
php artisan reportify:make UserExport
```

This creates `app/Exports/UserExport.php`:

```php
namespace App\Exports;

use App\Models\User;
use Saroven\Reportify\Contracts\Reportable;

class UserExport implements Reportable
{
    public function getExportData(array $payload, string $exportType, int|string|null $userId = null): mixed
    {
        $query = User::query();

        if (!empty($payload['search'])) {
            $search = $payload['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        // Return clean mapped attributes for spreadsheet and plain text exports
        return $query->latest('id')->get()->map(function (User $user) {
            return [
                'ID' => $user->id,
                'Name' => $user->name,
                'Email' => $user->email,
                'Role' => $user->role,
                'Department' => $user->department ?? '-',
                'Phone' => $user->phone ?? '-',
                'Status' => $user->status,
                'Created At' => $user->created_at ? $user->created_at->format('Y-m-d H:i:s') : '-',
            ];
        });
    }
}
```

Dispatch background exports manually using `ProcessReportJob`:

```php
use App\Exports\UserExport;
use Saroven\Reportify\Jobs\ProcessReportJob;

public function export(Request $request)
{
    ProcessReportJob::dispatch(
        requestData: $request->all(),
        type: 'users-report',
        title: 'Users Export',
        user: auth()->id(),
        view: 'reports.users-pdf',
        additionalData: ['orientation' => 'L'],
        dataProvider: UserExport::class
    );

    return back()->with('success', 'Export process started successfully.');
}
```

---

### 3. Synchronous PDF Streaming

Stream a generated PDF directly to the browser for inline preview or printing:

```php
use Saroven\Reportify\Facades\Reportify;

public function print(Request $request)
{
    $users = User::where('status', 'active')->get();

    return Reportify::streamPdf(
        request: $request->all(),
        response: $users,
        title: 'Active Users List',
        type: 'active-users',
        view: 'reports.users-pdf',
        additionalData: [
            'orientation' => 'P',
            'paper_size' => 'A4',
            'headerHtml' => '<h2>Active Users Report</h2>'
        ]
    );
}
```

---

### 4. PDF Chunking & Large Dataset Processing (`pdfChunk`)

When exporting large datasets (e.g. 5,000 to 50,000+ records), rendering everything in a single mPDF memory buffer can trigger memory limit crashes or mPDF backtrack errors. Reportify solves this with **PDF Chunking & Merging**:

```php
use Saroven\Reportify\Facades\Reportify;

// Export large dataset by automatically chunking & merging PDF parts
$pdfPath = Reportify::exportPdfChunk(
    request: $request->all(),
    response: $largeUserCollection,
    context: 'exports/pdf',
    title: 'Large User Directory Export',
    view: 'reports.users-pdf',
    additionalData: ['orientation' => 'P']
);
```

#### In Controllers via `HasReportify`:
Simply pass `?export=pdfChunk` in request parameters:

```php
if ($request->has('export')) {
    $view = in_array($request->get('export'), ['pdfStream', 'pdf', 'pdfChunk']) ? 'reports.users-pdf' : null;
    return $this->exportReport($request, 'User Directory Report', view: $view, dataProvider: UserExport::class);
}
```

#### How PDF Chunking Works:
1. Splits records into batches based on `config('reportify.chunk_size', 2000)`.
2. Generates standalone PDF parts in temporary storage without memory overflow.
3. Merges all chunked PDF parts into a single output PDF using mPDF's page template importer.
4. Automatically cleans up temporary chunk files from storage.

---

### 4. Direct Multi-Format Exports

Export directly using the `Reportify` Facade or `reportify()` global helper:

```php
use Saroven\Reportify\Facades\Reportify;

// Export Excel (.xlsx)
$excelPath = Reportify::exportExcel($request->all(), $data, 'exports/excel', 'Users List', 'reports.users-table');

// Export CSV (.csv)
$csvPath = Reportify::exportCsv($request->all(), $data, 'exports/csv', 'Users List');

// Export Text File (.txt with custom delimiter)
$txtPath = Reportify::exportTxt($request->all(), $data, 'exports/txt', 'Users List', null, ['separator' => '|']);

// Export Multi-Part ZIP Package (.zip)
$zipPath = Reportify::prepareZip('pdf', $request->all(), $largeData, 'exports/zips', 'User Statements', 'reports.statement-pdf');
```

---

### 5. Listen to Export Events (Building a Download Manager)

`Reportify` dispatches native Laravel events during the export processing lifecycle (`ExportStarted`, `ExportCompleted`, `ExportFailed`). You can listen to these events in `AppServiceProvider.php` to track job statuses and build a Download Manager:

```php
namespace App\Providers;

use App\Models\Download;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Event;
use Saroven\Reportify\Events\ExportStarted;
use Saroven\Reportify\Events\ExportCompleted;
use Saroven\Reportify\Events\ExportFailed;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // 1. Export Started -> Record initial 'processing' status with unique exportId
        Event::listen(function (ExportStarted $event) {
            Download::create([
                'export_id' => $event->exportId,
                'user_id'   => $event->userId ?: null,
                'title'     => $event->title,
                'format'    => strtoupper($event->exportFormat),
                'status'    => 'processing',
            ]);
        });

        // 2. Export Completed -> Update status to 'completed' matched by unique exportId
        Event::listen(function (ExportCompleted $event) {
            $download = $event->exportId
                ? Download::where('export_id', $event->exportId)->first()
                : Download::where('title', $event->title)->where('status', 'processing')->latest('id')->first();

            if ($download) {
                $download->update([
                    'file_path' => $event->filePath,
                    'status'    => 'completed',
                ]);
            }
        });

        // 3. Export Failed -> Update status to 'failed' with error details
        Event::listen(function (ExportFailed $event) {
            $download = $event->exportId
                ? Download::where('export_id', $event->exportId)->first()
                : Download::where('title', $event->title)->where('status', 'processing')->latest('id')->first();

            if ($download) {
                $download->update([
                    'status' => 'failed',
                    'error'  => $event->errorMessage,
                ]);
            }
        });
    }
}
```

Then create a `DownloadController` to serve the generated files:

```php
namespace App\Http\Controllers;

use App\Models\Download;
use Illuminate\Support\Facades\Storage;

class DownloadController extends Controller
{
    public function index()
    {
        $downloads = Download::latest('id')->paginate(10);
        return view('downloads.index', compact('downloads'));
    }

    public function download(Download $download)
    {
        $disk = config('reportify.storage_disk', 'public');
        return Storage::disk($disk)->download($download->file_path);
    }
}
```

---

### 6. Add Export Buttons & Scripts to Blade Layouts

Include drop-in action buttons in your views:

```html
<!-- Render export dropdown buttons -->
<x-reportify-buttons
    :pdfStream="['url' => '#', 'onClick' => 'exportLinkRedirectWithUrlParams(event, {type: `pdfStream`})']"
    :pdf="['url' => '#', 'onClick' => 'exportLinkRedirectWithUrlParams(event, {type: `pdf`})']"
    :excel="['url' => '#', 'onClick' => 'exportLinkRedirectWithUrlParams(event, {type: `excel`})']"
    :csv="['url' => '#', 'onClick' => 'exportLinkRedirectWithUrlParams(event, {type: `csv`})']"
    :txt="['url' => '#', 'onClick' => 'exportLinkRedirectWithUrlParams(event, {type: `txt`})']"
/>
```

Include `<x-reportify-scripts />` in your master layout template (`layouts/app.blade.php`) for automatic query parameter preservation:

```html
    @yield('content')

    <x-reportify-scripts />
</body>
</html>
```

---

## ⚙️ Configuration Reference (`config/reportify.php`)

```php
return [
    'storage_disk'     => env('REPORTIFY_STORAGE_DISK', 'public'),
    'force_sync'       => (bool) env('REPORTIFY_FORCE_SYNC', false),
    'export_directory' => 'exports',
    'chunk_size'       => (int) env('REPORTIFY_CHUNK_SIZE', 2000),

    'mpdf' => [
        'backtrack_limit'       => '1000000000',
        'recursion_limit'       => '1000000000',
        'default_paper_size'    => 'A4',
        'default_orientation'   => 'P',
        'author'                => env('APP_NAME', 'Laravel'),
        'default_header_margin' => 28,
    ],

    'views' => [
        'pdf_header' => 'reportify::pdf-header',
        'pdf_footer' => 'reportify::pdf-footer',
        'empty_pdf'  => 'reportify::empty-pdf',
    ],
];
```

---

## 🗂 `$additionalData` Reference

All export methods accept an `$additionalData` array for per-report customisation. The most commonly used keys:

| Key | Type | Description |
|-----|------|-------------|
| `filename` | `string` | Custom output filename (without extension) |
| `export_id` | `string` | Unique tracking UUID for the export job (auto-generated if omitted) |
| `file_dir` | `string` | Override the output directory |
| `orientation` | `string` | PDF orientation: `'P'` (portrait) or `'L'` (landscape) |
| `paper_size` | `string` | mPDF paper size, e.g. `'A4'`, `'A3'`, `'Letter'` |
| `headerHtml` | `string` | Raw HTML string injected into the PDF header |
| `headerMargin` | `int` | **Hard override** for the PDF top margin in mm — skips auto-calculation entirely |
| `additionalHeaderMargin` | `int` | **Additive nudge** added on top of the auto-calculated (or overridden) margin. Accepts negative values |
| `hidePdfHeader` | `bool` | Hide the PDF header entirely |
| `hidePdfFooter` | `bool` | Hide the PDF footer entirely |
| `hidePageNumber` | `bool` | Hide page number in footer |
| `hidePrintDate` | `bool` | Hide print date in footer |
| `hidePrintBy` | `bool` | Hide "printed by" user stamp in footer |
| `hidePoweredBy` | `bool` | Hide "Powered by Reportify" line in footer |
| `hideVersionNumber` | `bool` | Hide version number in footer |
| `additionalFooter` | `string` | Extra HTML appended to the PDF footer |
| `separator` | `string` | Column delimiter for TXT exports (default: `~`) |
| `extension` | `string` | File extension for TXT exports (default: `txt`). Use `'none'` for no extension |
| `no_data_exception_disabled` | `bool` | Allow export to proceed with an empty dataset instead of throwing |
| `data_chunk_size` | `int` | Override chunk size for this export only |

### Header Margin Priority

For PDF exports the top margin is resolved in this order:

```
additionalData['headerMargin']          → 1. Hard override (replaces auto-calc)
    ↓ if not set
determineHeaderMargin($headerHtml)      → 2. Auto-calculated from HTML tag count
    ↓ (base for the calculation)
config('reportify.mpdf.default_header_margin', 28)  → 3. Global config default

+ additionalData['additionalHeaderMargin']  → Always added last (default 0)
```

**Examples:**

```php
// Let Reportify auto-calculate but nudge everything 10mm lower
Reportify::exportPdf($request->all(), $data, 'exports/pdf', 'Invoice', 'reports.invoice', [
    'headerHtml'             => '<div><h2>Company</h2><p>Dhaka</p></div>',
    'additionalHeaderMargin' => 10,
]);

// Hard-set a fixed 45mm top margin (skip auto-calculation)
Reportify::exportPdf($request->all(), $data, 'exports/pdf', 'Report', 'reports.main', [
    'headerMargin' => 45,
]);

// Global default for all reports (config/reportify.php)
'mpdf' => [
    'default_header_margin' => 35,
],
```

---

## 🌐 API Controller Support

`HasReportify::exportReport()` automatically detects whether the request expects JSON and returns the appropriate response — no extra configuration needed:

```php
// Web request  → redirect back with flash message
// API request  → JSON: { "message": "Export for 'X' is being processed...", "export_id": "uuid" }
return $this->exportReport($request, 'User Report', dataProvider: UserExport::class);
```

Override `reportifyExportResponse()` in your controller for fully custom behaviour:

```php
protected function reportifyExportResponse(string $title): mixed
{
    return response()->json([
        'status'  => 'queued',
        'message' => "'{$title}' export is queued.",
    ]);
}
```

---

## 🏢 Multi-Tenancy Support

Reportify works seamlessly with multi-tenant Laravel packages including **`stancl/tenancy`** and **`spatie/laravel-multitenancy`**. Because `ProcessReportJob` implements Laravel's standard `ShouldQueue` interface and avoids storing Eloquent models in its constructor, tenant contexts are automatically preserved across queued export workers without serialization errors.

### `stancl/tenancy`

When using `stancl/tenancy`, background exports are automatically tenant-aware via the `QueueTenancyBootstrapper`:

1. **Context Preservation**: The current `tenant_id` is captured on dispatch and restored before `handle()` executes.
2. **Database Queues**: Ensure your `jobs` table uses a central database connection:
   ```php
   'connections' => [
       'database' => [
           'driver' => 'database',
           'table' => 'jobs',
           'queue' => 'default',
           'retry_after' => 90,
           'connection' => 'central',
       ],
   ],
   ```
3. **Storage Isolation**: If `FilesystemTenancyBootstrapper` is enabled, disks are automatically scoped. Alternatively, isolate paths using `$additionalData['file_dir']`:
   ```php
   return $this->exportReport(
       $request,
       'Sales Report',
       additionalData: [
           'file_dir' => 'exports/' . tenant('id'),
       ],
       dataProvider: SalesExport::class
   );
   ```
4. **Event Listeners**: If your `downloads` table is in the central database, wrap event listeners in `tenancy()->central()`:
   ```php
   Event::listen(function (ExportCompleted $event) {
       tenancy()->central(function () use ($event) {
           Download::where('export_id', $event->exportId)->update([
               'file_path' => $event->filePath,
               'status'    => 'completed',
           ]);
       });
   });
   ```

### `spatie/laravel-multitenancy`

`spatie/laravel-multitenancy` works out of the box when queue tenant awareness is enabled in `config/multitenancy.php`:

```php
'queues_are_tenant_aware_by_default' => true,
```

- When enabled, Spatie captures the current tenant on dispatch and calls `$tenant->makeCurrent()` before job execution.
- If automatic awareness is disabled, pass the tenant ID via `$additionalData` and call `$tenant->makeCurrent()` inside `getExportData()`.
- Scope export directories per tenant using `$additionalData['file_dir']`:
   ```php
   return $this->exportReport(
       $request,
       'Sales Report',
       additionalData: [
           'file_dir' => 'exports/' . Tenant::current()->id,
       ],
       dataProvider: SalesExport::class
   );
   ```

### Comparison Matrix

| Feature | `stancl/tenancy` | `spatie/laravel-multitenancy` |
|---|---|---|
| **Queue Tenant Awareness** | Automatic via `QueueTenancyBootstrapper` | Automatic when `queues_are_tenant_aware_by_default => true` |
| **Model Serialization Risk** | None (`ProcessReportJob` uses scalar IDs) | None (`ProcessReportJob` uses scalar IDs) |
| **Storage Scoping** | Built-in via filesystem bootstrapper | Handled via `$additionalData['file_dir']` |

---

## 🧪 Testing

Run the test suite using Pest PHP:

```bash
vendor/bin/pest
```

53 tests, 94 assertions.

---

## 💳 Credits
- Special thanks to [S M Iftakhairul](https://github.com/smiftakhairul) for architecture & design inspiration.

---

## 📜 License

The MIT License (MIT). See [LICENSE.md](LICENSE.md) for details.
