<?php

namespace Contoweb\AbacusApi\Console\Commands;

use Exception;
use Illuminate\Console\Command;

class GenerateIdeHelperCommand extends Command
{
    protected $signature = 'abacus:generate-ide-helper
                          {--output= : Override output file from config}
                          {--source= : Absolute path to the OData metadata XML file}';

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

    public function handle(): int
    {
        if (! config('abacus-api.ide_helper.enabled')) {
            $this->info('IDE Helper generation is disabled in config.');

            return 0;
        }
        $this->info($this->option('source'));
        $outputFile = base_path($this->option('output') ?? config('abacus-api.ide_helper.output_file'));

        try {
            if ($this->option('source')) {
                $metadataPath = $this->option('source');
            } else {
                $metadataPath = dirname(__DIR__, 3).'/resources/metadata/metadata-2025-203-p-2025-10-15.xml';
            }

            if (! file_exists($metadataPath)) {
                $this->error('Metadata file not found: '.$metadataPath);

                return 1;
            }

            $doc = simplexml_load_file($metadataPath);

            if ($doc === false) {
                $this->error('Failed to parse metadata XML');

                return 1;
            }

            $namespaces = $doc->getNamespaces(true);
            $doc->registerXPathNamespace('edmx', $namespaces['edmx'] ?? 'http://docs.oasis-open.org/odata/ns/edmx');
            $doc->registerXPathNamespace('edm', $namespaces[''] ?? 'http://docs.oasis-open.org/odata/ns/edm');

            /* Collect all EntityTypes with their properties */
            $entityTypes = [];

            foreach ($doc->xpath('//edm:Schema') as $schema) {

                $schema->registerXPathNamespace(
                    'edm',
                    'http://docs.oasis-open.org/odata/ns/edm'
                );

                $ns = (string) $schema['Namespace'];

                foreach ($schema->xpath('edm:EntityType') as $et) {

                    $et->registerXPathNamespace(
                        'edm',
                        'http://docs.oasis-open.org/odata/ns/edm'
                    );

                    $name = (string) $et['Name'];
                    $fqn = "$ns.$name";

                    $props = [];

                    foreach ($et->xpath('edm:Property') as $prop) {
                        $props[] = [
                            'name' => (string) $prop['Name'],
                            'type' => (string) $prop['Type'],
                            'nullable' => (string) ($prop['Nullable'] ?? 'true'),
                            'maxLength' => (string) ($prop['MaxLength'] ?? ''),
                        ];
                    }

                    foreach ($et->xpath('edm:NavigationProperty') as $nav) {
                        $props[] = [
                            'name' => (string) $nav['Name'],
                            'type' => (string) $nav['Type'],
                            'nullable' => '',
                            'maxLength' => '',
                            'navigation' => true,
                        ];
                    }

                    $entityTypes[$fqn] = $props;
                }
            }

            /* Collect EntitySets from EntityContainer (endpoint -> EntityType mapping) */
            $entitySets = [];
            foreach ($doc->xpath('//edm:EntityContainer/edm:EntitySet') as $es) {
                $entitySets[] = [
                    'resource' => (string) $es['Name'],
                    'entityType' => (string) $es['EntityType'],
                ];
            }

            /* Build entity models keyed by resource name with PHP types */
            $entityModels = [];
            foreach ($entitySets as $es) {
                $resource = $es['resource'];
                $entityType = $es['entityType'];

                if (! isset($entityTypes[$entityType])) {
                    continue;
                }

                $properties = [];
                foreach ($entityTypes[$entityType] as $prop) {
                    $phpType = isset($prop['navigation']) ? 'array' : $this->mapODataType($prop['type']);

                    $isNullable = $prop['nullable'] === 'true';

                    $description = ! empty($prop['maxLength']) ? "Max length: {$prop['maxLength']}" : '';

                    $properties[$prop['name']] = [
                        'type' => $phpType,
                        'nullable' => $isNullable,
                        'description' => $description,
                    ];
                }

                $entityModels[$resource] = [
                    'properties' => $properties,
                    'description' => null,
                ];
            }

            $this->info('Found '.count($entityModels).' entity types in metadata');

            $this->info('Generating IDE helper file...');
            $content = $this->generateIdeHelper($entityModels);

            file_put_contents($outputFile, $content);

            $this->info("✓ IDE helper generated: {$outputFile}");
            $this->comment('Restart your IDE or run "File → Invalidate Caches" in PhpStorm');

            return 0;
        } catch (Exception $e) {
            $this->error("Error: {$e->getMessage()}");

            return 1;
        }
    }

    protected function mapODataType(string $odataType): string
    {
        return $this->typeMapping[$odataType] ?? 'mixed';
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

        if (! empty($model['description'])) {
            $lines[] = '     * '.$model['description'];
            $lines[] = '     *';
        }

        foreach ($model['properties'] as $propertyName => $property) {
            $type = $property['type'];
            if ($property['nullable']) {
                $type .= '|null';
            }

            $line = "     * @property {$type} \${$propertyName}";

            if (! empty($property['description'])) {
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
