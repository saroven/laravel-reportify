<?php

namespace Saroven\Reportify\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static ?string exportExcel(array $request, mixed $response, string $context, string $title, ?string $view = null, array $additionalData = [])
 * @method static ?string exportExcelZip(array $request, mixed $response, string $context, string $title, ?string $view = null, array $additionalData = [])
 * @method static ?string exportCsv(array $request, mixed $response, string $context, string $title, ?string $view = null, array $additionalData = [])
 * @method static ?string exportCsvZip(array $request, mixed $response, string $context, string $title, ?string $view = null, array $additionalData = [])
 * @method static ?string exportTxt(array $request, mixed $response, string $context, string $title, ?string $view = null, array $additionalData = [])
 * @method static ?string exportTxtZip(array $request, mixed $response, string $context, string $title, ?string $view = null, array $additionalData = [])
 * @method static ?string exportPdf(array $request, mixed $response, string $context, string $title, string $view, array $additionalData = [])
 * @method static ?string exportPdfZip(array $request, mixed $response, string $context, string $title, string $view, array $additionalData = [])
 * @method static ?string exportPdfChunk(array $request, mixed $response, string $context, string $title, string $view, array $additionalData = [])
 * @method static void streamPdf(array $request, mixed $response, string $title, string $type, string $view, array $additionalData = [])
 * @method static ?string prepareZip(string $type, array $request, mixed $response, string $context, string $title, ?string $view = null, array $additionalData = [])
 *
 * @see \Saroven\Reportify\ReportifyService
 */
class Reportify extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'reportify';
    }
}
