<?php

declare(strict_types=1);

namespace Saroven\Reportify\Contracts;

interface Reportable
{
    /**
     * Resolve data array or collection for export.
     *
     * @param array<string, mixed> $payload
     * @param string $exportType
     * @param int|string|null $userId
     * @return mixed
     */
    public function getExportData(array $payload, string $exportType, int|string|null $userId = null): mixed;
}
