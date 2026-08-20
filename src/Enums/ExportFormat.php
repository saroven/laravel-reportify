<?php

declare(strict_types=1);

namespace Saroven\Reportify\Enums;

enum ExportFormat: string
{
    case PDF = 'pdf';
    case PDF_STREAM = 'pdfStream';
    case PDF_CHUNK = 'pdfChunk';
    case PDF_ZIP = 'pdfZip';
    case EXCEL = 'excel';
    case EXCEL_ZIP = 'excelZip';
    case CSV = 'csv';
    case CSV_ZIP = 'csvZip';
    case TXT = 'txt';
    case TXT_ZIP = 'txtZip';

    public function isZip(): bool
    {
        return str_ends_with($this->value, 'Zip');
    }

    public function isPdf(): bool
    {
        return str_starts_with($this->value, 'pdf');
    }
}
