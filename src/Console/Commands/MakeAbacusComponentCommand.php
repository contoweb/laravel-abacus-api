<?php

namespace Contoweb\AbacusApi\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class MakeAbacusComponentCommand extends Command
{
    protected $signature = 'make:abacus-component {name}';

    protected $description = 'Create a new Abacus component (OData complex types)';

    private readonly string $componentsNamespace;

    public function __construct(string $componentsNamespace)
    {
        parent::__construct();
        $this->componentsNamespace = $componentsNamespace;
    }

    public function handle(): int
    {
        $name = $this->argument('name');

        $path = $this->getPath($this->componentsNamespace, $name);

        if (File::exists($path)) {
            $this->error("Component {$name} already exists!");

            return 1;
        }

        $this->makeDirectory($path);

        $stub = $this->getStub();
        $content = $this->replaceStub($stub, $name, $this->componentsNamespace);

        File::put($path, $content);

        $this->info("Component {$name} created successfully.");
        $this->comment("Location: {$path}");

        return 0;
    }

    protected function getPath(string $namespace, string $name): string
    {
        $namespacePath = str_replace('\\', '/', $namespace);
        $appPath = app_path();

        // Remove 'App' from namespace path
        $namespacePath = preg_replace('/^App\//', '', $namespacePath);

        return "{$appPath}/{$namespacePath}/{$name}.php";
    }

    protected function makeDirectory(string $path): void
    {
        $directory = dirname($path);

        if (! File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true);
        }
    }

    protected function getStub(): string
    {
        return <<<'STUB'
<?php

namespace {{namespace}};

use Contoweb\AbacusApi\Models\AbacusComponent;

class {{class}} extends AbacusComponent
{
    //
}
STUB;
    }

    protected function replaceStub(string $stub, string $name, string $namespace): string
    {
        return str_replace(
            ['{{namespace}}', '{{class}}'],
            [$namespace, $name],
            $stub
        );
    }
}
