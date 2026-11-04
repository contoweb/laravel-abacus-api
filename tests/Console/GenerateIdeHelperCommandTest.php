<?php

namespace Contoweb\AbacusApi\Tests\Console;

use Contoweb\AbacusApi\Tests\Fixtures\TestScopedBatch;
use Contoweb\AbacusApi\Tests\Fixtures\TestScopedProduct;
use Contoweb\AbacusApi\Tests\TestCase;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;

class GenerateIdeHelperCommandTest extends TestCase
{
    protected string $outputDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->outputDir = sys_get_temp_dir().'/abacus_ide_helper_'.uniqid();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->outputDir);

        parent::tearDown();
    }

    /**
     * @param  list<class-string>  $models
     */
    protected function generate(array $models): void
    {
        $this->artisan('abacus:generate-ide-helper', [
            'model' => $models,
            '--source' => dirname(__DIR__).'/Fixtures/metadata.json',
            '--output-dir' => $this->outputDir,
        ])->assertExitCode(0);
    }

    protected function modelsFile(): string
    {
        return File::get($this->outputDir.'/_ide_helper_models_Contoweb_AbacusApi_Tests_Fixtures.php');
    }

    protected function buildersFile(): string
    {
        return File::get($this->outputDir.'/_ide_helper_model_builders_Contoweb_AbacusApi_Tests_Fixtures.php');
    }

    #[Test]
    public function it_writes_helper_files_and_gitignore(): void
    {
        $this->generate([TestScopedBatch::class]);

        $this->assertFileExists($this->outputDir.'/.gitignore');
        $this->assertFileExists($this->outputDir.'/_ide_helper_models_Contoweb_AbacusApi_Tests_Fixtures.php');
        $this->assertFileExists($this->outputDir.'/_ide_helper_model_builders_Contoweb_AbacusApi_Tests_Fixtures.php');
        $this->assertSame("*\n", File::get($this->outputDir.'/.gitignore'));
    }

    #[Test]
    public function it_generates_a_dedicated_builder_with_scopes(): void
    {
        $this->generate([TestScopedBatch::class]);

        $builders = $this->buildersFile();
        $builder = '\\AbacusIdeHelper\\Contoweb\\AbacusApi\\Tests\\Fixtures\\_IH_TestScopedBatch_QB';

        $this->assertStringContainsString('namespace AbacusIdeHelper\\Contoweb\\AbacusApi\\Tests\\Fixtures {', $builders);
        $this->assertStringContainsString('@extends \\Contoweb\\AbacusApi\\AbacusODataQueryBuilder<\\Contoweb\\AbacusApi\\Tests\\Fixtures\\TestScopedBatch>', $builders);
        $this->assertStringContainsString("@method {$builder} whereBatchNumber(string \$batchNumber)", $builders);
        $this->assertStringContainsString("@method {$builder} whereVariant(int \$variantId)", $builders);
        $this->assertStringContainsString("@method {$builder} active()", $builders);
        $this->assertStringContainsString('class _IH_TestScopedBatch_QB extends \\Contoweb\\AbacusApi\\AbacusODataQueryBuilder {}', $builders);
    }

    #[Test]
    public function it_ignores_private_and_non_scope_methods(): void
    {
        $this->generate([TestScopedBatch::class]);

        $this->assertStringNotContainsString('privateScope', $this->buildersFile());
        $this->assertStringNotContainsString('notAScope', $this->buildersFile());
        $this->assertStringNotContainsString('privateScope', $this->modelsFile());
    }

    #[Test]
    public function it_adds_builder_entry_points_scopes_and_mixin_to_the_model(): void
    {
        $this->generate([TestScopedBatch::class]);

        $models = $this->modelsFile();
        $builder = '\\AbacusIdeHelper\\Contoweb\\AbacusApi\\Tests\\Fixtures\\_IH_TestScopedBatch_QB';

        $this->assertStringContainsString("@method static {$builder} select(array|string \$fields)", $models);
        $this->assertStringContainsString("@method static {$builder} query()", $models);
        $this->assertStringContainsString("@method static {$builder} top(int \$limit)", $models);
        $this->assertStringContainsString("@method static {$builder} whereBatchNumber(string \$batchNumber)", $models);
        $this->assertStringContainsString("@mixin {$builder}", $models);
        $this->assertStringContainsString('class TestScopedBatch extends \\Contoweb\\AbacusApi\\Models\\AbacusModel {}', $models);
    }

    #[Test]
    public function it_generates_properties_from_casts_and_metadata(): void
    {
        $this->generate([TestScopedProduct::class]);

        $models = $this->modelsFile();

        $this->assertStringContainsString('@property \\Illuminate\\Support\\Collection<int, \\Contoweb\\AbacusApi\\Tests\\Fixtures\\TestScopedBatch>|null $Batches', $models);
    }

    #[Test]
    public function it_types_key_fields_without_null_and_other_fields_with_null(): void
    {
        $this->generate([TestScopedProduct::class]);

        $models = $this->modelsFile();

        $this->assertStringContainsString('@property int $Id', $models);
        $this->assertStringContainsString('@property string|null $Name', $models);
    }

    #[Test]
    public function it_falls_back_to_a_union_type_without_metadata_entry(): void
    {
        $this->artisan('abacus:generate-ide-helper', [
            'model' => [TestScopedProduct::class],
            '--source' => dirname(__DIR__).'/Fixtures/metadata-empty.json',
            '--output-dir' => $this->outputDir,
        ])->assertExitCode(0);

        $this->assertStringContainsString(
            '@property \\Illuminate\\Support\\Collection<int, \\Contoweb\\AbacusApi\\Tests\\Fixtures\\TestScopedBatch>|\\Contoweb\\AbacusApi\\Tests\\Fixtures\\TestScopedBatch|null $Batches',
            $this->modelsFile()
        );
        $this->assertStringContainsString('@property string|null $Name', $this->modelsFile());
    }

    #[Test]
    public function it_warns_when_the_resource_is_not_in_the_metadata(): void
    {
        $this->artisan('abacus:generate-ide-helper', [
            'model' => [TestScopedProduct::class],
            '--source' => dirname(__DIR__).'/Fixtures/metadata-empty.json',
            '--output-dir' => $this->outputDir,
        ])
            ->expectsOutputToContain("resource 'Products' not found in the metadata")
            ->assertExitCode(0);
    }

    #[Test]
    public function it_warns_when_a_cast_is_not_defined_in_the_metadata(): void
    {
        $this->artisan('abacus:generate-ide-helper', [
            'model' => [TestScopedProduct::class],
            '--source' => dirname(__DIR__).'/Fixtures/metadata-missing-field.json',
            '--output-dir' => $this->outputDir,
        ])
            ->expectsOutputToContain("'Batches' is cast but not defined in the metadata for 'Products'")
            ->doesntExpectOutputToContain("'Name' is cast but not defined")
            ->assertExitCode(0);
    }

    #[Test]
    public function it_does_not_warn_when_all_casts_are_defined_in_the_metadata(): void
    {
        $this->artisan('abacus:generate-ide-helper', [
            'model' => [TestScopedProduct::class],
            '--source' => dirname(__DIR__).'/Fixtures/metadata.json',
            '--output-dir' => $this->outputDir,
        ])
            ->doesntExpectOutputToContain('not found in the metadata')
            ->doesntExpectOutputToContain('is cast but not defined')
            ->assertExitCode(0);
    }

    #[Test]
    public function it_overwrites_previous_output(): void
    {
        File::ensureDirectoryExists($this->outputDir);
        File::put($this->outputDir.'/stale.php', '<?php');

        $this->generate([TestScopedBatch::class]);

        $this->assertFileDoesNotExist($this->outputDir.'/stale.php');
    }

    #[Test]
    public function it_fails_when_the_metadata_file_is_missing(): void
    {
        $this->artisan('abacus:generate-ide-helper', [
            '--source' => '/does/not/exist.json',
            '--output-dir' => $this->outputDir,
        ])->assertExitCode(1);
    }

    #[Test]
    public function it_fails_when_the_metadata_file_is_not_valid_json(): void
    {
        File::ensureDirectoryExists($this->outputDir);
        File::put($this->outputDir.'/invalid.json', '{nope');

        $this->artisan('abacus:generate-ide-helper', [
            '--source' => $this->outputDir.'/invalid.json',
            '--output-dir' => $this->outputDir.'/out',
        ])->assertExitCode(1);
    }

    #[Test]
    public function it_skips_classes_that_are_not_abacus_models(): void
    {
        $this->generate([self::class]);

        $this->assertFileDoesNotExist($this->outputDir.'/_ide_helper_models_Contoweb_AbacusApi_Tests_Console.php');
    }
}
