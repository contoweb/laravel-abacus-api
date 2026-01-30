<?php

namespace Contoweb\AbacusApi\Console\Commands;

use Contoweb\AbacusApi\AbacusService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class GenerateIdeHelperCommand extends Command
{
    protected $signature = 'abacus:generate-ide-helper
                          {--output= : Override output file from config}
                          {--list : List all available entity types from metadata}';

    protected $description = 'Generate IDE helper file from Abacus OData metadata';

    protected array $typeMapping = [
        'Edm.String' => 'string',
        'Edm.Int32' => 'int',
        'Edm.Int64' => 'int',
        'Edm.Double' => 'float',
        'Edm.Decimal' => 'float',
        'Edm.Boolean' => 'bool',
        'Edm.DateTime' => 'string',
        'Edm.DateTimeOffset' => 'string',
        'Edm.Guid' => 'string',
        'Edm.Binary' => 'string',
        'Edm.Byte' => 'int',
        'Edm.SByte' => 'int',
        'Edm.Int16' => 'int',
        'Edm.Single' => 'float',
    ];

    public function __construct(protected AbacusService $abacusService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        if (! config('abacus-api.ide_helper.enabled')) {
            $this->info('IDE Helper generation is disabled in config.');

            return 0;
        }

        $outputFile = base_path($this->option('output') ?? config('abacus-api.ide_helper.output_file'));

        try {
            /* First, scan user models */
            $this->info('Scanning user models...');

            $namespace = config('abacus-api.models_namespace');
            $modelsPath = $this->namespaceToPath($namespace);
            $userModelsInfo = [];

            if (is_dir($modelsPath)) {
                $files = File::glob($modelsPath.'/*.php');

                foreach ($files as $file) {
                    $modelInfo = $this->extractModelInfo($file, $namespace);
                    if ($modelInfo) {
                        $userModelsInfo[] = $modelInfo;
                    }
                }
            }

            /* Try to load from local definition files based on user models */
            if (! empty($userModelsInfo)) {
                $this->info('Looking for local endpoint definition files...');

                $definitionsPath = storage_path('app/abacus/endpoint-definitions');
                $models = [];

                if (is_dir($definitionsPath)) {
                    $models = $this->loadDefinitionsForModels($definitionsPath, $userModelsInfo);
                }

                if (! empty($models)) {
                    $this->info('Loaded '.count($models).' entity definitions from local files');

                    /* If --list flag is set, display all entities and exit */
                    if ($this->option('list')) {
                        $this->listEntities($models);

                        return 0;
                    }

                    $this->info('Generating IDE helper file...');
                    $content = $this->generateIdeHelper($models);

                    File::put($outputFile, $content);

                    $this->info("✓ IDE helper generated: {$outputFile}");
                    $this->comment('Restart your IDE or run "File → Invalidate Caches" in PhpStorm');

                    return 0;
                }
            }

            /* Fallback: Fetch OData metadata from API */
            $this->comment('No local definition files found, fetching from API...');
            $this->info('Fetching OData metadata from Abacus API...');

            $metadataContent = $this->abacusService->metadata();

            if (empty($metadataContent)) {
                $this->error('Failed to fetch metadata from API');

                return 1;
            }

            /* Detect format: XML or JSON */
            $this->info('Parsing OData metadata...');

            $isXml = str_starts_with(trim($metadataContent), '<');
            $this->comment('Detected format: '.($isXml ? 'XML' : 'JSON'));

            if ($isXml) {
                /* Parse XML metadata */
                libxml_use_internal_errors(true);
                $xml = simplexml_load_string($metadataContent);

                if ($xml === false) {
                    $this->error('Failed to parse XML metadata:');
                    foreach (libxml_get_errors() as $error) {
                        $this->error("  Line {$error->line}: {$error->message}");
                    }
                    libxml_clear_errors();

                    return 1;
                }

                /* Register namespaces */
                $xml->registerXPathNamespace('edmx', 'http://docs.oasis-open.org/odata/ns/edmx');
                $xml->registerXPathNamespace('edm', 'http://docs.oasis-open.org/odata/ns/edm');

                /* Extract entity types from XML */
                $entityTypeElements = $xml->xpath('//edm:EntityType');

                if (empty($entityTypeElements)) {
                    $this->error('No EntityTypes found in XML metadata');

                    return 1;
                }

                $models = $this->parseXmlEntityTypes($entityTypeElements);
            } else {
                /* Parse JSON metadata */
                $metadata = json_decode($metadataContent, true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    $this->error('Failed to parse JSON metadata: '.json_last_error_msg());
                    $this->comment('First 500 characters of response:');
                    $this->line(substr($metadataContent, 0, 500));

                    return 1;
                }

                /* Extract entity types from JSON */
                $entityTypes = $this->extractEntityTypesFromJson($metadata);

                if (empty($entityTypes)) {
                    $this->error('No EntityTypes found in metadata');

                    return 1;
                }

                $this->info('Found '.count($entityTypes).' entity types');
                $this->info('Parsing entity definitions...');
                $models = $this->parseEntityTypes($entityTypes);
            }

            if (empty($models)) {
                $this->error('No entity models could be parsed');

                return 1;
            }

            $this->info('Found '.count($models).' entity types');

            /* If --list flag is set, display all entities and exit */
            if ($this->option('list')) {
                $this->listEntities($models);

                return 0;
            }

            /* Scan for user's model classes and map them to entities */
            $userModels = $this->scanUserModels($models);

            $this->info('Generating IDE helper file...');
            $content = $this->generateIdeHelper($userModels);

            File::put($outputFile, $content);

            $this->info("✓ IDE helper generated: {$outputFile}");
            $this->comment('Restart your IDE or run "File → Invalidate Caches" in PhpStorm');

            return 0;
        } catch (\Exception $e) {
            $this->error("Error: {$e->getMessage()}");

            return 1;
        }
    }

    protected function loadDefinitionsForModels(string $definitionsPath, array $userModelsInfo): array
    {
        $models = [];

        foreach ($userModelsInfo as $modelInfo) {
            $className = $modelInfo['class'];
            $resource = $modelInfo['resource'];

            /* Convert resource name to filename (e.g., Products → products.json) */
            $fileName = strtolower($resource).'.json';
            $filePath = $definitionsPath.'/'.$fileName;

            if (! File::exists($filePath)) {
                $this->warn("  {$className}: Definition file not found: {$fileName}");

                continue;
            }

            $this->comment("  {$className}: Loading {$fileName}...");

            $content = File::get($filePath);
            $swagger = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->warn('    Failed to parse: '.json_last_error_msg());

                continue;
            }

            /* Extract the schema name from the GET response */
            $schemaName = $this->extractSchemaName($swagger);

            if (! $schemaName) {
                $this->warn('    Could not find schema reference');

                continue;
            }

            /* Extract the schema definition */
            $schema = $swagger['components']['schemas'][$schemaName] ?? null;

            if (! $schema) {
                $this->warn("    Schema {$schemaName} not found");

                continue;
            }

            /* Parse properties */
            $properties = $this->parseSwaggerProperties($schema['properties'] ?? []);

            /* Use the last part of the schema name as the entity name for description */
            $entityName = $this->extractEntityName($schemaName);

            $models[$className] = [
                'resource' => $className,
                'properties' => $properties,
                'description' => $resource !== $className ? "From {$resource} ({$entityName})" : null,
            ];

            $this->comment("    → {$className} from {$entityName}");
        }

        return $models;
    }

    protected function extractSchemaName(array $swagger): ?string
    {
        /* Look for the main GET endpoint response */
        $paths = $swagger['paths'] ?? [];

        foreach ($paths as $path => $methods) {
            if (isset($methods['get']['responses']['200']['content']['application/json']['schema'])) {
                $schema = $methods['get']['responses']['200']['content']['application/json']['schema'];

                /* Look for $ref in the value array items */
                if (isset($schema['properties']['value']['items']['$ref'])) {
                    $ref = $schema['properties']['value']['items']['$ref'];

                    /* Extract schema name from #/components/schemas/ch.abacus.orde.ProductData */
                    return str_replace('#/components/schemas/', '', $ref);
                }
            }
        }

        return null;
    }

    protected function extractEntityName(string $schemaName): string
    {
        /* Extract the last part after the last dot (e.g., ch.abacus.orde.ProductData → ProductData) */
        $parts = explode('.', $schemaName);

        return end($parts);
    }

    protected function parseSwaggerProperties(array $properties): array
    {
        $parsed = [];

        foreach ($properties as $name => $property) {
            /* Handle $ref properties (complex types) */
            if (isset($property['$ref'])) {
                $parsed[$name] = [
                    'type' => 'array', /* Complex types represented as array */
                    'nullable' => true,
                    'description' => 'Complex type: '.$this->extractEntityName($property['$ref']),
                ];

                continue;
            }

            $type = $this->mapSwaggerType($property);
            $description = $property['description'] ?? null;
            $nullable = true; /* OpenAPI doesn't have explicit nullable in this format */

            $parsed[$name] = [
                'type' => $type,
                'nullable' => $nullable,
                'description' => $description,
            ];
        }

        return $parsed;
    }

    protected function mapSwaggerType(array $property): string
    {
        $type = $property['type'] ?? 'string';
        $format = $property['format'] ?? null;

        if ($type === 'integer' || $format === 'int32' || $format === 'int64') {
            return 'int';
        }

        if ($type === 'number' || $format === 'float' || $format === 'double') {
            return 'float';
        }

        if ($type === 'boolean') {
            return 'bool';
        }

        if ($type === 'array') {
            return 'array';
        }

        return 'string';
    }

    protected function parseXmlEntityTypes(array $entityTypeElements): array
    {
        $models = [];

        foreach ($entityTypeElements as $entityType) {
            $entityType->registerXPathNamespace('edm', 'http://docs.oasis-open.org/odata/ns/edm');

            $entityName = (string) $entityType['Name'];

            if (! $this->isEntity($entityName)) {
                continue;
            }

            /* Extract properties */
            $properties = [];
            $propertyElements = $entityType->xpath('.//edm:Property');

            foreach ($propertyElements as $property) {
                $propertyName = (string) $property['Name'];
                $propertyType = (string) $property['Type'];
                $nullable = ! isset($property['Nullable']) || (string) $property['Nullable'] === 'true';

                $properties[$propertyName] = [
                    'type' => $this->mapODataType($propertyType),
                    'nullable' => $nullable,
                    'description' => null,
                ];
            }

            $models[$entityName] = [
                'resource' => $entityName,
                'properties' => $properties,
                'description' => null,
            ];
        }

        return $models;
    }

    protected function extractEntityTypesFromJson(array $metadata): array
    {
        $entityTypes = [];
        $namespaceCount = 0;

        foreach ($metadata as $namespace => $namespaceContent) {
            /* Skip metadata keys that start with $ */
            if (str_starts_with($namespace, '$')) {
                continue;
            }

            /* This is a namespace (e.g., ch.abacus.lohn) */
            if (is_array($namespaceContent)) {
                $namespaceEntityCount = 0;

                foreach ($namespaceContent as $typeName => $typeDefinition) {
                    /* Check if this is an EntityType */
                    if (is_array($typeDefinition) && isset($typeDefinition['$Kind']) && $typeDefinition['$Kind'] === 'EntityType') {
                        $entityTypes[$typeName] = $typeDefinition;
                        $namespaceEntityCount++;
                    }
                }

                if ($namespaceEntityCount > 0) {
                    $namespaceCount++;
                }
            }
        }

        return $entityTypes;
    }

    protected function parseEntityTypes(array $entityTypes): array
    {
        $models = [];

        foreach ($entityTypes as $entityName => $entityType) {
            if (! $this->isEntity($entityName)) {
                continue;
            }

            /* Extract properties */
            $properties = [];

            foreach ($entityType as $propertyName => $propertyValue) {
                /* Skip special OData keys that start with $ */
                if (str_starts_with($propertyName, '$')) {
                    continue;
                }

                /* This is a property definition */
                if (is_array($propertyValue)) {
                    $propertyType = $propertyValue['$Type'] ?? 'Edm.String';
                    $nullable = $propertyValue['$Nullable'] ?? true;

                    $properties[$propertyName] = [
                        'type' => $this->mapODataType($propertyType),
                        'nullable' => $nullable,
                        'description' => null,
                    ];
                }
            }

            $models[$entityName] = [
                'resource' => $entityName,
                'properties' => $properties,
                'description' => null,
            ];
        }

        return $models;
    }

    protected function isEntity(string $entityName): bool
    {
        $excludePatterns = ['DTO', 'Collection', 'Request', 'Response', 'Error', 'Metadata'];

        foreach ($excludePatterns as $pattern) {
            if (str_contains($entityName, $pattern)) {
                return false;
            }
        }

        return true;
    }

    protected function mapODataType(string $odataType): string
    {
        return $this->typeMapping[$odataType] ?? 'mixed';
    }

    protected function listEntities(array $models): void
    {
        $this->info('');
        $this->info('Available Entity Types in Metadata:');
        $this->info('=====================================');
        $this->info('');

        $entityNames = array_keys($models);
        sort($entityNames);

        $columns = 3;
        $rows = (int) ceil(count($entityNames) / $columns);

        for ($i = 0; $i < $rows; $i++) {
            $line = '';
            for ($col = 0; $col < $columns; $col++) {
                $index = $i + ($col * $rows);
                if (isset($entityNames[$index])) {
                    $line .= str_pad($entityNames[$index], 35);
                }
            }
            $this->line($line);
        }

        $this->info('');
        $this->info('Total: '.count($entityNames).' entity types');
        $this->info('');
        $this->comment('Use these entity names in your model\'s $resource property.');
    }

    protected function scanUserModels(array $entityModels): array
    {
        $namespace = config('abacus-api.models_namespace');
        $modelsPath = $this->namespaceToPath($namespace);

        if (! is_dir($modelsPath)) {
            $this->comment("No models directory found at: {$modelsPath}");
            $this->comment('Generating IDE helper for all entity types from metadata...');

            return $entityModels;
        }

        $userModels = [];
        $files = File::glob($modelsPath.'/*.php');

        foreach ($files as $file) {
            $modelInfo = $this->extractModelInfo($file, $namespace);

            if (! $modelInfo) {
                continue;
            }

            $className = $modelInfo['class'];
            $resource = $modelInfo['resource'];

            /* Find the entity in metadata */
            if (isset($entityModels[$resource])) {
                $userModels[$className] = [
                    'resource' => $className,
                    'properties' => $entityModels[$resource]['properties'],
                    'description' => $resource !== $className ? "Mapped from {$resource}" : null,
                ];

                if ($resource !== $className) {
                    $this->comment("  {$className} → {$resource}");
                }
            } else {
                $this->warn("Warning: Entity '{$resource}' not found in metadata for model '{$className}'");
            }
        }

        if (empty($userModels)) {
            $this->comment('No user models found, generating IDE helper for all entity types...');

            return $entityModels;
        }

        $this->info('Found '.count($userModels).' user models');

        return $userModels;
    }

    protected function namespaceToPath(string $namespace): string
    {
        /* Convert namespace to path (e.g., App\Models\Abacus → app/Models/Abacus) */
        $relativePath = str_replace('\\', '/', $namespace);
        $relativePath = str_replace('App/', 'app/', $relativePath);

        return base_path($relativePath);
    }

    protected function extractModelInfo(string $filePath, string $namespace): ?array
    {
        $content = File::get($filePath);
        $fileName = basename($filePath, '.php');

        /* Extract the $resource property */
        if (preg_match('/protected\s+static\s+string\s+\$resource\s*=\s*[\'"]([^\'"]+)[\'"]/', $content, $matches)) {
            return [
                'class' => $fileName,
                'resource' => $matches[1],
            ];
        }

        /* If no $resource is defined, assume it matches the class name */
        return [
            'class' => $fileName,
            'resource' => $fileName,
        ];
    }

    protected function generateIdeHelper(array $models): string
    {
        $namespace = config('abacus-api.models_namespace');

        $lines = [
            '<?php',
            '',
            '/**',
            ' * IDE Helper for Abacus REST Models',
            ' * ',
            ' * Generated with: php artisan abacus:generate-ide-helper',
            ' * Date: '.now()->toDateTimeString(),
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
            $lines[] = '     * '.$model['description'];
            $lines[] = '     *';
        }

        foreach ($model['properties'] as $propertyName => $property) {
            $type = $property['type'];
            if ($property['nullable']) {
                $type .= '|null';
            }

            $line = "     * @property {$type} \${$propertyName}";

            if ($property['description']) {
                $line .= ' '.$property['description'];
            }

            $lines[] = $line;
        }

        $lines[] = '     *';

        $methods = [
            "@method static {$modelName}|null find(\$id)",
            "@method static {$modelName}|null first()",
            '@method static \\Illuminate\\Support\\Collection|static[] all()',
            '@method static \\Illuminate\\Support\\Collection|static[] firstPage()',
            '@method static \\Illuminate\\Support\\Collection|static[] get()',
            "@method static {$modelName} create(array \$attributes)",
            '@method static \\Contoweb\\AbacusApi\\AbacusQueryBuilder query()',
            '@method static \\Contoweb\\AbacusApi\\AbacusQueryBuilder where(string $field, string $operator, mixed $value)',
            '@method static \\Contoweb\\AbacusApi\\AbacusQueryBuilder whereEquals(string $field, mixed $value)',
            '@method static \\Contoweb\\AbacusApi\\AbacusQueryBuilder select(array|string $fields)',
            "@method static \\Contoweb\\AbacusApi\\AbacusQueryBuilder orderBy(string \$field, string \$direction = 'asc')",
            '@method static \\Contoweb\\AbacusApi\\AbacusQueryBuilder top(int $limit)',
            '@method static \\Contoweb\\AbacusApi\\AbacusQueryBuilder limit(int $limit)',
            '@method static \\Contoweb\\AbacusApi\\AbacusQueryBuilder take(int $limit)',
            '@method static \\Contoweb\\AbacusApi\\AbacusQueryBuilder expand(array|string $relations)',
        ];

        foreach ($methods as $method) {
            $lines[] = '     * '.$method;
        }

        $lines[] = '     */';
        $lines[] = "    class {$modelName} extends \\Contoweb\\AbacusApi\\Models\\AbacusModel {}";

        return implode("\n", $lines);
    }
}
