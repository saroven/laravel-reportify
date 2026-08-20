<?php

declare(strict_types=1);

namespace Saroven\Reportify;

use Exception;
use Clegginabox\PDFMerger\PDFMerger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Excel as ExcelType;
use Maatwebsite\Excel\Facades\Excel;
use Saroven\Reportify\Exports\ArrayExport;
use Saroven\Reportify\Exports\ViewExport;
use Saroven\Reportify\PDF\PdfEngine;
use ZipArchive;

class ReportifyService
{
    private mixed $authUser;
    private mixed $authUserInfo;
    private string $fileUniqueHash;
    private int $chunkSize;
    private bool $hideNoDataException;
    private string $disk;

    public function __construct(array $data = [], string|int|null $user = null)
    {
        $this->disk = (string) config('reportify.storage_disk', 'public');
        $this->authUser = $user ?? auth()->user()?->id ?? 0;
        
        $userModel = config('auth.providers.users.model', '\App\Models\User');
        $this->authUserInfo = ($this->authUser && class_exists($userModel)) ? $userModel::find($this->authUser) : null;
        
        $this->fileUniqueHash = now()->format('Ymd-His-u') . '-' . strtolower(str()->random(6));
        $this->chunkSize = (int)($data['data_chunk_size'] ?? config('reportify.chunk_size', 2000));
        $this->hideNoDataException = (bool)($data['no_data_exception_disabled'] ?? false);
    }

    public function exportExcel(
        array $request, Collection|array $response,
        string $context, string $title, ?string $view = null,
        array $additionalData = []
    ): ?string {
        try {
            [$response, $additionalData] = $this->getModifiedResponse($response, $additionalData);
            $this->makeDirectory($context);
            $fileName = ($additionalData['filename'] ?? (str()->slug($title) . '-' . $this->fileUniqueHash)) . '.xlsx';
            $filePath = "{$context}/{$fileName}";

            $exportable = $view 
                ? new ViewExport($view, $request, $response, $additionalData)
                : new ArrayExport($response);

            Excel::store($exportable, "{$this->disk}/{$filePath}", null, ExcelType::XLSX);

            return $filePath;
        } catch (Exception $e) {
            Log::error('Reportify Excel Export Error: ' . $e->getMessage(), ['exception' => $e]);
            return null;
        }
    }

    public function exportExcelZip(
        array $request, Collection|array $response,
        string $context, string $title, ?string $view = null,
        array $additionalData = []
    ): ?string {
        return $this->prepareZip('excel', $request, $response, $context, $title, $view, $additionalData);
    }

    public function exportCsv(
        array $request, Collection|array $response,
        string $context, string $title, ?string $view = null,
        array $additionalData = []
    ): ?string {
        try {
            [$response, $additionalData] = $this->getModifiedResponse($response, $additionalData);
            $this->makeDirectory($context);
            $fileName = ($additionalData['filename'] ?? (str()->slug($title) . '-' . $this->fileUniqueHash)) . '.csv';
            $filePath = "{$context}/{$fileName}";

            $exportable = $view 
                ? new ViewExport($view, $request, $response, $additionalData)
                : new ArrayExport($response);

            Excel::store($exportable, "{$this->disk}/{$filePath}", null, ExcelType::CSV);

            return $filePath;
        } catch (Exception $e) {
            Log::error('Reportify CSV Export Error: ' . $e->getMessage(), ['exception' => $e]);
            return null;
        }
    }

    public function exportCsvZip(
        array $request, Collection|array $response,
        string $context, string $title, ?string $view = null,
        array $additionalData = []
    ): ?string {
        return $this->prepareZip('csv', $request, $response, $context, $title, $view, $additionalData);
    }

    public function exportTxt(
        array $request, Collection|array $response,
        string $context, string $title, ?string $view = null,
        array $additionalData = []
    ): ?string {
        try {
            [$response, $additionalData] = $this->getModifiedResponse($response, $additionalData);
            $this->makeDirectory($context);
            $extension = $additionalData['extension'] ?? 'txt';
            $extension = $extension === 'none' ? '' : ".{$extension}";
            $fileName = ($additionalData['filename'] ?? (str()->slug($title) . '-' . $this->fileUniqueHash)) . $extension;
            $filePath = "{$context}/{$fileName}";

            if ($view) {
                $txtData = strip_tags(view($view, compact('response', 'request', 'additionalData'))->render());
            } else {
                $separator = $additionalData['separator'] ?? '~';
                $txtData = collect($response)
                    ->map(function (mixed $data) use ($separator): string {
                        if (is_object($data)) {
                            $data = (array) $data;
                        }
                        if (!is_array($data)) {
                            return (string) $data;
                        }
                        $cleanData = array_filter($data, fn($v): bool => !is_array($v) && !is_object($v));
                        return implode($separator, $cleanData);
                    })
                    ->implode("\n") . "\n";
            }

            Storage::disk($this->disk)->put($filePath, $txtData);
            return $filePath;
        } catch (Exception $e) {
            Log::error('Reportify TXT Export Error: ' . $e->getMessage(), ['exception' => $e]);
            return null;
        }
    }

    public function exportTxtZip(
        array $request, Collection|array $response,
        string $context, string $title, ?string $view = null,
        array $additionalData = []
    ): ?string {
        return $this->prepareZip('txt', $request, $response, $context, $title, $view, $additionalData);
    }

    public function exportPdf(
        array $request, Collection|array $response,
        string $context, string $title, string $view,
        array $additionalData = []
    ): ?string {
        try {
            [$response, $additionalData] = $this->getModifiedResponse($response, $additionalData);
            $this->makeDirectory($context);
            $fileName = ($additionalData['filename'] ?? (str()->slug($title) . '-' . $this->fileUniqueHash)) . '.pdf';

            $pdf = (new PdfEngine())
                ->loadView($view, compact('response', 'request', 'additionalData'))
                ->setPaper(
                    $additionalData['paper_size'] ?? config('reportify.mpdf.default_paper_size', 'A4'),
                    $additionalData['orientation'] ?? config('reportify.mpdf.default_orientation', 'P')
                );

            $headerMargin = 5;
            if (!($additionalData['hidePdfHeader'] ?? false)) {
                $headerHtml = $additionalData['headerHtml'] ?? '';
                $headerMargin = $this->determineHeaderMargin($headerHtml);
                $pdf->loadHeader(config('reportify.views.pdf_header', 'reportify::pdf-header'), [
                    'header_html' => $headerHtml,
                ]);
            }

            $bottomMargin = 2;
            if (!($additionalData['hidePdfFooter'] ?? false)) {
                $bottomMargin = 15;
                $pdf->loadFooter(config('reportify.views.pdf_footer', 'reportify::pdf-footer'), [
                    'hide_page_number' => $additionalData['hidePageNumber'] ?? false,
                    'hide_print_date' => $additionalData['hidePrintDate'] ?? false,
                    'hide_print_by' => $additionalData['hidePrintBy'] ?? false,
                    'hide_powered_by' => $additionalData['hidePoweredBy'] ?? false,
                    'hide_version_number' => $additionalData['hideVersionNumber'] ?? false,
                    'additional_footer' => $additionalData['additionalFooter'] ?? '',
                    'authUserInfo' => $this->authUserInfo,
                ]);
            }

            return $pdf->setPageMargins(5, 5, $headerMargin, $bottomMargin)->export($fileName, $context);
        } catch (Exception $e) {
            Log::error('Reportify PDF Export Error: ' . $e->getMessage(), ['exception' => $e]);
            return null;
        }
    }

    public function exportPdfZip(
        array $request, Collection|array $response,
        string $context, string $title, string $view,
        array $additionalData = []
    ): ?string {
        return $this->prepareZip('pdf', $request, $response, $context, $title, $view, $additionalData);
    }

    public function exportPdfChunk(
        array $request, Collection|array $response,
        string $context, string $title, string $view,
        array $additionalData = []
    ): ?string {
        try {
            $this->makeDirectory($context);
            $customFileName = $additionalData['filename'] ?? null;
            $fileName = ($customFileName ?? (str()->slug($title) . '-' . $this->fileUniqueHash)) . '.pdf';
            $chunkedFiles = [];

            [$response, $additionalData] = $this->getModifiedResponse($response, $additionalData);
            $responseChunks = collect($response)->chunk($this->chunkSize);

            $additionalData['hidePrintDate'] = true;
            $additionalData['hidePrintBy'] = true;
            $additionalData['hidePoweredBy'] = true;
            $additionalData['hidePageNumber'] = true;
            $additionalData['hideVersionNumber'] = true;

            foreach ($responseChunks as $index => $responseChunk) {
                $part = $index + 1;
                $additionalData['showHeader'] = ($index === 0);
                $additionalData['firstLoop'] = ($index === 0);
                $additionalData['lastLoop'] = ($index === $responseChunks->count() - 1);
                $additionalData['chunkLastSl'] = $index * $this->chunkSize;

                if ($customFileName) {
                    $additionalData['filename'] = str()->slug("{$customFileName} (Part {$part})") . '-' . $this->fileUniqueHash;
                }

                $chunkAdditionalData = array_merge($additionalData, [
                    'hidePdfFooter' => true,
                    'bottom_margin' => 10,
                ]);

                $chunkedFile = $this->exportPdf($request, $responseChunk, 'exports/chunks', "{$title} (Part {$part})", $view, $chunkAdditionalData);
                
                if (empty($chunkedFile)) {
                    if (!empty($chunkedFiles)) {
                        Storage::disk($this->disk)->delete($chunkedFiles);
                    }
                    throw new Exception("File part {$part} could not be saved.");
                }
                
                $chunkedFiles[] = $chunkedFile;
            }

            if (!$this->hideNoDataException && empty($chunkedFiles)) {
                throw new Exception('No chunked PDF files were generated.');
            }

            if (empty($chunkedFiles)) {
                $chunkAdditionalData = array_merge($additionalData, [
                    'hidePdfFooter' => true,
                    'bottom_margin' => 10,
                ]);
                $emptyView = config('reportify.views.empty_pdf', 'reportify::empty-pdf');
                $chunkedFiles[] = $this->exportPdf($request, [], 'exports/chunks', 'Empty PDF', $emptyView, $chunkAdditionalData);
            }

            $filePath = $this->mergePdfFiles($chunkedFiles, $context, $fileName, $additionalData);
            
            if (!($additionalData['hidePdfFooter'] ?? false)) {
                $filePath = $this->modifyPdfFooter($context, $fileName, $additionalData);
            }
            
            return $filePath;
        } catch (Exception $e) {
            Log::error('Reportify PDF Chunk Export Error: ' . $e->getMessage(), ['exception' => $e]);
            return null;
        }
    }

    public function streamPdf(
        array $request,
        Collection|array $response,
        string $title,
        string $type,
        string $view,
        array $additionalData = []
    ): void {
        [$response, $additionalData] = $this->getModifiedResponse($response, $additionalData);
        $fileName = ($additionalData['filename'] ?? (str()->slug($title) . '-' . $this->fileUniqueHash)) . '.pdf';

        $pdf = (new PdfEngine())
            ->loadView($view, compact('response', 'request', 'additionalData'))
            ->setPaper(
                $additionalData['paper_size'] ?? config('reportify.mpdf.default_paper_size', 'A4'),
                $additionalData['orientation'] ?? config('reportify.mpdf.default_orientation', 'P')
            );

        $headerMargin = 5;
        if (!($additionalData['hidePdfHeader'] ?? false)) {
            $headerHtml = $additionalData['headerHtml'] ?? '';
            $headerMargin = $this->determineHeaderMargin($headerHtml);
            $pdf->loadHeader(config('reportify.views.pdf_header', 'reportify::pdf-header'), [
                'header_html' => $headerHtml,
            ]);
        }

        $bottomMargin = 2;
        if (!($additionalData['hidePdfFooter'] ?? false)) {
            $bottomMargin = 15;
            $pdf->loadFooter(config('reportify.views.pdf_footer', 'reportify::pdf-footer'), [
                'hide_page_number' => $additionalData['hidePageNumber'] ?? false,
                'additional_footer' => $additionalData['additionalFooter'] ?? '',
                'authUserInfo' => $this->authUserInfo,
            ]);
        }

        $pdf->setPageMargins(5, 5, $headerMargin, $bottomMargin)->stream($fileName);
    }

    public function prepareZip(
        string $type, array $request, Collection|array $response,
        string $context, string $title, ?string $view = null,
        array $additionalData = []
    ): ?string {
        try {
            $allowedTypes = ['pdf', 'excel', 'csv', 'txt'];
            if (!in_array($type, $allowedTypes, true)) {
                throw new Exception("Unsupported ZIP export type: {$type}");
            }

            $exportMethod = 'export' . ucfirst($type);
            $this->makeDirectory($context);
            
            $customFileName = $additionalData['filename'] ?? null;
            $fileName = ($customFileName ?? (str()->slug($title) . '-' . $this->fileUniqueHash)) . '.zip';
            
            [$response, $additionalData] = $this->getModifiedResponse($response, $additionalData);
            $chunkedFiles = [];
            $responseChunks = collect($response)->chunk($this->chunkSize);

            foreach ($responseChunks as $index => $responseChunk) {
                $part = $index + 1;
                $additionalData['firstLoop'] = ($index === 0);
                $additionalData['lastLoop'] = ($index === $responseChunks->count() - 1);

                if ($customFileName) {
                    $additionalData['filename'] = str()->slug("{$customFileName} (Part {$part})") . '-' . $this->fileUniqueHash;
                }

                $chunkedFile = $this->{$exportMethod}($request, $responseChunk, 'exports/chunks', "{$title} (Part {$part})", $view, $additionalData);
                
                if (empty($chunkedFile)) {
                    if (!empty($chunkedFiles)) {
                        Storage::disk($this->disk)->delete($chunkedFiles);
                    }
                    throw new Exception("Failed to save chunk file part {$part}");
                }
                
                $chunkedFiles[] = $chunkedFile;
            }

            if (!$this->hideNoDataException && empty($chunkedFiles)) {
                throw new Exception('No data found for ZIP package generation.');
            }

            if (empty($chunkedFiles)) {
                $emptyView = ($type === 'pdf') ? config('reportify.views.empty_pdf', 'reportify::empty-pdf') : null;
                $chunkedFiles[] = $this->{$exportMethod}($request, [], 'exports/chunks', 'Empty ' . strtoupper($type), $emptyView);
            }

            $zip = new ZipArchive();
            $zipPath = storage_path("app/{$this->disk}/{$context}/{$fileName}");

            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
                foreach ($chunkedFiles as $file) {
                    $absFile = storage_path("app/{$this->disk}/{$file}");
                    if (File::exists($absFile)) {
                        $zip->addFile($absFile, basename($absFile));
                    }
                }
                $zip->close();
            }

            Storage::disk($this->disk)->delete($chunkedFiles);
            return "{$context}/{$fileName}";
        } catch (Exception $e) {
            Log::error('Reportify ZIP Export Error: ' . $e->getMessage(), ['exception' => $e]);
            return null;
        }
    }

    public function makeDirectory(string $context): void
    {
        if (!Storage::disk($this->disk)->exists($context)) {
            Storage::disk($this->disk)->makeDirectory($context);
        }
    }

    private function getModifiedResponse(mixed $response, array $additionalData): array
    {
        $data = $response['_data'] ?? $response;
        if (!empty($response['_additionalData'] ?? [])) {
            $additionalData = array_merge($additionalData, $response['_additionalData']);
        }

        return [$data, $additionalData];
    }

    private function mergePdfFiles(array $files, string $context, string $fileName, array $additionalData = []): ?string
    {
        try {
            $pdf = new PDFMerger();
            foreach ($files as $file) {
                $pdf->addPDF(storage_path("app/{$this->disk}/{$file}"));
            }

            $targetPath = storage_path("app/{$this->disk}/{$context}/{$fileName}");
            $pdf->merge('file', $targetPath, $additionalData['orientation'] ?? 'P');
            
            Storage::disk($this->disk)->delete($files);
            return "{$context}/{$fileName}";
        } catch (Exception $e) {
            Log::error('Reportify PDF Merge Error: ' . $e->getMessage(), ['exception' => $e]);
            return null;
        }
    }

    private function modifyPdfFooter(string $context, string $fileName, array $additionalData = []): ?string
    {
        try {
            $headerMargin = 5;
            if (!($additionalData['hidePdfHeader'] ?? false)) {
                $headerHtml = $additionalData['headerHtml'] ?? '';
                $headerMargin = $this->determineHeaderMargin($headerHtml);
            }

            $pdf = (new PdfEngine())
                ->setPaper(
                    $additionalData['paper_size'] ?? config('reportify.mpdf.default_paper_size', 'A4'),
                    $additionalData['orientation'] ?? config('reportify.mpdf.default_orientation', 'P')
                )
                ->setPageMargins(5, 5, $headerMargin, 15);

            $pageCount = $pdf->getInstance()->setSourceFile(storage_path("app/{$this->disk}/{$context}/{$fileName}"));
            
            for ($i = 1; $i <= $pageCount; $i++) {
                $pdf->getInstance()->AddPage();
                $tplIdx = $pdf->getInstance()->importPage($i);
                $pdf->getInstance()->useTemplate($tplIdx, 0, 0, null, null, true);
                
                $pdf->loadFooter(config('reportify.views.pdf_footer', 'reportify::pdf-footer'), [
                    'hide_footer_text' => true,
                    'additional_footer' => '',
                    'authUserInfo' => $this->authUserInfo,
                ]);
            }

            return $pdf->export($fileName, $context);
        } catch (Exception $e) {
            Log::error('Reportify PDF Footer Modification Error: ' . $e->getMessage(), ['exception' => $e]);
            return null;
        }
    }

    public function determineHeaderMargin(string $headerHtml, int $headerMargin = 28): int
    {
        if (empty(trim($headerHtml))) {
            return $headerMargin;
        }

        $blockTags = [
            '<address', '<article', '<aside', '<blockquote', '<canvas', '<dd', '<div', '<dl', '<dt', '<fieldset', '<figcaption',
            '<figure', '<footer', '<form', '<h1', '<h2', '<h3', '<h4', '<h5', '<h6', '<header', '<hr', '<li', '<main', '<nav',
            '<noscript', '<ol', '<p', '<pre', '<section', '<table', '<tfoot', '<ul', '<video',
        ];

        $blockCount = 0;
        foreach ($blockTags as $tag) {
            $blockCount += substr_count(strtolower($headerHtml), $tag);
        }

        $rowCount = substr_count(strtolower($headerHtml), '<tr');
        $textLines = substr_count(strtolower($headerHtml), '<br') + 1;

        if ($blockCount === 0 && $rowCount === 0) {
            $textContent = strip_tags($headerHtml, '<br>');
            $textLines = (int) ceil(strlen($textContent) / 100);
        }

        $extraHeight = ($blockCount * 15) + ($rowCount * 15) + ($textLines * 15);
        $extraMargin = (int) ceil($extraHeight * 0.25);

        return $headerMargin + $extraMargin;
    }
}
