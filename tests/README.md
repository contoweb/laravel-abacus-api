# Laravel Abacus API Tests

Comprehensive test suite for the Laravel Abacus API package.

## Test Structure

```
tests/
├── TestCase.php              # Base test case with Orchestra Testbench
├── Unit/                     # Unit tests for individual classes
│   ├── BaseAbacusClientTest.php
│   ├── AbacusClientTest.php
│   ├── AbacusServiceTest.php
│   ├── AbacusQueryBuilderTest.php
│   ├── AbacusModelTest.php
│   ├── ODataOperatorTest.php
│   ├── AbacusReportsClientTest.php
│   └── AbacusReportsServiceTest.php
├── Feature/                  # Integration tests
│   ├── AbacusServiceIntegrationTest.php
│   ├── QueryBuilderIntegrationTest.php
│   └── ReportsWorkflowTest.php
├── Console/                  # Console command tests
│   ├── MakeAbacusModelCommandTest.php
│   └── MakeAbacusReportCommandTest.php
├── Fixtures/                 # Test data fixtures
│   └── ResponseFixtures.php
└── Helpers/                  # Test helper utilities
    ├── HttpMockHelper.php
    └── AssertionHelper.php
```

## Running Tests

### Run All Tests
```bash
vendor/bin/phpunit
```

### Run Specific Test Suite
```bash
# Unit tests only
vendor/bin/phpunit --testsuite Unit

# Feature tests only
vendor/bin/phpunit --testsuite Feature

# Console tests only
vendor/bin/phpunit --testsuite Console
```

### Run Specific Test File
```bash
vendor/bin/phpunit tests/Unit/AbacusServiceTest.php
```

### Run Specific Test Method
```bash
vendor/bin/phpunit --filter it_performs_get_request
```

### Run with Coverage
```bash
vendor/bin/phpunit --coverage-html coverage
```

Then open `coverage/index.html` in your browser.

## Test Categories

### Unit Tests

Unit tests focus on testing individual classes in isolation with mocked dependencies.

- **BaseAbacusClientTest**: OAuth2 authentication, token management, HTTP methods
- **AbacusClientTest**: Path building and URL construction
- **AbacusServiceTest**: CRUD operations and OData queries
- **AbacusQueryBuilderTest**: Query building and OData parameter construction
- **AbacusModelTest**: Eloquent-like model interface
- **ODataOperatorTest**: Enum validation
- **AbacusReportsClientTest**: Report job submission and polling
- **AbacusReportsServiceTest**: Report workflow and validation

### Feature Tests

Feature tests verify complete workflows and integrations.

- **AbacusServiceIntegrationTest**: End-to-end CRUD workflows
- **QueryBuilderIntegrationTest**: Complex query scenarios
- **ReportsWorkflowTest**: Complete report execution flows

### Console Tests

Console tests verify Artisan command functionality.

- **MakeAbacusModelCommandTest**: Model generation command
- **MakeAbacusReportCommandTest**: Report generation command

## Test Helpers

### ResponseFixtures

Pre-built response data for consistent testing:

```php
use Abacus\AbacusApi\Tests\Fixtures\ResponseFixtures;

// Token response
$token = ResponseFixtures::successfulTokenResponse();

// Entity list
$entities = ResponseFixtures::entityListResponse(10);

// Paginated response
$page = ResponseFixtures::paginatedResponse(1, true);

// Report output
$output = ResponseFixtures::reportOutputResponse(5);
```

### HttpMockHelper

Simplified HTTP mocking for common scenarios:

```php
use Abacus\AbacusApi\Tests\Helpers\HttpMockHelper;

// Mock successful workflow
HttpMockHelper::mockSuccessfulWorkflow([
    '*/api/entities' => Http::response(['data' => []], 200),
]);

// Mock CRUD operations
HttpMockHelper::mockCrudWorkflow('Subjects', 1);

// Mock pagination
HttpMockHelper::mockPagination(3);

// Mock report execution
HttpMockHelper::mockReportExecution('test-report.avx', 'job-123', 2);
```

### AssertionHelper

Custom assertions for common test scenarios:

```php
use Abacus\AbacusApi\Tests\Helpers\AssertionHelper;

// Assert OData parameters
AssertionHelper::assertODataParametersInRequest([
    '$filter' => "Status eq 'Active'",
    '$top' => 10,
]);

// Assert entity operations
AssertionHelper::assertEntityCreated('Subjects', ['Name' => 'Test']);
AssertionHelper::assertEntityUpdated('Subjects', 1, ['Name' => 'Updated']);
AssertionHelper::assertEntityDeleted('Subjects', 1);

// Assert report submission
AssertionHelper::assertReportSubmitted('report.avx', ['param' => 'value']);

// Assert model state
AssertionHelper::assertModelIsDirty($model, 'Name');
AssertionHelper::assertModelIsClean($model);
```

## Writing Tests

### Example Unit Test

```php
use Abacus\AbacusApi\Tests\TestCase;
use Illuminate\Support\Facades\Http;

class MyTest extends TestCase
{
    /** @test */
    public function it_does_something(): void
    {
        Http::fake([
            '*/oauth/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            '*/api/entities' => Http::response([
                'value' => [['Id' => 1]],
            ], 200),
        ]);

        $result = $this->service->query('Entities');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('value', $result);
    }
}
```

### Example Feature Test

```php
use Abacus\AbacusApi\Tests\TestCase;
use Abacus\AbacusApi\Tests\Helpers\HttpMockHelper;

class MyFeatureTest extends TestCase
{
    /** @test */
    public function it_performs_complete_workflow(): void
    {
        HttpMockHelper::mockCrudWorkflow('Subjects', 1);

        // Create
        $created = $this->service->create('Subjects', ['Name' => 'Test']);

        // Read
        $found = $this->service->find('Subjects', 1);

        // Update
        $updated = $this->service->update('Subjects', 1, ['Name' => 'Updated']);

        // Delete
        $deleted = $this->service->delete('Subjects', 1);

        $this->assertTrue($deleted);
    }
}
```

## Best Practices

1. **Use descriptive test method names**: Start with `it_` followed by what the test verifies
2. **One assertion per concept**: Test one thing at a time
3. **Use helpers**: Leverage HttpMockHelper and AssertionHelper for cleaner tests
4. **Mock external calls**: Always mock HTTP requests to external APIs
5. **Clean up**: Use `setUp()` and `tearDown()` methods for test preparation and cleanup
6. **Test edge cases**: Include tests for error conditions and edge cases

## Coverage Goals

Target: 90%+ code coverage for all source files

Current coverage can be viewed by running:
```bash
vendor/bin/phpunit --coverage-text
```

## Continuous Integration

Tests are automatically run on every commit via GitHub Actions (if configured).

## Troubleshooting

### Common Issues

**Issue**: Tests fail with "Class not found"
**Solution**: Run `composer dump-autoload`

**Issue**: HTTP assertions fail unexpectedly
**Solution**: Check that `Http::fake()` is called before any HTTP operations

**Issue**: Cache-related test failures
**Solution**: Ensure `Cache::flush()` is called in `setUp()` method

## Contributing

When adding new features:
1. Write tests first (TDD approach)
2. Ensure all tests pass
3. Maintain or improve code coverage
4. Update this README if adding new test categories or helpers
