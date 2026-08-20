<?php

declare(strict_types=1);

namespace Saroven\Reportify\PDF;

use Exception;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Facades\Storage;
use Mpdf\Mpdf;

class PdfEngine
{
    protected Mpdf $mpdf;
    private string $bodyHtml = '';
    private int $totalPages = 0;

    public function __construct(?Mpdf $mpdf = null)
    {
        ini_set('pcre.backtrack_limit', (string) config('reportify.mpdf.backtrack_limit', '1000000000'));
        ini_set('pcre.recursion_limit', (string) config('reportify.mpdf.recursion_limit', '1000000000'));

        $this->mpdf = $mpdf ?? new Mpdf();
        $this->mpdf->setAutoBottomMargin = 'stretch';
        
        $author = (string) (config('reportify.mpdf.author') ?? config('app.name', 'Laravel'));
        $this->setAuthor($author);
    }

    public function setDisplayMode(string $zoom, string $layout): static
    {
        $this->mpdf->SetDisplayMode($zoom, $layout);
        return $this;
    }

    public function loadView(?string $view = null, Arrayable|array $data = [], array $mergeData = []): static
    {
        $html = $this->convertViewToHTML($view, $data, $mergeData);

        if (empty(trim($html))) {
            throw new Exception("The view '{$view}' is empty or does not exist.");
        }

        $this->loadBodyHtml($html);
        return $this;
    }

    public function loadHeader(string $view, array $data = [], array $mergeData = []): static
    {
        $this->mpdf->SetHTMLHeader($this->convertViewToHTML($view, $data, $mergeData));
        return $this;
    }

    public function loadFooter(string $view, array $data = [], array $mergeData = []): static
    {
        $this->mpdf->SetHTMLFooter($this->convertViewToHTML($view, $data, $mergeData));
        return $this;
    }

    public function setPaper(string $size = 'A4', string $orientation = 'P'): static
    {
        $orient = strtoupper(substr($orientation, 0, 1)) === 'L' ? 'L' : 'P';
        $author = (string) (config('reportify.mpdf.author') ?? config('app.name', 'Laravel'));

        $this->mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => $size,
            'orientation' => $orient,
        ]);
        $this->mpdf->setAutoBottomMargin = 'stretch';
        $this->setAuthor($author);

        return $this;
    }

    public function setPageMargins(int $left, int $right = 0, int $top = 0, int $bottom = 0): static
    {
        $args = func_num_args();

        if ($args === 1) {
            $right = $top = $bottom = $left;
        } elseif ($args === 2) {
            $top = $bottom = $right;
        } elseif ($args === 3) {
            $bottom = $top;
        }

        $this->mpdf->tMargin = $top;
        $this->mpdf->bMargin = $bottom;
        $this->mpdf->DeflMargin = $left;
        $this->mpdf->DefrMargin = $right;

        return $this;
    }

    public function setFooterMargin(int $value): static
    {
        $this->mpdf->margin_footer = $value;
        return $this;
    }

    public function stream(string $fileName = 'report.pdf'): void
    {
        if (strtolower(pathinfo($fileName, PATHINFO_EXTENSION)) !== 'pdf') {
            $fileName = pathinfo($fileName, PATHINFO_FILENAME) . '.pdf';
        }

        $this->writeHtml();
        $this->mpdf->Output($fileName, 'I');
    }

    public function export(string $fileName, string $path = 'exports/pdf'): string
    {
        $disk = (string) config('reportify.storage_disk', 'public');
        [$path, $fileName] = $this->sanitizePathAndFileName($path, $fileName);
        
        $pdfContent = $this->generateAsString();
        $fullPath = $path ? "{$path}/{$fileName}.pdf" : "{$fileName}.pdf";
        
        Storage::disk($disk)->put($fullPath, $pdfContent);

        return $fullPath;
    }

    public function exportWithPdfInfo(string $fileName, string $path = 'exports/pdf', int $total = 0): array
    {
        return [
            'filepath' => $this->export($fileName, $path),
            'total_page' => $this->totalPages + $total,
        ];
    }

    private function writeHtml(): void
    {
        $this->mpdf->WriteHTML($this->bodyHtml);
        $this->totalPages = $this->mpdf->page;
    }

    public function addPageBreak(string $orientation = 'P', int $left = 5, int $right = 5, int $top = 5, int $bottom = 5, int $startPage = 1): static
    {
        $this->mpdf->AddPageByArray([
            'orientation' => $orientation,
            'margin-left' => $left,
            'margin-right' => $right,
            'margin-top' => $top,
            'margin-bottom' => $bottom,
            'resetpagenum' => $startPage,
        ]);
        return $this;
    }

    public function getTotalPageNumber(): int
    {
        return $this->totalPages;
    }

    private function sanitizePathAndFileName(string $path, string $fileName): array
    {
        $disk = (string) config('reportify.storage_disk', 'public');
        $path = empty($path) ? '' : rtrim($path, '/');

        if ($fileName === '') {
            $fileName = now()->toDateString() . '-' . time();
        }

        if (str_ends_with(strtolower($fileName), '.pdf')) {
            $fileName = substr($fileName, 0, -4);
        }

        if ($path && !Storage::disk($disk)->exists($path)) {
            Storage::disk($disk)->makeDirectory($path);
        }

        return [$path, $fileName];
    }

    public function setAuthor(string $author = ''): void
    {
        $this->mpdf->SetAuthor($author);
    }

    public function loadBodyHtml(string $html): void
    {
        $this->bodyHtml = $html;
    }

    private function convertViewToHTML(?string $view, array $data, array $mergeData): string
    {
        if (empty($view)) {
            return '';
        }
        return view($view, $data, $mergeData)->render();
    }

    public function generateAsString(): string
    {
        $this->writeHtml();
        return $this->mpdf->Output('', 'S');
    }

    public function getInstance(): Mpdf
    {
        return $this->mpdf;
    }
}
