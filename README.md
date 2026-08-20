# Reportify 🚀 (`saroven/laravel-reportify`)

**Reportify** is a unified, high-performance report and document export engine for Laravel applications. It encapsulates multi-format exports (**PDF via mPDF**, **Excel & CSV via Laravel Excel (Maatwebsite/Excel)**, **TXT**, and **ZIP archives**) with built-in support for PDF streaming, data chunking, PDF merging, background queue processing, and UI action components.

---

## 📦 Features

- 📑 **Multi-Format Support**: Generate PDF, Excel (.xlsx), CSV, TXT, and ZIP archives.
- ⚡ **Synchronous PDF Streaming**: Directly stream formatted PDF documents in the browser.
- 🔄 **Asynchronous Queued Jobs**: Process heavy exports in background queue jobs (`ProcessExportJob`) with real-time download manager lifecycle hooks (`Processing`, `Completed`, `Failed`).
- 🧩 **PDF Chunking & Merging**: Automatically chunk huge datasets into smaller PDF files and seamlessly merge them using `PDFMerger`.
- 🎨 **Blade Views & PDF Headers/Footers**: Fully customizable company PDF headers, page footers, print dates, authenticated user stamps, and page numbers (`Page X of Y`).
- 🔘 **Blade Action Component**: Standard UI export button dropdown `<x-reportify-export-group>`.

---

## 💻 Installation

Add local path repository or Git repository in your application's `composer.json`:

```json
"repositories": [
    {
        "type": "path",
        "url": "../laravel-reportify"
    }
],
"require": {
    "saroven/laravel-reportify": "dev-main"
}
```

Then run:

```bash
composer update saroven/laravel-reportify
```

Publish configuration file and Blade views (optional):

```bash
php artisan vendor:publish --tag=reportify-config
php artisan vendor:publish --tag=reportify-views
```

---

## 🛠 Generator Command

Generate a stubbed `Reportable` class:

```bash
php artisan reportify:make ClientLedger
```

This creates `app/Exports/ClientLedgerExport.php` implementing `Saroven\Reportify\Contracts\Reportable`:

```php
namespace App\Exports;

use Saroven\Reportify\Contracts\Reportable;

class ClientLedgerExport implements Reportable
{
    public function getExportData(array $payload, string $exportType, int|string|null $userId = null): mixed
    {
        return ClientLedger::query()->get();
    }
}
```

## ⚙️ Configuration (`config/reportify.php`)

```php
return [
    'storage_disk' => env('REPORTIFY_STORAGE_DISK', 'public'),
    'export_directory' => 'exports',
    'chunk_size' => 2000,
    'mpdf' => [
        'default_paper_size' => 'A4',
        'default_orientation' => 'P',
        'author' => env('APP_NAME', 'QFL'),
    ],
    'views' => [
        'pdf_header' => 'reportify::pdf-header',
        'pdf_footer' => 'reportify::pdf-footer',
        'empty_pdf'  => 'reportify::empty-pdf',
    ],
    'download_manager' => [
        'enabled' => true,
        'create_callback' => 'importDownloadManagerCreate',
        'update_callback' => 'importDownloadManagerUpdate',
        'delete_callback' => 'importDownloadManagerDeleteFile',
    ],
];
```

---

## 🚀 Usage

### 1. Make any Controller Exportable (`Reportable` + `HasReportifyExports`)

Simply implement `Reportable` and use `HasReportifyExports` trait on your controller for **1-line export handling**:

```php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Saroven\Reportify\Contracts\Reportable;
use Saroven\Reportify\Traits\HasReportifyExports;

class ClientController extends Controller implements Reportable
{
    use HasReportifyExports;

    public function index(Request $request)
    {
        if ($request->export) {
            return $this->handleReportifyExport($request, 'Client List', 'reports.client-pdf');
        }

        return view('client.index');
    }

    public function getExportData(array $payload, string $exportType, int|string|null $userId = null): mixed
    {
        return Client::query()->where('status', 'active')->get();
    }
}
```

---

### 2. Synchronous PDF Streaming

Stream a generated PDF directly to the browser:

```php
use Saroven\Reportify\Facades\Reportify;

public function index(Request $request)
{
    if ($request->export === 'pdfStream') {
        $data = self::getList($request);
        
        return Reportify::streamPdf(
            request: $request->all(),
            response: $data,
            title: 'Employee Attendance Summary',
            type: 'attendance-summary',
            view: 'hris::attendances.pdf',
            additionalData: ['orientation' => 'L']
        );
    }
    
    return view('hris::attendances.index');
}
```

### 2. Direct File Exports (Excel, CSV, TXT, PDF)

```php
use Saroven\Reportify\Facades\Reportify;

// Export Excel file
$filePath = Reportify::exportExcel($request->all(), $data, 'exports/excel', 'Client Stock Balance', 'reports.stock');

// Export CSV file
$filePath = Reportify::exportCsv($request->all(), $data, 'exports/csv', 'Client Stock Balance');

// Export PDF file
$filePath = Reportify::exportPdf($request->all(), $data, 'exports/pdf', 'Client Stock Balance', 'reports.stock-pdf');

// Export PDF Chunked & Merged
$filePath = Reportify::exportPdfChunk($request->all(), $largeDataCollection, 'exports/pdf', 'Ledger Statement', 'reports.ledger-pdf');
```

### 3. Asynchronous Background Queued Export

Dispatch queued export processing:

```php
use Saroven\Reportify\Jobs\ProcessExportJob;

public function export(Request $request)
{
    ProcessExportJob::dispatch(
        $request->all(),
        'client-ledger-report',
        'Client Ledger Statement',
        auth()->id(),
        'reports.client-ledger-pdf',
        ['orientation' => 'L'],
        fn($payload) => ClientLedgerService::getData($payload)
    );

    return back()->with('success', 'Export process initiated. Check Download Manager.');
}
```

### 4. Blade Action Buttons Component

Render standard export action buttons in your views:

```html
<x-reportify-export-group
    :pdfStream="['url' => '#', 'onClick' => 'exportLinkRedirectWithUrlParams(event, {type: `pdfStream`})']"
    :pdf="['url' => '#', 'onClick' => 'exportLinkRedirectWithUrlParams(event, {type: `pdfChunk`})']"
    :excel="['url' => '#', 'onClick' => 'exportLinkRedirectWithUrlParams(event, {type: `excel`})']"
    :csv="['url' => '#', 'onClick' => 'exportLinkRedirectWithUrlParams(event, {type: `csv`})']"
    :txt="['url' => '#', 'onClick' => 'exportLinkRedirectWithUrlParams(event, {type: `txt`})']"
/>
```

---

## 📜 License

The MIT License (MIT). See License File for details.
