<?php

namespace Contoweb\AbacusApi\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class MakeAbacusModelCommand extends Command
{
    protected $signature = 'make:abacus-model {name} {--resource=}';

    protected $description = 'Create a new Abacus REST model';

    public function handle(): int
    {
        $name      = $this->argument('name');
        $resource  = $this->option('resource') ?? Str::plural($name);
        $namespace = config('abacus-api.models_namespace');

        $path = $this->getPath($namespace, $name);

        if (File::exists($path)) {
            $this->error("Model {$name} already exists!");

            return 1;
        }

        $this->makeDirectory($path);

        $stub    = $this->getStub();
        $content = $this->replaceStub($stub, $name, $resource, $namespace);

        File::put($path, $content);

        $this->info("Model {$name} created successfully.");
        $this->comment("Location: {$path}");
        $this->comment("Resource: {$resource}");

        return 0;
    }

    protected function getPath(string $namespace, string $name): string
    {
        $namespacePath = str_replace('\\', '/', $namespace);
        $appPath       = app_path();

        // Remove 'App' from namespace path
        $namespacePath = preg_replace('/^App\//', '', $namespacePath);

        return "{$appPath}/{$namespacePath}/{$name}.php";
    }

    protected function makeDirectory(string $path): void
    {
        $directory = dirname($path);

        if ( ! File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true);
        }
    }

    protected function getStub(): string
    {
        return <<<'STUB'
<?php

namespace {{namespace}};

use Contoweb\AbacusApi\Models\AbacusModel;

class {{class}} extends AbacusModel
{
    protected static string $resource = '{{resource}}';
}
STUB;
    }

    protected function replaceStub(string $stub, string $name, string $resource, string $namespace): string
    {
        return str_replace(
            ['{{namespace}}', '{{class}}', '{{resource}}'],
            [$namespace, $name, $resource],
            $stub
        );
    }
}