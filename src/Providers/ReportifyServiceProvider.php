<?php

declare(strict_types=1);

namespace Saroven\Reportify\Providers;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Saroven\Reportify\Console\Commands\MakeReportCommand;
use Saroven\Reportify\ReportifyService;
use Saroven\Reportify\View\Components\ExportGroup;

class ReportifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../../config/reportify.php', 'reportify'
        );

        $this->app->bind('reportify', function (): ReportifyService {
            return new ReportifyService();
        });

        $this->app->alias('reportify', ReportifyService::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'reportify');

        Blade::component(ExportGroup::class, 'reportify-export-group');
        Blade::component(ExportGroup::class, 'reportify-buttons');
        Blade::component(ExportGroup::class, 'reportify-actions');
        Blade::component('reportify::components.scripts', 'reportify-scripts');

        if ($this->app->runningInConsole()) {
            $this->commands([
                MakeReportCommand::class,
            ]);

            $this->publishes([
                __DIR__ . '/../../config/reportify.php' => config_path('reportify.php'),
            ], 'reportify-config');

            $this->publishes([
                __DIR__ . '/../../resources/views' => resource_path('views/vendor/reportify'),
            ], 'reportify-views');
        }
    }
}
