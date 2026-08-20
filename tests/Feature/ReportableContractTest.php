<?php

use Saroven\Reportify\Contracts\Reportable;

it('resolves export data via Reportable interface contract', function () {
    $export = new class implements Reportable {
        public function getExportData(array $payload, string $exportType, int|string|null $userId = null): mixed
        {
            return [
                ['id' => 1, 'name' => 'John Doe'],
                ['id' => 2, 'name' => 'Jane Smith'],
            ];
        }
    };

    $data = $export->getExportData([], 'excel');

    expect($data)->toBeArray()
        ->and($data)->toHaveCount(2)
        ->and($data[0]['name'])->toBe('John Doe');
});
