<?php

declare(strict_types=1);

namespace Saroven\Reportify\Traits;

use Illuminate\Http\Request;
use Saroven\Reportify\Facades\Reportify;
use Saroven\Reportify\Jobs\ProcessReportJob;
use Saroven\Reportify\Contracts\Reportable;

trait HasReportify
{
    /**
     * Handle export request directly inside controller.
     * Streams PDF synchronously if export=pdfStream, otherwise handles inline sync or background queued job.
     *
     * @param Request|array $request
     * @param string $title
     * @param string|null $view
     * @param array $additionalData
     * @param mixed|null $dataProvider
     * @return mixed
     */
    public function exportReport(
        Request|array $request,
        string $title,
        ?string $view = null,
        array $additionalData = [],
        mixed $dataProvider = null,
        ?string $exportId = null
    ): mixed {
        $requestData = $request instanceof Request ? $request->all() : $request;
        $exportFormat = (string) ($requestData['export'] ?? 'excel');
        $dataProvider = $dataProvider ?? ($this instanceof Reportable ? static::class : null);
        $exportId = $exportId ?? (string) ($additionalData['export_id'] ?? $requestData['_export_id'] ?? (string) str()->uuid());

        if ($exportFormat === 'pdfStream') {
            return $this->streamReport($requestData, $title, $view ?? config('reportify.views.empty_pdf', 'reportify::empty-pdf'), $additionalData, $dataProvider);
        }

        $isSync = config('queue.default') === 'sync' || config('reportify.force_sync', false);

        if ($isSync) {
            ProcessReportJob::dispatchSync(
                requestData: $requestData,
                type: str()->slug($title),
                title: $title,
                user: auth()->id(),
                view: $view,
                additionalData: $additionalData,
                dataProvider: $dataProvider,
                exportId: $exportId
            );
        } else {
            ProcessReportJob::dispatch(
                requestData: $requestData,
                type: str()->slug($title),
                title: $title,
                user: auth()->id(),
                view: $view,
                additionalData: $additionalData,
                dataProvider: $dataProvider,
                exportId: $exportId
            );
        }

        return $this->reportifyExportResponse($title, $exportId);
    }

    /**
     * Build the response returned after an export job is dispatched.
     * Override this in your controller for custom behaviour.
     *
     * Returns a JSON response for API requests, or a back() redirect for web requests.
     */
    protected function reportifyExportResponse(string $title, ?string $exportId = null): mixed
    {
        if (request()->expectsJson()) {
            $data = [
                'message' => "Export for '{$title}' is being processed. Check Download Manager.",
            ];

            if ($exportId !== null) {
                $data['export_id'] = $exportId;
            }

            return response()->json($data);
        }

        return back()->with('success', "Export for '{$title}' processed successfully. Check Download Manager.");
    }

    /**
     * Directly stream PDF report.
     */
    public function streamReport(
        Request|array $request,
        string $title,
        string $view,
        array $additionalData = [],
        mixed $dataProvider = null
    ): mixed {
        $requestData = $request instanceof Request ? $request->all() : $request;
        $dataProvider = $dataProvider ?? ($this instanceof Reportable ? static::class : null);

        if (is_callable($dataProvider)) {
            $data = call_user_func($dataProvider, $requestData, 'pdfStream');
        } elseif (is_string($dataProvider) && class_exists($dataProvider)) {
            $instance = app($dataProvider);
            $data = $instance instanceof Reportable ? $instance->getExportData($requestData, 'pdfStream') : [];
        } else {
            $data = $this instanceof Reportable ? $this->getExportData($requestData, 'pdfStream') : [];
        }

        return Reportify::streamPdf(
            request: $requestData,
            response: $data,
            title: $title,
            type: str()->slug($title),
            view: $view,
            additionalData: $additionalData
        );
    }
}
