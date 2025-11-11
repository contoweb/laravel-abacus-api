<?php

namespace Contoweb\AbacusApi\Tests\Unit;

use Contoweb\AbacusApi\Tests\TestCase;
use Contoweb\AbacusApi\AbacusClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;

class AbacusClientTest extends TestCase
{
    protected AbacusClient $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = new AbacusClient();
    }

    #[Test]
    public function it_builds_entity_path(): void
    {
        $path = $this->client->entityPath('Subjects');

        $this->assertEquals('/api/entity/v1/mandants/test-mandate/Subjects', $path);
    }

    #[Test]
    public function it_builds_entity_path_with_id(): void
    {
        $path = $this->client->entityPathWithId('Subjects', 123);

        $this->assertEquals('/api/entity/v1/mandants/test-mandate/Subjects(123)', $path);
    }

    #[Test]
    public function it_builds_entity_path_with_string_id(): void
    {
        $path = $this->client->entityPathWithId('Users', 'abc-def');

        $this->assertEquals('/api/entity/v1/mandants/test-mandate/Users(abc-def)', $path);
    }

    #[Test]
    public function it_builds_entity_property_path(): void
    {
        $path = $this->client->entityPropertyPath('Subjects', 123, 'Name');

        $this->assertEquals('/api/entity/v1/mandants/test-mandate/Subjects(123)/Name', $path);
    }

    #[Test]
    public function it_builds_metadata_path(): void
    {
        $path = $this->client->metadataPath();

        $this->assertEquals('/api/entity/v1/mandants/test-mandate/$metadata', $path);
    }

    #[Test]
    public function it_builds_entities_list_path(): void
    {
        $path = $this->client->entitiesPath();

        $this->assertEquals('/api/entity/v1/mandants/test-mandate/', $path);
    }

    #[Test]
    public function it_follows_next_link_for_pagination(): void
    {
        Http::fake([
            '*/oauth/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            'https://api.example.com/api/entity/v1/mandants/test-mandate/Subjects?$skip=100' => Http::response([
                'value' => [
                    ['Id' => 101, 'Name' => 'Subject 101'],
                    ['Id' => 102, 'Name' => 'Subject 102'],
                ],
            ], 200),
        ]);

        $nextLinkUrl = 'https://api.example.com/api/entity/v1/mandants/test-mandate/Subjects?$skip=100';
        $response = $this->client->getNextLink($nextLinkUrl);

        $this->assertEquals(200, $response->status());
        $this->assertCount(2, $response->json('value'));

        Http::assertSent(function (Request $request) use ($nextLinkUrl) {
            return $request->url() === $nextLinkUrl &&
                   $request->hasHeader('Authorization');
        });
    }

    #[Test]
    public function it_refreshes_token_on_next_link_401(): void
    {
        Http::fake([
            '*/oauth/token' => Http::response([
                'access_token' => 'refreshed-token',
                'expires_in' => 3600,
            ], 200),
            'https://api.example.com/next-page*' => Http::sequence()
                ->push(['error' => 'Unauthorized'], 401)
                ->push(['value' => []], 200),
        ]);

        $response = $this->client->getNextLink('https://api.example.com/next-page');

        $this->assertEquals(200, $response->status());
    }

    #[Test]
    public function it_uses_correct_mandate_in_paths(): void
    {
        $customClient = new AbacusClient(
            'https://api.example.com',
            'custom-mandate-123'
        );

        $path = $customClient->entityPath('Invoices');

        $this->assertEquals('/api/entity/v1/mandants/custom-mandate-123/Invoices', $path);
    }

    #[Test]
    public function it_builds_paths_with_special_characters(): void
    {
        $path = $this->client->entityPath('Special/Resource');

        $this->assertEquals('/api/entity/v1/mandants/test-mandate/Special/Resource', $path);
    }

    #[Test]
    public function it_builds_path_with_numeric_id(): void
    {
        $path = $this->client->entityPathWithId('Orders', 99999);

        $this->assertEquals('/api/entity/v1/mandants/test-mandate/Orders(99999)', $path);
    }

    #[Test]
    public function it_builds_path_with_guid_id(): void
    {
        $guid = '550e8400-e29b-41d4-a716-446655440000';
        $path = $this->client->entityPathWithId('Documents', $guid);

        $this->assertEquals('/api/entity/v1/mandants/test-mandate/Documents(550e8400-e29b-41d4-a716-446655440000)', $path);
    }

    #[Test]
    public function it_builds_property_path_with_nested_property(): void
    {
        $path = $this->client->entityPropertyPath('Subjects', 42, 'Address/City');

        $this->assertEquals('/api/entity/v1/mandants/test-mandate/Subjects(42)/Address/City', $path);
    }
}
