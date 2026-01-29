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
            ->top(10)
            ->get();

        $this->assertCount(2, $results);
        $this->assertEquals('John', $results[0]->FirstName);
        $this->assertEquals(25, $results[0]->Age);
    }

    #[Test]
    public function it_follows_pagination_automatically(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            '*/api/entity/v1/mandants/test-mandate/Subjects*' => Http::response([
                'value' => [
                    ['Id' => 1, 'FirstName' => 'Item 1'],
                    ['Id' => 2, 'FirstName' => 'Item 2'],
                ],
                '@odata.nextLink' => 'https://api.example.com/page2',
            ], 200),
            'https://api.example.com/page2' => Http::response([
                'value' => [
                    ['Id' => 3, 'FirstName' => 'Item 3'],
                    ['Id' => 4, 'FirstName' => 'Item 4'],
                ],
                '@odata.nextLink' => 'https://api.example.com/page3',
            ], 200),
            'https://api.example.com/page3' => Http::response([
                'value' => [
                    ['Id' => 5, 'FirstName' => 'Item 5'],
                ],
            ], 200),
        ]);

        $results = TestSubject::cursor()->pages(10)->get();

        /* Should automatically fetch all 3 pages */
        $this->assertCount(5, $results);
        $this->assertEquals('Item 1', $results[0]->FirstName);
        $this->assertEquals('Item 5', $results[4]->FirstName);
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

        $results = TestSubject::expand(['Addresses', 'Contacts'])->get();

        $this->assertCount(1, $results);
        $this->assertIsArray($results[0]->Addresses);
        $this->assertIsArray($results[0]->Contacts);
        $this->assertEquals('123 Main St', $results[0]->Addresses[0]['Street']);
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
            ->top(5)
            ->get();

        $this->assertCount(1, $results);
        $this->assertEquals('Match', $results[0]->FirstName);

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

        $results = TestSubject::where('FirstName', 'eq', "O'Brien")->get();

        Http::assertSent(function ($request) {
            /* Single quotes should be escaped as double single quotes and URL encoded */
            return str_contains($request->url(), "O%27%27Brien");
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
            ->get();

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

    #[Test]
    public function it_handles_cursor_pagination_with_callback(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            '*/api/entity/v1/mandants/test-mandate/Subjects*' => Http::response([
                'value' => [
                    ['Id' => 1, 'FirstName' => 'Page1'],
                    ['Id' => 2, 'FirstName' => 'Page1'],
                ],
                '@odata.nextLink' => 'https://api.example.com/page2',
            ], 200),
            'https://api.example.com/page2' => Http::response([
                'value' => [
                    ['Id' => 3, 'FirstName' => 'Page2'],
                ],
            ], 200),
        ]);

        $processedPages = [];

        TestSubject::pages(10)
            ->cursorWithCallback(function ($items, $pageNumber) use (&$processedPages) {
                $processedPages[] = [
                    'page' => $pageNumber,
                    'count' => $items->count(),
                    'first_name' => $items->first()->FirstName,
                ];
            })
            ->get();

        $this->assertCount(2, $processedPages);
        $this->assertEquals(1, $processedPages[0]['page']);
        $this->assertEquals(2, $processedPages[0]['count']);
        $this->assertEquals('Page1', $processedPages[0]['first_name']);
        $this->assertEquals(2, $processedPages[1]['page']);
        $this->assertEquals(1, $processedPages[1]['count']);
        $this->assertEquals('Page2', $processedPages[1]['first_name']);
    }
}
