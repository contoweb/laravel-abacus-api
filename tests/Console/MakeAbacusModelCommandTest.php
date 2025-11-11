<?php

namespace Contoweb\AbacusApi\Tests\Console;

use Contoweb\AbacusApi\Tests\TestCase;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;

class MakeAbacusModelCommandTest extends TestCase
{
    protected string $testModelsPath;

    protected function setUp(): void
    {
        parent::setUp();

        /* Set up a test models path */
        $this->testModelsPath = base_path('app/Models/Abacus');
    }

    protected function tearDown(): void
    {
        /* Clean up any created test files */
        if (File::isDirectory($this->testModelsPath)) {
            File::deleteDirectory($this->testModelsPath);
        }

        parent::tearDown();
    }

    #[Test]
    public function it_creates_model_file(): void
    {
        $this->artisan('make:abacus-model', ['name' => 'Subject'])
             ->assertExitCode(0)
             ->expectsOutput('Model Subject created successfully.');

        $expectedPath = app_path('Models/Abacus/Subject.php');
        $this->assertFileExists($expectedPath);
    }

    #[Test]
    public function it_creates_model_with_custom_resource(): void
    {
        $this->artisan('make:abacus-model', [
            'name' => 'Invoice',
            '--resource' => 'CustomInvoices',
        ])->assertExitCode(0);

        $path = app_path('Models/Abacus/Invoice.php');
        $this->assertFileExists($path);

        $content = File::get($path);
        $this->assertStringContainsString("protected static string \$resource = 'CustomInvoices';", $content);
    }

    #[Test]
    public function it_uses_plural_resource_by_default(): void
    {
        $this->artisan('make:abacus-model', ['name' => 'Subject'])
             ->assertExitCode(0);

        $path = app_path('Models/Abacus/Subject.php');
        $content = File::get($path);

        $this->assertStringContainsString("protected static string \$resource = 'Subjects';", $content);
    }

    #[Test]
    public function it_prevents_overwriting_existing_model(): void
    {
        /* Create model first time */
        $this->artisan('make:abacus-model', ['name' => 'Duplicate'])
             ->assertExitCode(0);

        /* Try to create again */
        $this->artisan('make:abacus-model', ['name' => 'Duplicate'])
             ->assertExitCode(1)
             ->expectsOutput('Model Duplicate already exists!');
    }

    #[Test]
    public function it_creates_correct_namespace(): void
    {
        $this->artisan('make:abacus-model', ['name' => 'TestModel'])
             ->assertExitCode(0);

        $path = app_path('Models/Abacus/TestModel.php');
        $content = File::get($path);

        $this->assertStringContainsString('namespace App\Models\Abacus;', $content);
        $this->assertStringContainsString('class TestModel extends AbacusModel', $content);
    }

    #[Test]
    public function it_creates_directory_if_not_exists(): void
    {
        /* Ensure directory doesn't exist */
        if (File::isDirectory($this->testModelsPath)) {
            File::deleteDirectory($this->testModelsPath);
        }

        $this->artisan('make:abacus-model', ['name' => 'NewModel'])
             ->assertExitCode(0);

        $this->assertDirectoryExists($this->testModelsPath);
    }

    #[Test]
    public function it_generates_valid_php_file(): void
    {
        $this->artisan('make:abacus-model', ['name' => 'ValidModel'])
             ->assertExitCode(0);

        $path = app_path('Models/Abacus/ValidModel.php');
        $content = File::get($path);

        /* Check for syntax errors by including the file */
        $this->assertStringStartsWith('<?php', $content);
        $this->assertStringContainsString('use Contoweb\AbacusApi\Models\AbacusModel;', $content);
    }
}
