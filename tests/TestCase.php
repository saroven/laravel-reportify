<?php

namespace Saroven\Reportify\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Saroven\Reportify\Providers\ReportifyServiceProvider;
use Saroven\Reportify\Facades\Reportify;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            ReportifyServiceProvider::class,
        ];
    }

    protected function getPackageAliases($app): array
    {
        return [
            'Reportify' => Reportify::class,
        ];
    }
}
