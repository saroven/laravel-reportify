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

class ProcessReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 3600;
    public int $backoff = 90;

    private ReportifyService $reportifyService;
    private string $type;
    private string $title;
    private string $context;
    private array $payload;
    private int|string|null $authUser;
    private string $exportType;
    private mixed $idmId = null;
    private ?string $view;
    private array $additionalData;
    private bool $hideNoDataException;
    private mixed $dataProvider;

    public function __construct(
        array $requestData = [],
        string $type = 'default',
        ?string $title = null,
        int|string|null $user = null,
        ?string $view = null,
        array $additionalData = [],
        mixed $dataProvider = null
    ) {
        $this->reportifyService = new ReportifyService($additionalData, $user);
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

        if (config('reportify.download_manager.enabled', true)) {
            $createFn = (string) config('reportify.download_manager.create_callback', 'importDownloadManagerCreate');
            if (function_exists($createFn) && !($additionalData['idm_disabled'] ?? false)) {
                $idmTitle = function_exists('getExportIdmTitle') 
                    ? getExportIdmTitle($this->title, $this->exportType, $this->payload) 
                    : $this->title;
                $this->idmId = $createFn($this->authUser, $idmTitle, 'Download', null, !$this->authUser ? ['Super Admin', 'IT'] : null);
            }
        }
    }

    public function handle(): void
    {
        $updateFn = (string) config('reportify.download_manager.update_callback', 'importDownloadManagerUpdate');

        if ($this->idmId && function_exists($updateFn)) {
            $updateFn($this->idmId, 'Processing', 'Export processing!', null, null, false);
        }

        if (ExportFormat::tryFrom($this->exportType) === null) {
            throw new Exception("Export process failed! Unknown export format: {$this->exportType}");
        }

        $response = $this->resolveData();

        if (!$this->hideNoDataException && $this->isEmptyResponse($response)) {
            throw new Exception('Export process failed! No data found for the given criteria.');
        }

        $exportMethod = 'export' . ucfirst($this->exportType);
        
        if (!method_exists($this->reportifyService, $exportMethod)) {
            throw new Exception("Export method '{$exportMethod}' is not supported.");
        }

        $filePath = $this->reportifyService->{$exportMethod}(
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

        if ($this->idmId && function_exists($updateFn)) {
            $updateFn($this->idmId, 'Completed', 'Export process successfully completed!', $filePath);
        }
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
        $updateFn = (string) config('reportify.download_manager.update_callback', 'importDownloadManagerUpdate');
        $deleteFn = (string) config('reportify.download_manager.delete_callback', 'importDownloadManagerDeleteFile');

        if ($e) {
            Log::error(sprintf('ProcessReportJob [%s]: %s', $this->type, $e->getMessage()), ['exception' => $e]);
        }

        if ($this->idmId) {
            if (function_exists($updateFn)) {
                $updateFn($this->idmId, 'Failed', $e?->getMessage() ?? 'Export process failed');
            }
            if (function_exists($deleteFn)) {
                $deleteFn($this->idmId);
            }
        }
    }
}
