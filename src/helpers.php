<?php

declare(strict_types=1);

use Saroven\Reportify\ReportifyService;

if (!function_exists('reportify')) {
    /**
     * Get the Reportify service instance from the Laravel container.
     */
    function reportify(): ReportifyService
    {
        return app('reportify');
    }
}
