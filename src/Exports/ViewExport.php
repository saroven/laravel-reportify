<?php

declare(strict_types=1);

namespace Saroven\Reportify\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;

class ViewExport implements FromView
{
    /**
     * @param string $view
     * @param array<string, mixed> $request
     * @param mixed $response
     * @param array<string, mixed> $additionalData
     */
    public function __construct(
        private readonly string $view,
        private readonly array $request = [],
        private readonly mixed $response = [],
        private readonly array $additionalData = []
    ) {}

    public function view(): View
    {
        return view($this->view, [
            'request' => $this->request,
            'response' => $this->response,
            'additionalData' => $this->additionalData,
        ]);
    }
}
