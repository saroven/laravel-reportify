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
        // 1. Export Started -> Record initial 'processing' status
        Event::listen(function (ExportStarted $event) {
            Download::create([
                'user_id' => $event->userId ?: null,
                'title' => $event->title,
                'format' => strtoupper($event->exportFormat),
                'status' => 'processing',
            ]);
        });

        // 2. Export Completed -> Update status to 'completed' with file path
        Event::listen(function (ExportCompleted $event) {
            $download = Download::where('title', $event->title)
                ->where('format', strtoupper($event->exportFormat))
                ->where('status', 'processing')
                ->latest('id')
                ->first();

            if ($download) {
                $download->update([
                    'file_path' => $event->filePath,
                    'status' => 'completed',
                ]);
            } else {
                Download::create([
                    'user_id' => $event->userId ?: null,
                    'title' => $event->title,
                    'format' => strtoupper($event->exportFormat),
                    'file_path' => $event->filePath,
                    'status' => 'completed',
                ]);
            }
        });

        // 3. Export Failed -> Update status to 'failed' with error details
        Event::listen(function (ExportFailed $event) {
            $download = Download::where('title', $event->title)
                ->where('format', strtoupper($event->exportFormat))
                ->where('status', 'processing')
                ->latest('id')
                ->first();

            if ($download) {
                $download->update([
                    'status' => 'failed',
                    'error' => $event->errorMessage,
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
