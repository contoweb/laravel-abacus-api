<?php

namespace Contoweb\AbacusApi\Tests\Console;

use Contoweb\AbacusApi\Tests\TestCase;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;

class MakeAbacusReportCommandTest extends TestCase
{
    protected string $testReportsPath;

    protected function setUp(): void
    {
        parent::setUp();

        /* Set up a test reports path */
        $this->testReportsPath = base_path('app/Reports');
    }

    protected function tearDown(): void
    {
        /* Clean up any created test files */
        if (File::isDirectory($this->testReportsPath)) {
            File::deleteDirectory($this->testReportsPath);
        }

        parent::tearDown();
    }

    #[Test]
    public function it_creates_report_file(): void
    {
        $this->artisan('make:abacus-report', ['name' => 'SalesReport'])
            ->assertExitCode(0)
            ->expectsOutput('Report SalesReport created successfully.');

        $expectedPath = app_path('Reports/SalesReport.php');
        $this->assertFileExists($expectedPath);
    }

    #[Test]
    public function it_creates_report_with_model(): void
    {
        $this->artisan('make:abacus-report', [
            'name' => 'InvoiceReport',
            '--model' => 'InvoiceData',
        ])->assertExitCode(0)
            ->expectsOutput('Report InvoiceReport created successfully.')
            ->expectsOutput('Report model InvoiceData created successfully.');

        $reportPath = app_path('Reports/InvoiceReport.php');
        $modelPath = app_path('Reports/Models/InvoiceData.php');

        $this->assertFileExists($reportPath);
        $this->assertFileExists($modelPath);
    }

    #[Test]
    public function it_prevents_overwriting_existing_report(): void
    {
        /* Create report first time */
        $this->artisan('make:abacus-report', ['name' => 'DuplicateReport'])
            ->assertExitCode(0);

        /* Try to create again */
        $this->artisan('make:abacus-report', ['name' => 'DuplicateReport'])
            ->assertExitCode(1)
            ->expectsOutput('Report DuplicateReport already exists!');
    }

    #[Test]
    public function it_skips_model_if_already_exists(): void
    {
        /* Create report with model */
        $this->artisan('make:abacus-report', [
            'name' => 'FirstReport',
            '--model' => 'SharedModel',
        ])->assertExitCode(0);

        /* Create another report with same model name */
        $this->artisan('make:abacus-report', [
            'name' => 'SecondReport',
            '--model' => 'SharedModel',
        ])->assertExitCode(0)
            ->expectsOutput('Model SharedModel already exists, skipping model generation.');
    }

    #[Test]
    public function it_shows_tip_when_no_model_specified(): void
    {
        $this->artisan('make:abacus-report', ['name' => 'SimpleReport'])
            ->assertExitCode(0)
            ->expectsOutput('Tip: Use --model=ModelName to generate a report model class.');
    }

    #[Test]
    public function it_creates_correct_namespace(): void
    {
        $this->artisan('make:abacus-report', ['name' => 'TestReport'])
            ->assertExitCode(0);

        $path = app_path('Reports/TestReport.php');
        $content = File::get($path);

        $this->assertStringContainsString('namespace App\Reports;', $content);
        $this->assertStringContainsString('class TestReport extends Report', $content);
    }

    #[Test]
    public function it_creates_directory_if_not_exists(): void
    {
        /* Ensure directory doesn't exist */
        if (File::isDirectory($this->testReportsPath)) {
            File::deleteDirectory($this->testReportsPath);
        }

        $this->artisan('make:abacus-report', ['name' => 'NewReport'])
            ->assertExitCode(0);

        $this->assertDirectoryExists($this->testReportsPath);
    }

    #[Test]
    public function it_creates_models_subdirectory_for_model(): void
    {
        $this->artisan('make:abacus-report', [
            'name' => 'ComplexReport',
            '--model' => 'ComplexModel',
        ])->assertExitCode(0);

        $modelsPath = app_path('Reports/Models');
        $this->assertDirectoryExists($modelsPath);
    }

    #[Test]
    public function it_generates_valid_php_files(): void
    {
        $this->artisan('make:abacus-report', [
            'name' => 'ValidReport',
            '--model' => 'ValidModel',
        ])->assertExitCode(0);

        $reportPath = app_path('Reports/ValidReport.php');
        $modelPath = app_path('Reports/Models/ValidModel.php');

        $reportContent = File::get($reportPath);
        $modelContent = File::get($modelPath);

        /* Check for valid PHP syntax */
        $this->assertStringStartsWith('<?php', $reportContent);
        $this->assertStringStartsWith('<?php', $modelContent);

        /* Check for required imports */
        $this->assertStringContainsString('use Contoweb\AbacusApi\Reports\Abstracts\Report;', $reportContent);
    }
}
