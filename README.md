# Reportify 🚀

[![Latest Version on Packagist](https://img.shields.io/packagist/v/saroven/laravel-reportify.svg?style=flat-square)](https://packagist.org/packages/saroven/laravel-reportify)
[![Total Downloads](https://img.shields.io/packagist/dt/saroven/laravel-reportify.svg?style=flat-square)](https://packagist.org/packages/saroven/laravel-reportify)
[![License](https://img.shields.io/packagist/l/saroven/laravel-reportify.svg?style=flat-square)](LICENSE.md)

**Reportify** is a unified, high-performance report generation and document export engine for Laravel applications. Easily stream or export **PDFs (via mPDF)**, **Excel (.xlsx)**, **CSV**, **TXT**, and **ZIP archives** using clean Laravel syntax, event-driven background queues, and customizable Blade templates.

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
use App\Models\User;

class UserController extends Controller implements Reportable
{
    use HasReportify;

    public function index(Request $request)
    {
        if ($request->has('export')) {
            return $this->exportReport($request, 'Users Report', 'reports.users-pdf');
        }

        return view('users.index');
    }

    public function getExportData(array $payload, string $exportType, int|string|null $userId = null): mixed
    {
        return User::query()->where('status', 'active')->get();
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

use Saroven\Reportify\Contracts\Reportable;
use App\Models\User;

class UserExport implements Reportable
{
    public function getExportData(array $payload, string $exportType, int|string|null $userId = null): mixed
    {
        return User::query()
            ->when($payload['role'] ?? null, fn($q, $r) => $q->where('role', $r))
            ->get();
    }
}
```

Dispatch background exports using the generated class name:

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

### 5. Listen to Export Events

`Reportify` dispatches native Laravel events. Register listeners in your `EventServiceProvider`:

```php
namespace App\Listeners;

use Saroven\Reportify\Events\ExportCompleted;
use Saroven\Reportify\Events\ExportFailed;
use Illuminate\Support\Facades\Log;

class HandleReportifyEvents
{
    public function handleExportCompleted(ExportCompleted $event): void
    {
        // $event->userId, $event->title, $event->filePath, $event->exportFormat
        Log::info("Report '{$event->title}' generated successfully at: {$event->filePath}");
    }

    public function handleExportFailed(ExportFailed $event): void
    {
        Log::error("Report '{$event->title}' failed: {$event->errorMessage}");
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
    :pdf="['url' => '#', 'onClick' => 'exportLinkRedirectWithUrlParams(event, {type: `pdfChunk`})']"
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
    // Storage disk where export files are saved (default: 'public')
    'storage_disk' => env('REPORTIFY_STORAGE_DISK', 'public'),

    // Set to true to force inline synchronous execution on servers without queue daemons
    'force_sync' => (bool) env('REPORTIFY_FORCE_SYNC', false),

    // Base directory path for output files
    'export_directory' => 'exports',

    // Batch chunk size for query processing and PDF merging
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

## 🧪 Testing

Run the test suite using Pest PHP:

```bash
vendor/bin/pest
```

---

## 📜 License

The MIT License (MIT). See [LICENSE.md](LICENSE.md) for details.
