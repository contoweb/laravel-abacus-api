<?php

namespace Contoweb\AbacusApi\Console\Commands;

use Contoweb\AbacusApi\Attributes\Scope;
use Contoweb\AbacusApi\Models\AbacusComponent;
use Contoweb\AbacusApi\Models\AbacusModel;
use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use JsonException;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use ReflectionParameter;
use ReflectionType;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

class GenerateIdeHelperCommand extends Command
{
    protected $signature = 'abacus:generate-ide-helper
                          {model?* : Model classes to generate (default: all models found in --dir)}
                          {--dir=* : Model directories relative to the base path, supports glob patterns (default: app/Models/Abacus)}
                          {--source= : Absolute path to the OData metadata JSON file}
                          {--output-dir= : Directory for the generated helper files (default: vendor/_abacus_ide_helper)}';

    protected $description = 'Generate IDE helper files for the Abacus models';

    protected const BUILDER_ENTRY_POINTS = ['query', 'select', 'where', 'orderBy', 'top', 'expand', 'withoutUuidEscaping', 'withUuidEscaping'];

    protected const BUILTIN_TYPES = ['int', 'float', 'string', 'bool', 'array', 'callable', 'iterable', 'object', 'mixed', 'null', 'void', 'never', 'false', 'true', 'self', 'static'];

    /** @var array<string, mixed> */
    protected array $metadata = [];

    /** @var array<string, mixed>|null */
    protected ?array $entityContainer = null;

    /**
     * @throws FileNotFoundException
     */
    public function handle(Filesystem $files): int
    {
        $source = $this->option('source') ?? dirname(__DIR__, 3).'/resources/metadata/metadata-2026-201-p-2026-05-15.json';

        if (! $files->exists($source)) {
            $this->error('Metadata file not found: '.$source);

            return self::FAILURE;
        }

        try {
            $this->metadata = json_decode($files->get($source), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            $this->error('Failed to parse metadata JSON: '.$e->getMessage());

            return self::FAILURE;
        }

        $modelDocs = [];
        $builderDocs = [];

        foreach ($this->loadModels($files) as $class) {
            try {
                $reflection = new ReflectionClass($class);
                $builder = $this->builderClassName($reflection);

                $modelDocs[$reflection->getNamespaceName()][] = $this->createModelDocs($reflection, $builder);
                $builderDocs[$reflection->getNamespaceName()][] = $this->createBuilderDocs($reflection, $builder);
            } catch (Throwable $e) {
                $this->warn("Could not analyze model $class: {$e->getMessage()}");

                continue;
            }

            $this->comment("Loaded model $class", OutputInterface::VERBOSITY_VERBOSE);
        }

        $this->writeFiles($files, $this->option('output-dir') ?? base_path('vendor/_abacus_ide_helper'), $modelDocs, $builderDocs);

        $this->info('IDE helper generated for '.array_sum(array_map('count', $modelDocs)).' models.');
        $this->comment('Restart PhpStorm or run "File → Invalidate Caches" if suggestions do not show up.');

        return self::SUCCESS;
    }

    /**
     * @return list<class-string<AbacusModel>>
     */
    protected function loadModels(Filesystem $files): array
    {
        $classes = $this->argument('model') ?: $this->findModelClasses($files);

        return (new Collection($classes))
            ->filter(fn (string $class) => class_exists($class))
            ->filter(fn (string $class) => is_subclass_of($class, AbacusModel::class))
            ->filter(fn (string $class) => (new ReflectionClass($class))->isInstantiable())
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    protected function findModelClasses(Filesystem $files): array
    {
        $classes = [];

        foreach ($this->option('dir') ?: ['app/Models/Abacus'] as $pattern) {
            foreach (glob(base_path($pattern), GLOB_ONLYDIR) ?: [] as $directory) {
                foreach ($files->allFiles($directory) as $file) {
                    $classes[] = $this->laravel->getNamespace().str_replace(
                        ['/', '.php'],
                        ['\\', ''],
                        Str::after($file->getPathname(), app_path().DIRECTORY_SEPARATOR)
                    );
                }
            }
        }

        return $classes;
    }

    /**
     * @param  ReflectionClass<AbacusModel>  $reflection
     *
     * @throws ReflectionException
     */
    protected function createModelDocs(ReflectionClass $reflection, string $builder): string
    {
        $model = $reflection->newInstanceWithoutConstructor();
        $resource = $model::getResource();
        $entity = $this->findEntity($resource);
        $casts = $reflection->getProperty('casts')->getValue($model);

        if ($entity === []) {
            $this->warn("{$reflection->getName()}: resource '$resource' not found in the metadata, properties are typed from the casts only.");
        }

        $keys = $entity['$Key'] ?? [];

        $lines = [];

        foreach ($casts as $name => $cast) {
            $definition = is_array($entity[$name] ?? null) ? $entity[$name] : null;

            if ($definition === null && $entity !== []) {
                $this->warn("{$reflection->getName()}: '$name' is cast but not defined in the metadata for '$resource'.");
            }

            $lines[] = "@property {$this->castType((string) $cast, $definition, in_array($name, $keys, true))} \${$name}";
        }

        foreach (self::BUILDER_ENTRY_POINTS as $method) {
            $lines[] = "@method static $builder $method({$this->formatParameters(new ReflectionMethod(AbacusModel::class, $method))})";
        }

        foreach ($this->scopes($reflection) as $name => $method) {
            $lines[] = "@method static $builder $name({$this->formatParameters($method, true)})";
        }

        $lines[] = "@mixin $builder";

        return $this->docBlock($lines)."\n    class {$reflection->getShortName()} extends \\{$reflection->getParentClass()->getName()} {}";
    }

    protected function createBuilderDocs(ReflectionClass $reflection, string $builder): string
    {
        $lines = ["@extends \\Contoweb\\AbacusApi\\AbacusODataQueryBuilder<\\{$reflection->getName()}>"];

        foreach ($this->scopes($reflection) as $name => $method) {
            $lines[] = "@method $builder {$name}({$this->formatParameters($method, true)})";
        }

        return $this->docBlock($lines)."\n    class ".Str::afterLast($builder, '\\').' extends \\Contoweb\\AbacusApi\\AbacusODataQueryBuilder {}';
    }

    /**
     * @return array<string, ReflectionMethod>
     */
    protected function scopes(ReflectionClass $reflection): array
    {
        $scopes = [];

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC | ReflectionMethod::IS_PROTECTED) as $method) {
            if ($method->isStatic() || $method->getDeclaringClass()->getName() === AbacusModel::class) {
                continue;
            }

            if ($method->getAttributes(Scope::class) !== []) {
                $scopes[$method->getName()] = $method;
            } elseif (str_starts_with($method->getName(), 'scope') && strlen($method->getName()) > 5) {
                $scopes[lcfirst(substr($method->getName(), 5))] = $method;
            }
        }

        return $scopes;
    }

    /**
     * @param  array<string, mixed>|null  $definition
     */
    protected function castType(string $cast, ?array $definition, bool $isKey = false): string
    {
        $cast = Str::before($cast, ':');
        $nullable = $isKey ? '' : '|null';

        if (is_subclass_of($cast, AbacusModel::class) || is_subclass_of($cast, AbacusComponent::class)) {
            $related = '\\'.$cast;

            return match (true) {
                $definition === null => "\\Illuminate\\Support\\Collection<int, {$related}>|{$related}|null",
                ($definition['$Collection'] ?? false) === true => "\\Illuminate\\Support\\Collection<int, {$related}>|null",
                default => "{$related}|null",
            };
        }

        return match ($cast) {
            'int', 'integer' => 'int'.$nullable,
            'real', 'float', 'double' => 'float'.$nullable,
            'string' => 'string'.$nullable,
            'bool', 'boolean' => 'bool'.$nullable,
            'object' => 'object'.$nullable,
            'date', 'datetime', 'timestamp' => '\\Illuminate\\Support\\Carbon'.$nullable,
            default => class_exists($cast) || enum_exists($cast) ? "\\{$cast}{$nullable}" : 'mixed',
        };
    }

    /**
     * @return array<string, mixed>
     */
    protected function findEntity(string $resource): array
    {
        $type = $this->entityContainer()[$resource]['$Type'] ?? null;

        if ($type === null) {
            return [];
        }

        return $this->metadata[Str::beforeLast($type, '.')][Str::afterLast($type, '.')] ?? [];
    }

    /**
     * @return array<string, mixed>
     */
    protected function entityContainer(): array
    {
        if ($this->entityContainer !== null) {
            return $this->entityContainer;
        }

        foreach ($this->metadata as $schema) {
            foreach (is_array($schema) ? $schema : [] as $element) {
                if (is_array($element) && ($element['$Kind'] ?? null) === 'EntityContainer') {
                    return $this->entityContainer = $element;
                }
            }
        }

        return $this->entityContainer = [];
    }

    protected function builderClassName(ReflectionClass $reflection): string
    {
        return "\\AbacusIdeHelper\\{$reflection->getNamespaceName()}\\_IH_{$reflection->getShortName()}_QB";
    }

    protected function formatParameters(ReflectionMethod $method, bool $skipFirst = false): string
    {
        $parameters = array_slice($method->getParameters(), $skipFirst ? 1 : 0);

        return implode(', ', array_map(fn (ReflectionParameter $parameter): string => $this->formatParameter($parameter), $parameters));
    }

    protected function formatParameter(ReflectionParameter $parameter): string
    {
        $type = $parameter->hasType() ? $this->formatType($parameter->getType()).' ' : '';
        $variadic = $parameter->isVariadic() ? '...' : '';
        $default = $parameter->isDefaultValueAvailable() ? ' = '.$this->formatDefault($parameter->getDefaultValue()) : '';

        return "{$type}{$variadic}\${$parameter->getName()}{$default}";
    }

    protected function formatType(ReflectionType $type): string
    {
        return preg_replace_callback(
            '/[A-Za-z_\\\\][A-Za-z0-9_\\\\]*/',
            fn (array $match): string => in_array(strtolower($match[0]), self::BUILTIN_TYPES, true) ? $match[0] : '\\'.ltrim($match[0], '\\'),
            (string) $type
        );
    }

    protected function formatDefault(mixed $value): string
    {
        return match (true) {
            $value === null => 'null',
            $value === [] => '[]',
            default => var_export($value, true),
        };
    }

    /**
     * @param  list<string>  $lines
     */
    protected function docBlock(array $lines): string
    {
        return "    /**\n".implode("\n", array_map(fn (string $line): string => "     * {$line}", $lines))."\n     */";
    }

    /**
     * @param  array<string, list<string>>  $modelDocs
     * @param  array<string, list<string>>  $builderDocs
     */
    protected function writeFiles(Filesystem $files, string $directory, array $modelDocs, array $builderDocs): void
    {
        $files->deleteDirectory($directory);
        $files->ensureDirectoryExists($directory);

        $files->put($directory.'/.gitignore', "*\n");

        foreach ($modelDocs as $namespace => $blocks) {
            $suffix = str_replace('\\', '_', $namespace);

            $files->put("$directory/_ide_helper_models_$suffix.php", $this->wrapNamespace($namespace, $blocks));
            $files->put("$directory/_ide_helper_model_builders_$suffix.php", $this->wrapNamespace("AbacusIdeHelper\\$namespace", $builderDocs[$namespace]));
        }
    }

    /**
     * @param  list<string>  $blocks
     */
    protected function wrapNamespace(string $namespace, array $blocks): string
    {
        return "<?php\n/** @noinspection all */\n\nnamespace {$namespace} {\n\n".implode("\n\n", $blocks)."\n}\n";
    }
}
