<?php

declare(strict_types=1);

namespace Saroven\Reportify\Traits;

use Illuminate\Http\Request;
use Saroven\Reportify\Facades\Reportify;
use Saroven\Reportify\Jobs\ProcessExportJob;
use Saroven\Reportify\Contracts\Reportable;

trait HasReportifyExports
{
    /**
     * Handle export request directly inside controller.
     * Streams PDF synchronously if export=pdfStream.
     * Auto-detects queue connection: runs inline with dispatchSync if queue connection is 'sync',
     * or dispatches asynchronously if background queue workers exist.
     *
     * @param Request $request
     * @param string $title
     * @param string|null $view
     * @param array $additionalData
     * @param mixed|null $dataProvider (Defaults to static controller class if it implements Reportable)
     * @return mixed
     */
    public function handleReportifyExport(
        Request $request,
        string $title,
        ?string $view = null,
        array $additionalData = [],
        mixed $dataProvider = null
    ): mixed {
        $exportFormat = (string) $request->get('export', 'excel');
        $dataProvider = $dataProvider ?? ($this instanceof Reportable ? static::class : null);

        if ($exportFormat === 'pdfStream') {
            $data = is_callable($dataProvider) 
                ? call_user_func($dataProvider, $request->all(), $exportFormat) 
                : ($this instanceof Reportable ? $this->getExportData($request->all(), $exportFormat) : []);

            Reportify::streamPdf(
                request: $request->all(),
                response: $data,
                title: $title,
                type: str()->slug($title),
                view: $view ?? config('reportify.views.empty_pdf', 'reportify::empty-pdf'),
                additionalData: $additionalData
            );
            return null;
        }

        $isSync = config('queue.default') === 'sync' || config('reportify.force_sync', false);

        if ($isSync) {
            set_time_limit(0);
            ini_set('memory_limit', '1024M');

            ProcessExportJob::dispatchSync(
                requestData: $request->all(),
                type: str()->slug($title),
                title: $title,
                user: auth()->id(),
                view: $view,
                additionalData: $additionalData,
                dataProvider: $dataProvider
            );
        } else {
            ProcessExportJob::dispatch(
                requestData: $request->all(),
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
}
