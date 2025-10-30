<?php

namespace Contoweb\AbacusOdata\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

class GenerateIdeHelperCommand extends Command
{
    protected $signature = 'abacus:generate-ide-helper
                          {--url= : Override Swagger JSON URL from config}
                          {--file= : Override Swagger JSON file path from config}
                          {--output= : Override output file from config}';

    protected $description = 'Generate IDE helper file from Abacus Swagger JSON';

    protected array $typeMapping = [
        'string'  => 'string',
        'integer' => 'int',
        'number'  => 'float',
        'boolean' => 'bool',
        'array'   => 'array',
        'object'  => 'array',
    ];

    public function handle(): int
    {
        if ( ! config('abacus-odata.ide_helper.enabled')) {
            $this->info('IDE Helper generation is disabled in config.');

            return 0;
        }

        $url        = $this->option('url') ?? config('abacus-odata.ide_helper.swagger_url');
        $outputFile = base_path($this->option('output') ?? config('abacus-odata.ide_helper.output_file'));

        try {
            /* Fetch or read Swagger JSON */
            if ($url) {
                $this->info("Fetching Swagger JSON from: {$url}");

                $response = Http::timeout(30)->get($url);

                if ( ! $response->successful()) {
                    $this->error("Failed to fetch Swagger JSON: HTTP {$response->status()}");

                    return 1;
                }

                $swagger = $response->json();
            } else {
                $jsonFile = $this->option('file') ?? config('abacus-odata.ide_helper.swagger_json_file');

                $jsonPath = base_path($jsonFile);

                $this->info("Reading Swagger JSON from file: {$jsonPath}");

                if ( ! File::exists($jsonPath)) {
                    $this->error("Swagger JSON file not found: {$jsonPath}");

                    return 1;
                }

                $jsonContent = File::get($jsonPath);
                $swagger     = json_decode($jsonContent, true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    $this->error("Invalid JSON in file: " . json_last_error_msg());

                    return 1;
                }
            }

            if ( ! isset($swagger['definitions'])) {
                $this->error('Invalid Swagger format: missing definitions');

                return 1;
            }

            $this->info('Parsing definitions...');
            $models = $this->parseDefinitions($swagger['definitions']);

            $this->info('Generating IDE helper file...');
            $content = $this->generateIdeHelper($models);

            File::put($outputFile, $content);

            $this->info("✓ IDE helper generated: {$outputFile}");
            $this->comment('Restart your IDE or run "File → Invalidate Caches" in PhpStorm');

            return 0;
        } catch (\Exception $e) {
            $this->error("Error: {$e->getMessage()}");

            return 1;
        }
    }

    protected function parseDefinitions(array $definitions): array
    {
        $models = [];

        foreach ($definitions as $definitionName => $definition) {
            if ( ! $this->isEntity($definitionName)) {
                continue;
            }

            $modelName  = $this->getModelName($definitionName);
            $properties = $this->parseProperties($definition['properties'] ?? []);

            $models[$modelName] = [
                'resource'    => $modelName,
                'properties'  => $properties,
                'description' => $definition['description'] ?? null,
            ];
        }

        return $models;
    }

    protected function isEntity(string $definitionName): bool
    {
        $excludePatterns = ['DTO', 'Collection', 'Request', 'Response', 'Error', 'Metadata'];

        foreach ($excludePatterns as $pattern) {
            if (str_contains($definitionName, $pattern)) {
                return false;
            }
        }

        return true;
    }

    protected function getModelName(string $definitionName): string
    {
        $parts = explode('.', $definitionName);

        return end($parts);
    }

    protected function parseProperties(array $properties): array
    {
        $parsed = [];

        foreach ($properties as $name => $property) {
            $type        = $this->mapType($property);
            $description = $property['description'] ?? null;
            $nullable    = ! ($property['required'] ?? false);

            $parsed[$name] = [
                'type'        => $type,
                'nullable'    => $nullable,
                'description' => $description,
            ];
        }

        return $parsed;
    }

    protected function mapType(array $property): string
    {
        $type   = $property['type'] ?? 'string';
        $format = $property['format'] ?? null;

        if ($format === 'date-time' || $format === 'date') {
            return 'string';
        }

        if ($format === 'int32' || $format === 'int64') {
            return 'int';
        }

        if ($format === 'double' || $format === 'float') {
            return 'float';
        }

        if (is_array($type)) {
            $itemType       = $property['items']['type'] ?? 'mixed';
            $mappedItemType = $this->typeMapping[$itemType] ?? 'mixed';

            return "array<{$mappedItemType}>";
        }

        return $this->typeMapping[$type] ?? 'mixed';
    }

    protected function generateIdeHelper(array $models): string
    {
        $namespace = config('abacus-odata.models_namespace');

        $lines = [
            '<?php',
            '',
            '/**',
            ' * IDE Helper for Abacus REST Models',
            ' * ',
            ' * Generated with: php artisan abacus:generate-ide-helper',
            ' * Date: ' . now()->toDateTimeString(),
            ' * ',
            ' * DO NOT EDIT THIS FILE MANUALLY!',
            ' * This file is auto-generated and will be overwritten.',
            ' */',
            '',
            "namespace {$namespace} {",
            '',
        ];

        foreach ($models as $modelName => $model) {
            $lines[] = $this->generateModelBlock($modelName, $model, $namespace);
            $lines[] = '';
        }

        $lines[] = '}';
        $lines[] = '';

        return implode("\n", $lines);
    }

    protected function generateModelBlock(string $modelName, array $model, string $namespace): string
    {
        $lines = ['    /**'];

        if ($model['description']) {
            $lines[] = '     * ' . $model['description'];
            $lines[] = '     *';
        }

        foreach ($model['properties'] as $propertyName => $property) {
            $type = $property['type'];
            if ($property['nullable']) {
                $type .= '|null';
            }

            $line = "     * @property {$type} \${$propertyName}";

            if ($property['description']) {
                $line .= ' ' . $property['description'];
            }

            $lines[] = $line;
        }

        $lines[] = '     *';

        $methods = [
            "@method static {$modelName}|null find(\$id)",
            "@method static \\Illuminate\\Support\\Collection all()",
            "@method static \\Illuminate\\Support\\Collection firstPage()",
            "@method static {$modelName} create(array \$attributes)",
            "@method static \\contoweb\\AbacusRestOdata\\AbacusQueryBuilder query()",
            "@method static \\contoweb\\AbacusRestOdata\\AbacusQueryBuilder where(string \$field, string \$operator, mixed \$value)",
            "@method static \\contoweb\\AbacusRestOdata\\AbacusQueryBuilder whereEquals(string \$field, mixed \$value)",
            "@method static \\contoweb\\AbacusRestOdata\\AbacusQueryBuilder select(array|string \$fields)",
            "@method static \\contoweb\\AbacusRestOdata\\AbacusQueryBuilder orderBy(string \$field, string \$direction = 'asc')",
            "@method static \\contoweb\\AbacusRestOdata\\AbacusQueryBuilder top(int \$limit)",
            "@method static \\contoweb\\AbacusRestOdata\\AbacusQueryBuilder limit(int \$limit)",
            "@method static \\contoweb\\AbacusRestOdata\\AbacusQueryBuilder take(int \$limit)",
            "@method static \\contoweb\\AbacusRestOdata\\AbacusQueryBuilder expand(array|string \$relations)",
        ];

        foreach ($methods as $method) {
            $lines[] = '     * ' . $method;
        }

        $lines[] = '     */';
        $lines[] = "    class {$modelName} extends \\contoweb\\AbacusRestOdata\\Models\\AbacusModel {}";

        return implode("\n", $lines);
    }
}