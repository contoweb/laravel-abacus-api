<?php

namespace Contoweb\AbacusApi\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class MakeAbacusReportCommand extends Command
{
    protected $signature = 'make:abacus-report {name}';

    protected $description = 'Create a new Abacus report class';

    private readonly string $reportsNamespace;

    public function __construct(string $reportsNamespace)
    {
        parent::__construct();
        $this->reportsNamespace = $reportsNamespace;
    }

    public function handle(): int
    {
        $name = $this->argument('name');

        /* Create report class */
        $reportPath = $this->getPath($this->reportsNamespace, $name);

        if (File::exists($reportPath)) {
            $this->error("Report $name already exists!");

            return 1;
        }

        $this->makeDirectory($reportPath);

        $reportStub = $this->getReportStub();
        $reportContent = $this->replaceReportStub($reportStub, $name, $this->reportsNamespace);

        File::put($reportPath, $reportContent);

        $this->info("Report $name created successfully.");
        $this->comment("Location: $reportPath");

        return 0;
    }

    protected function getPath(string $namespace, string $name): string
    {
        $namespacePath = str_replace('\\', '/', $namespace);
        $appPath = app_path();

        /* Remove 'App' from namespace path */
        $namespacePath = preg_replace('/^App\//', '', $namespacePath);

        return "$appPath/$namespacePath/$name.php";
    }

    protected function getModelPath(string $namespace, string $name): string
    {
        $namespacePath = str_replace('\\', '/', $namespace);
        $appPath = app_path();

        /* Remove 'App' from namespace path */
        $namespacePath = preg_replace('/^App\//', '', $namespacePath);

        return "$appPath/$namespacePath/Models/$name.php";
    }

    protected function makeDirectory(string $path): void
    {
        $directory = dirname($path);

        if (! File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true);
        }
    }

    protected function getReportStub(): string
    {
        return <<<'STUB'
<?php

namespace {{namespace}};

use Contoweb\AbacusApi\Reports\Abstracts\Report;

class {{class}} extends Report
{
    /**
     * The report name.
     */
    public function name(): string
    {
        return 'your_report.avw';
    }

    /**
     * Map the JSON record.
     */
    public function mapping(array $record): array
    {
        return $record;
    }
}
STUB;
    }

    protected function replaceReportStub(string $stub, string $name, string $namespace): string
    {
        return str_replace(
            ['{{namespace}}', '{{class}}'],
            [$namespace, $name],
            $stub
        );
    }
}
