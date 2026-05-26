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
        $this->testReportsPath = base_path('app/Services/Abacus/Reports');
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

        $expectedPath = app_path('Services/Abacus/Reports/SalesReport.php');
        $this->assertFileExists($expectedPath);
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
    public function it_creates_correct_namespace(): void
    {
        $this->artisan('make:abacus-report', ['name' => 'TestReport'])
            ->assertExitCode(0);

        $path = app_path('Services/Abacus/Reports/TestReport.php');
        $content = File::get($path);

        $this->assertStringContainsString('namespace App\Services\Abacus\Reports;', $content);
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
    public function it_generates_valid_php_files(): void
    {
        $this->artisan('make:abacus-report', ['name' => 'ValidReport'])
            ->assertExitCode(0);

        $reportPath = app_path('Services/Abacus/Reports/ValidReport.php');
        $reportContent = File::get($reportPath);

        $this->assertStringStartsWith('<?php', $reportContent);
        $this->assertStringContainsString('use Contoweb\AbacusApi\Reports\Abstracts\Report;', $reportContent);
        $this->assertStringContainsString('mapping(array $record): array', $reportContent);
        $this->assertStringContainsString('return $record;', $reportContent);
    }
}
