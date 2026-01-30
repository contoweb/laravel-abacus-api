<?php

namespace Contoweb\AbacusApi\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class MakeAbacusReportCommand extends Command
{
    protected $signature = 'make:abacus-report {name} {--model= : Optional model name to generate}';

    protected $description = 'Create a new Abacus report class';

    public function handle(): int
    {
        $name = $this->argument('name');
        $modelName = $this->option('model');
        $reportsNamespace = config('abacus-api.reports.reports_namespace');

        /* Create report class */
        $reportPath = $this->getPath($reportsNamespace, $name);

        if (File::exists($reportPath)) {
            $this->error("Report {$name} already exists!");

            return 1;
        }

        $this->makeDirectory($reportPath);

        $reportStub = $this->getReportStub();
        $reportContent = $this->replaceReportStub($reportStub, $name, $reportsNamespace, $modelName);

        File::put($reportPath, $reportContent);

        $this->info("Report {$name} created successfully.");
        $this->comment("Location: {$reportPath}");

        /* Create model class if specified */
        if ($modelName) {
            $modelPath = $this->getModelPath($reportsNamespace, $modelName);

            if (File::exists($modelPath)) {
                $this->warn("Model {$modelName} already exists, skipping model generation.");
            } else {
                $this->makeDirectory($modelPath);

                $modelStub = $this->getModelStub();
                $modelContent = $this->replaceModelStub($modelStub, $modelName, $reportsNamespace);

                File::put($modelPath, $modelContent);

                $this->info("Report model {$modelName} created successfully.");
                $this->comment("Location: {$modelPath}");
            }
        } else {
            $this->comment('Tip: Use --model=ModelName to generate a report model class.');
        }

        return 0;
    }

    protected function getPath(string $namespace, string $name): string
    {
        $namespacePath = str_replace('\\', '/', $namespace);
        $appPath = app_path();

        /* Remove 'App' from namespace path */
        $namespacePath = preg_replace('/^App\//', '', $namespacePath);

        return "{$appPath}/{$namespacePath}/{$name}.php";
    }

    protected function getModelPath(string $namespace, string $name): string
    {
        $namespacePath = str_replace('\\', '/', $namespace);
        $appPath = app_path();

        /* Remove 'App' from namespace path */
        $namespacePath = preg_replace('/^App\//', '', $namespacePath);

        return "{$appPath}/{$namespacePath}/Models/{$name}.php";
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

use Contoweb\AbacusApi\Reports\Contracts\Report;
use Contoweb\AbacusApi\Reports\Contracts\ReportModel;
use Contoweb\AbacusApi\Reports\Contracts\RequiresValidationRules;
{{modelImport}}

class {{class}} implements Report, RequiresValidationRules
{
    /**
     * Get validation rules for report parameters
     */
    public static function validationRules(): array
    {
        return [
            /* Example: 'organization_number' => 'required|integer', */
        ];
    }

    /**
     * Get the report name (with URL encoding)
     * Example: config('abacus-api.rest_api.mandate') . '%2F' . 'report_name.avx'
     */
    public function name(): string
    {
        return config('abacus-api.rest_api.mandate') . '%2F' . 'your_report.avx';
    }

    /**
     * Map JSON record to report model
     */
    public function mapping(array $record): ReportModel
    {
        return new {{modelClass}}(
            /* Map JSON fields to model properties */
            /* Example: $record['FIELD_NAME'] ?? null, */
        );
    }
}
STUB;
    }

    protected function getModelStub(): string
    {
        return <<<'STUB'
<?php

namespace {{namespace}}\Models;

use Contoweb\AbacusApi\Reports\Contracts\ReportModel;

class {{class}} implements ReportModel
{
    public function __construct(
        /* Define your model properties here */
        /* Example: public readonly ?string $name, */
    ) {
    }
}
STUB;
    }

    protected function replaceReportStub(string $stub, string $name, string $namespace, ?string $modelName): string
    {
        $modelClass = $modelName ?? 'YourModel';
        $modelImport = $modelName ? "use {$namespace}\\Models\\{$modelName};" : '';

        return str_replace(
            ['{{namespace}}', '{{class}}', '{{modelClass}}', '{{modelImport}}'],
            [$namespace, $name, $modelClass, $modelImport],
            $stub
        );
    }

    protected function replaceModelStub(string $stub, string $name, string $namespace): string
    {
        return str_replace(
            ['{{namespace}}', '{{class}}'],
            [$namespace, $name],
            $stub
        );
    }
}
