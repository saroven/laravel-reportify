<?php

use Saroven\Reportify\Enums\ExportFormat;

it('validates supported export format enum values', function () {
    expect(ExportFormat::PDF->value)->toBe('pdf');
    expect(ExportFormat::EXCEL->value)->toBe('excel');
    expect(ExportFormat::CSV->value)->toBe('csv');
    expect(ExportFormat::TXT->value)->toBe('txt');
    expect(ExportFormat::PDF_STREAM->value)->toBe('pdfStream');
    expect(ExportFormat::PDF_CHUNK->value)->toBe('pdfChunk');
    expect(ExportFormat::PDF_ZIP->value)->toBe('pdfZip');
    expect(ExportFormat::EXCEL_ZIP->value)->toBe('excelZip');
    expect(ExportFormat::CSV_ZIP->value)->toBe('csvZip');
    expect(ExportFormat::TXT_ZIP->value)->toBe('txtZip');
});
