<?php

declare(strict_types=1);

namespace Saroven\Reportify\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class MakeExportProviderCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reportify:make {name : The name of the Reportable export class}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new Reportify Reportable class';

    public function __construct(private readonly Filesystem $files)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $name = trim($this->argument('name'));
        $className = Str::studly($name);

        if (!str_ends_with($className, 'Export')) {
            $className .= 'Export';
        }

        $directory = app_path('Exports');
        $filePath = "{$directory}/{$className}.php";

        if ($this->files->exists($filePath)) {
            $this->error("Export class {$className} already exists at {$filePath}!");
            return self::FAILURE;
        }

        $this->files->ensureDirectoryExists($directory);

        $stub = <<<PHP
<?php

declare(strict_types=1);

namespace App\Exports;

use Saroven\Reportify\Contracts\Reportable;

class {$className} implements Reportable
{
    /**
     * Resolve data array or collection for export.
     *
     * @param array<string, mixed> \$payload
     * @param string \$exportType
     * @param int|string|null \$userId
     * @return mixed
     */
    public function getExportData(array \$payload, string \$exportType, int|string|null \$userId = null): mixed
    {
        // TODO: Build and return your query result array or collection
        return [];
    }
}
PHP;

        $this->files->put($filePath, $stub);

        $this->info("Reportify Reportable class created successfully: app/Exports/{$className}.php");
        return self::SUCCESS;
    }
}
