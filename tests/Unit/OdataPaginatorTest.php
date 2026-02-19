<?php

namespace Contoweb\AbacusApi\Tests\Unit;

use Contoweb\AbacusApi\AbacusODataClient;
use Contoweb\AbacusApi\OdataPaginator;
use Contoweb\AbacusApi\Tests\Fixtures\TestSubject;
use Contoweb\AbacusApi\Tests\TestCase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;

class OdataPaginatorTest extends TestCase
{
    protected AbacusODataClient $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = new AbacusODataClient;

        Http::fake([
            '*/oauth/oauth2/v1/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
        ]);
    }

    #[Test]
    public function it_hydrates_items_on_construction(): void
    {
        $items = [
            ['Id' => 1, 'Name' => 'First'],
            ['Id' => 2, 'Name' => 'Second'],
        ];

        $paginator = new OdataPaginator($items, $this->client, TestSubject::class);

        $collection = $paginator->items();
        $this->assertCount(2, $collection);
        $this->assertInstanceOf(TestSubject::class, $collection->first());
        $this->assertInstanceOf(TestSubject::class, $collection->last());
        $this->assertEquals(1, $collection->first()->Id);
        $this->assertEquals(2, $collection->last()->Id);
    }

    #[Test]
    public function it_get_items_returns_collection(): void
    {
        $items = [
            ['Id' => 1, 'Name' => 'Test'],
        ];

        $paginator = new OdataPaginator($items, $this->client, TestSubject::class);
        $collection = $paginator->items();

        $this->assertCount(1, $collection);
        $this->assertEquals('Test', $collection->first()->Name);
    }

    #[Test]
    public function it_has_more_pages_returns_true_when_next_link_present(): void
    {
        $items = [['Id' => 1, 'Name' => 'First']];
        $nextLink = 'https://example.com/Subjects?$skiptoken=abc123';

        $paginator = new OdataPaginator($items, $this->client, TestSubject::class, $nextLink);

        $this->assertTrue($paginator->hasMorePages());
    }

    #[Test]
    public function it_has_more_pages_returns_false_when_no_next_link(): void
    {
        $items = [['Id' => 1, 'Name' => 'First']];

        $paginator = new OdataPaginator($items, $this->client, TestSubject::class, null);

        $this->assertFalse($paginator->hasMorePages());
    }

    #[Test]
    public function it_get_next_page_appends_items_to_collection(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            'https://example.com/Subjects*' => Http::response([
                'value' => [
                    ['Id' => 3, 'Name' => 'Third'],
                    ['Id' => 4, 'Name' => 'Fourth'],
                ],
            ], 200),
        ]);

        $items = [['Id' => 1, 'Name' => 'First']];
        $nextLink = 'https://example.com/Subjects?$skiptoken=abc123';

        $paginator = new OdataPaginator($items, $this->client, TestSubject::class, $nextLink);

        $this->assertCount(1, $paginator->items());
        $paginator->getNextPage();
        $this->assertCount(3, $paginator->items());

        $this->assertEquals(1, $paginator->items()[0]->Id);
        $this->assertEquals(3, $paginator->items()[1]->Id);
        $this->assertEquals(4, $paginator->items()[2]->Id);
    }

    #[Test]
    public function it_get_next_page_updates_next_link(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            'https://example.com/Subjects*' => Http::response([
                'value' => [
                    ['Id' => 2, 'Name' => 'Second'],
                ],
                '@odata.nextLink' => 'https://example.com/Subjects?$skiptoken=def456',
            ], 200),
        ]);

        $items = [['Id' => 1, 'Name' => 'First']];
        $nextLink = 'https://example.com/Subjects?$skiptoken=abc123';

        $paginator = new OdataPaginator($items, $this->client, TestSubject::class, $nextLink);

        $this->assertTrue($paginator->hasMorePages());
        $paginator->getNextPage();
        $this->assertTrue($paginator->hasMorePages());
    }

    #[Test]
    public function it_get_next_page_sets_next_link_to_null_when_no_more_pages(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            'https://example.com/Subjects*' => Http::response([
                'value' => [
                    ['Id' => 2, 'Name' => 'Second'],
                ],
            ], 200),
        ]);

        $items = [['Id' => 1, 'Name' => 'First']];
        $nextLink = 'https://example.com/Subjects?$skiptoken=abc123';

        $paginator = new OdataPaginator($items, $this->client, TestSubject::class, $nextLink);

        $this->assertTrue($paginator->hasMorePages());
        $paginator->getNextPage();
        $this->assertFalse($paginator->hasMorePages());
    }

    #[Test]
    public function it_supports_multi_page_iteration(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            'https://example.com/Subjects*' => Http::sequence([
                Http::response([
                    'value' => [
                        ['Id' => 2, 'Name' => 'Second'],
                    ],
                    '@odata.nextLink' => 'https://example.com/Subjects?$skiptoken=def456',
                ], 200),
                Http::response([
                    'value' => [
                        ['Id' => 3, 'Name' => 'Third'],
                    ],
                ], 200),
            ]),
        ]);

        $items = [['Id' => 1, 'Name' => 'First']];
        $nextLink = 'https://example.com/Subjects?$skiptoken=abc123';

        $paginator = new OdataPaginator($items, $this->client, TestSubject::class, $nextLink);

        $this->assertCount(1, $paginator->items());
        $this->assertTrue($paginator->hasMorePages());

        // First getNextPage() call
        $paginator->getNextPage();
        $this->assertCount(2, $paginator->items());
        $this->assertTrue($paginator->hasMorePages());

        // Second getNextPage() call
        $paginator->getNextPage();
        $this->assertCount(3, $paginator->items());
        $this->assertFalse($paginator->hasMorePages());

        // Verify all items are present and in order
        $this->assertEquals(1, $paginator->items()[0]->Id);
        $this->assertEquals(2, $paginator->items()[1]->Id);
        $this->assertEquals(3, $paginator->items()[2]->Id);
    }
}
