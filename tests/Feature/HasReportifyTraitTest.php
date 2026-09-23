<?php

use Saroven\Reportify\Contracts\Reportable;
use Saroven\Reportify\Traits\HasReportify;

it('binds HasReportify trait to controller classes', function () {
    $controller = new class implements Reportable {
        use HasReportify;

        public function getExportData(array $payload, string $exportType, int|string|null $userId = null): mixed
        {
            return [
                ['title' => 'Item 1'],
            ];
        }
    };

    $data = $controller->getExportData([], 'pdf');

    expect($data)->toBeArray()->toHaveCount(1);
});

it('returns json response with export_id for api requests', function () {
    $controller = new class implements Reportable {
        use HasReportify;

        public function getExportData(array $payload, string $exportType, int|string|null $userId = null): mixed
        {
            return [['id' => 1]];
        }
    };

    $request = \Illuminate\Http\Request::create('/export', 'GET', ['export' => 'excel']);
    $request->headers->set('Accept', 'application/json');

    \Illuminate\Support\Facades\Queue::fake();

    // Bind request to container so request() helper picks it up
    app()->instance('request', $request);

    $response = $controller->exportReport($request, 'Test Report', exportId: 'my-custom-uuid-123');

    expect($response)->toBeInstanceOf(\Illuminate\Http\JsonResponse::class);
    $data = $response->getData(true);
    expect($data)->toHaveKey('export_id', 'my-custom-uuid-123')
        ->and($data)->toHaveKey('message');
});
