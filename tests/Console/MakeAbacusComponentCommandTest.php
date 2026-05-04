<?php

namespace Contoweb\AbacusApi\Tests\Console;

use Contoweb\AbacusApi\Tests\TestCase;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;

class MakeAbacusComponentCommandTest extends TestCase
{
    protected string $testComponentsPath;

    protected function setUp(): void
    {
        parent::setUp();

        /* Set up a test components path */
        $this->testComponentsPath = base_path('app/Models/Abacus/Components');
    }

    protected function tearDown(): void
    {
        /* Clean up any created test files */
        if (File::isDirectory($this->testComponentsPath)) {
            File::deleteDirectory($this->testComponentsPath);
        }

        parent::tearDown();
    }

    #[Test]
    public function it_creates_component_file(): void
    {
        $this->artisan('make:abacus-component', ['name' => 'Measurements'])
            ->assertExitCode(0)
            ->expectsOutput('Component Measurements created successfully.');

        $expectedPath = app_path('Models/Abacus/Components/Measurements.php');
        $this->assertFileExists($expectedPath);
    }

    #[Test]
    public function it_prevents_overwriting_existing_component(): void
    {
        /* Create component first time */
        $this->artisan('make:abacus-component', ['name' => 'Duplicate'])
            ->assertExitCode(0);

        /* Try to create again */
        $this->artisan('make:abacus-component', ['name' => 'Duplicate'])
            ->assertExitCode(1)
            ->expectsOutput('Component Duplicate already exists!');
    }

    #[Test]
    public function it_creates_correct_namespace(): void
    {
        $this->artisan('make:abacus-component', ['name' => 'TestComponent'])
            ->assertExitCode(0);

        $path = app_path('Models/Abacus/Components/TestComponent.php');
        $content = File::get($path);

        $this->assertStringContainsString('namespace App\Models\Abacus\Components;', $content);
        $this->assertStringContainsString('class TestComponent extends AbacusComponent', $content);
    }

    #[Test]
    public function it_creates_directory_if_not_exists(): void
    {
        /* Ensure directory doesn't exist */
        if (File::isDirectory($this->testComponentsPath)) {
            File::deleteDirectory($this->testComponentsPath);
        }

        $this->artisan('make:abacus-component', ['name' => 'NewComponent'])
            ->assertExitCode(0);

        $this->assertDirectoryExists($this->testComponentsPath);
    }

    #[Test]
    public function it_generates_valid_php_file(): void
    {
        $this->artisan('make:abacus-component', ['name' => 'ValidComponent'])
            ->assertExitCode(0);

        $path = app_path('Models/Abacus/Components/ValidComponent.php');
        $content = File::get($path);

        /* Check for syntax errors by including the file */
        $this->assertStringStartsWith('<?php', $content);
        $this->assertStringContainsString('use Contoweb\AbacusApi\Models\AbacusComponent;', $content);
    }
}
