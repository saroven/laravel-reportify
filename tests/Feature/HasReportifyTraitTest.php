<?php

use Saroven\Reportify\Contracts\Reportable;
use Saroven\Reportify\Traits\HasReportify;

class TestController implements Reportable
{
    use HasReportify;

    public function getExportData(array $payload, string $exportType, int|string|null $userId = null): mixed
    {
        return [
            ['title' => 'Item 1'],
        ];
    }
}

it('binds HasReportify trait to controller classes', function () {
    $controller = new TestController();
    $data = $controller->getExportData([], 'pdf');

    expect($data)->toBeArray()->toHaveCount(1);
});
