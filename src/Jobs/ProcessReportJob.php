<?php

declare(strict_types=1);

namespace Saroven\Reportify\Jobs;

use Exception;
use Throwable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Saroven\Reportify\ReportifyService;
use Saroven\Reportify\Contracts\Reportable;
use Saroven\Reportify\Enums\ExportFormat;
use Saroven\Reportify\Events\ExportStarted;
use Saroven\Reportify\Events\ExportCompleted;
use Saroven\Reportify\Events\ExportFailed;

class ProcessReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 3600;
    public int $backoff = 90;

    private string $type;
    private string $title;
    private string $context;
    private array $payload;
    private int|string|null $authUser;
    private string $exportType;
    private ?string $view;
    private array $additionalData;
    private bool $hideNoDataException;
    private mixed $dataProvider;
    private string $exportId;

    public function __construct(
        array $requestData = [],
        string $type = 'default',
        ?string $title = null,
        int|string|null $user = null,
        ?string $view = null,
        array $additionalData = [],
        mixed $dataProvider = null,
        ?string $exportId = null
    ) {
        if ($dataProvider instanceof \Closure) {
            throw new \InvalidArgumentException(
                'ProcessReportJob does not support Closure as $dataProvider because closures cannot be serialized. '
                . 'Pass a class-string or a Reportable instance instead.'
            );
        }

        $this->type = $type;
        $this->title = $title ?? 'Document';
        $this->payload = $requestData;
        $this->exportType = (string) ($this->payload['export'] ?? 'excel');
        $this->context = (string) ($additionalData['file_dir'] ?? config('reportify.export_directory', 'exports') . '/' . $this->exportType);
        $this->authUser = $user ?? auth()->user()?->id ?? 0;
        $this->view = $view;
        $this->additionalData = $additionalData;
        $this->hideNoDataException = (bool) ($additionalData['no_data_exception_disabled'] ?? false);
        $this->dataProvider = $dataProvider;
        $this->exportId = $exportId 
            ?? (string) ($additionalData['export_id'] ?? $requestData['_export_id'] ?? (string) \Illuminate\Support\Str::orderedUuid());
        $this->additionalData['export_id'] = $this->exportId;
    }

    public function handle(): void
    {
        $reportifyService = new ReportifyService($this->additionalData, $this->authUser);

        ExportStarted::dispatch($this->authUser, $this->title, $this->exportType, $this->payload, $this->exportId);

        if (ExportFormat::tryFrom($this->exportType) === null) {
            throw new Exception("Export process failed! Unknown export format: {$this->exportType}");
        }

        if ($this->exportType === ExportFormat::PDF_STREAM->value) {
            throw new Exception(
                "Export process failed! 'pdfStream' is a browser-only streaming format and cannot be run in a background job."
            );
        }

        $response = $this->resolveData();

        if (!$this->hideNoDataException && $this->isEmptyResponse($response)) {
            throw new Exception('Export process failed! No data found for the given criteria.');
        }

        $exportMethod = 'export' . ucfirst($this->exportType);
        
        if (!method_exists($reportifyService, $exportMethod)) {
            throw new Exception("Export method '{$exportMethod}' is not supported.");
        }

        $filePath = $reportifyService->{$exportMethod}(
            $this->payload,
            $response,
            $this->context,
            $this->title,
            $this->view,
            $this->additionalData
        );

        if (!$this->hideNoDataException && empty($filePath)) {
            throw new Exception('Export process failed! Output file could not be generated.');
        }

        ExportCompleted::dispatch($this->authUser, $this->title, $this->exportType, (string) $filePath, $this->payload, $this->exportId);
    }

    private function resolveData(): mixed
    {
        if (is_callable($this->dataProvider)) {
            return call_user_func($this->dataProvider, $this->payload, $this->exportType, $this->authUser);
        }

        if ($this->dataProvider instanceof Reportable) {
            return $this->dataProvider->getExportData($this->payload, $this->exportType, $this->authUser);
        }

        if (is_string($this->dataProvider) && class_exists($this->dataProvider)) {
            $instance = app($this->dataProvider);
            if ($instance instanceof Reportable) {
                return $instance->getExportData($this->payload, $this->exportType, $this->authUser);
            }
        }

        return collect([]);
    }

    private function isEmptyResponse(mixed $response): bool
    {
        if (is_array($response)) {
            return empty($response);
        }

        if ($response instanceof Collection) {
            return $response->isEmpty();
        }

        return empty($response);
    }

    public function failed(?Throwable $e): void
    {
        $message = $e?->getMessage() ?? 'Export process failed';
        
        if ($e) {
            Log::error(sprintf('ProcessReportJob [%s]: %s', $this->type, $message), ['exception' => $e]);
        }

        ExportFailed::dispatch($this->authUser, $this->title, $this->exportType, $message, $this->payload, $this->exportId);
    }

    public function getExportId(): string
    {
        return $this->exportId;
    }
}
