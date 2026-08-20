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
        mixed $dataProvider = null
    ): mixed {
        $requestData = $request instanceof Request ? $request->all() : $request;
        $exportFormat = (string) ($requestData['export'] ?? 'excel');
        $dataProvider = $dataProvider ?? ($this instanceof Reportable ? static::class : null);

        if ($exportFormat === 'pdfStream') {
            return $this->streamReport($requestData, $title, $view ?? config('reportify.views.empty_pdf', 'reportify::empty-pdf'), $additionalData, $dataProvider);
        }

        $isSync = config('queue.default') === 'sync' || config('reportify.force_sync', false);

        if ($isSync) {
            set_time_limit(0);
            ini_set('memory_limit', '1024M');

            ProcessReportJob::dispatchSync(
                requestData: $requestData,
                type: str()->slug($title),
                title: $title,
                user: auth()->id(),
                view: $view,
                additionalData: $additionalData,
                dataProvider: $dataProvider
            );
        } else {
            ProcessReportJob::dispatch(
                requestData: $requestData,
                type: str()->slug($title),
                title: $title,
                user: auth()->id(),
                view: $view,
                additionalData: $additionalData,
                dataProvider: $dataProvider
            );
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

        $data = is_callable($dataProvider) 
            ? call_user_func($dataProvider, $requestData, 'pdfStream') 
            : ($this instanceof Reportable ? $this->getExportData($requestData, 'pdfStream') : []);

        Reportify::streamPdf(
            request: $requestData,
            response: $data,
            title: $title,
            type: str()->slug($title),
            view: $view,
            additionalData: $additionalData
        );

        return null;
    }
}
