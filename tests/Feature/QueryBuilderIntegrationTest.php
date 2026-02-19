<?php

namespace Contoweb\AbacusApi\Tests\Feature;

use Contoweb\AbacusApi\Enums\ODataOperator;
use Contoweb\AbacusApi\Tests\Fixtures\TestSubject;
use Contoweb\AbacusApi\Tests\TestCase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;

class QueryBuilderIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    #[Test]
    public function it_executes_complex_filtered_query(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            '*' => Http::response([
                'value' => [
                    ['Id' => 1, 'FirstName' => 'John', 'LastName' => 'Doe', 'Age' => 25, 'IsActive' => true],
                    ['Id' => 2, 'FirstName' => 'Jane', 'LastName' => 'Smith', 'Age' => 30, 'IsActive' => true],
                ],
            ], 200),
        ]);

        $results = TestSubject::where('IsActive', ODataOperator::EQUALS, true)
            ->where('Age', ODataOperator::GREATER_THAN, 18)
            ->select(['Id', 'FirstName', 'LastName', 'Age'])
            ->orderBy('FirstName', 'asc')
            ->paginate(10);

        $this->assertCount(2, $results->items());
        $this->assertEquals('John', $results->items()[0]->FirstName);
        $this->assertEquals(25, $results->items()[0]->Age);
    }

    #[Test]
    public function it_executes_query_with_expand(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            '*' => Http::response([
                'value' => [
                    [
                        'Id' => 1,
                        'FirstName' => 'Subject',
                        'Addresses' => [
                            ['Street' => '123 Main St', 'City' => 'Springfield'],
                        ],
                        'Contacts' => [
                            ['Type' => 'Email', 'Value' => 'test@example.com'],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $results = TestSubject::expand(['Addresses', 'Contacts'])->paginate();

        $this->assertCount(1, $results->items());
        $this->assertIsArray($results->items()[0]->Addresses);
        $this->assertIsArray($results->items()[0]->Contacts);
        $this->assertEquals('123 Main St', $results->items()[0]->Addresses[0]['Street']);
    }

    #[Test]
    public function it_chains_multiple_conditions(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            '*' => Http::response([
                'value' => [
                    ['Id' => 1, 'FirstName' => 'Match', 'Age' => 25, 'Status' => 'Active', 'Score' => 85],
                ],
            ], 200),
        ]);

        $results = TestSubject::where('Status', 'eq', 'Active')
            ->where('Age', 'ge', 21)
            ->where('Age', 'le', 65)
            ->where('Score', 'gt', 80)
            ->select(['Id', 'FirstName', 'Age', 'Status', 'Score'])
            ->orderBy('Score', 'desc')
            ->paginate(5);

        $this->assertCount(1, $results->items());
        $this->assertEquals('Match', $results->items()[0]->FirstName);

        Http::assertSent(function ($request) {
            $url = $request->url();

            /* URL parameters are encoded, so check for the filter parameter */
            return str_contains($url, '%24filter') &&
                   str_contains($url, 'Status') &&
                   str_contains($url, 'Age') &&
                   str_contains($url, 'Score');
        });
    }

    #[Test]
    public function it_handles_first_result(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            '*' => Http::response([
                'value' => [
                    ['Id' => 42, 'FirstName' => 'First Item'],
                ],
            ], 200),
        ]);

        $result = TestSubject::where('IsActive', 'eq', true)
            ->orderBy('CreatedAt', 'desc')
            ->first();

        $this->assertInstanceOf(TestSubject::class, $result);
        $this->assertEquals('First Item', $result->FirstName);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '%24top=1');
        });
    }

    #[Test]
    public function it_handles_find_by_id(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            '*/api/entity/v1/mandants/test-mandate/Subjects(123)*' => Http::response([
                'Id' => 123,
                'FirstName' => 'Found',
                'LastName' => 'ByID',
                'Email' => 'found@example.com',
            ], 200),
        ]);

        $result = TestSubject::find(123);

        $this->assertEquals(123, $result->Id);
        $this->assertEquals('Found', $result->FirstName);
    }

    #[Test]
    public function it_handles_special_characters_in_values(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            '*' => Http::response([
                'value' => [
                    ['Id' => 1, 'FirstName' => "O'Brien"],
                ],
            ], 200),
        ]);

        $results = TestSubject::where('FirstName', 'eq', "O'Brien")->paginate();

        Http::assertSent(function ($request) {
            /* Single quotes should be escaped as double single quotes and URL encoded */
            return str_contains($request->url(), 'O%27%27Brien');
        });
    }

    #[Test]
    public function it_handles_multiple_select_and_expand_calls(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            '*' => Http::response(['value' => []], 200),
        ]);

        TestSubject::select(['Id', 'FirstName'])
            ->select('Email')
            ->expand('Addresses')
            ->expand(['Contacts', 'Orders'])
            ->paginate();

        Http::assertSent(function ($request) {
            $url = $request->url();

            return str_contains($url, '%24select=Id') &&
                   str_contains($url, 'FirstName') &&
                   str_contains($url, 'Email') &&
                   str_contains($url, '%24expand=Addresses') &&
                   str_contains($url, 'Contacts') &&
                   str_contains($url, 'Orders');
        });
    }
}
