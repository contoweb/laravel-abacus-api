<?php

namespace Contoweb\AbacusApi\Tests\Unit;

use Contoweb\AbacusApi\AbacusODataClient;
use Contoweb\AbacusApi\Exceptions\AbacusForbiddenException;
use Contoweb\AbacusApi\Tests\TestCase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;

class AbacusODataClientTest extends TestCase
{
    protected AbacusODataClient $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = new AbacusODataClient($this->makeCredentialsProvider());
    }

    #[Test]
    public function it_builds_entity_path(): void
    {
        $path = $this->client->entityPath('Subjects');

        $this->assertEquals('/api/entity/v1/mandants/1212/Subjects', $path);
    }

    #[Test]
    public function it_builds_metadata_path(): void
    {
        $path = $this->client->metadataPath();

        $this->assertEquals('/api/entity/v1/mandants/1212/$metadata', $path);
    }

    #[Test]
    public function it_builds_entities_list_path(): void
    {
        $path = $this->client->entitiesPath();

        $this->assertEquals('/api/entity/v1/mandants/1212/', $path);
    }

    #[Test]
    public function it_follows_next_link_for_pagination(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            'https://api.example.com/api/entity/v1/mandants/1212/Subjects?$skip=100' => Http::response([
                'value' => [
                    ['Id' => 101, 'Name' => 'Subject 101'],
                    ['Id' => 102, 'Name' => 'Subject 102'],
                ],
            ], 200),
        ]);

        $nextLinkUrl = 'https://api.example.com/api/entity/v1/mandants/1212/Subjects?$skip=100';
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
            '*/oauth/oauth2/v1/token' => Http::response([
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
        $customClient = new AbacusODataClient($this->makeCustomCredentialsProvider(
            mandate: 'custom-mandate-123',
        ));

        $path = $customClient->entityPath('Invoices');

        $this->assertEquals('/api/entity/v1/mandants/custom-mandate-123/Invoices', $path);
    }

    #[Test]
    public function it_builds_paths_with_special_characters(): void
    {
        $path = $this->client->entityPath('Special/Resource');

        $this->assertEquals('/api/entity/v1/mandants/1212/Special/Resource', $path);
    }

    #[Test]
    public function it_uploads_raw_content_to_the_file_store(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            '*/api/file-store/v1/user' => Http::response('', 201, [
                'Location' => 'https://api.example.com/api/file-store/v1/user/abc-123',
            ]),
        ]);

        $response = $this->client->fileUpload('raw-file-content');

        $this->assertEquals(201, $response->status());
        $this->assertEquals('https://api.example.com/api/file-store/v1/user/abc-123', $response->header('Location'));

        Http::assertSent(function (Request $request) {
            return $request->method() === 'POST'
                && $request->url() === 'https://api.example.com/api/file-store/v1/user'
                && $request->body() === 'raw-file-content'
                && $request->hasHeader('Content-Type', 'application/octet-stream')
                && $request->hasHeader('Authorization', 'Bearer test-token');
        });
    }

    #[Test]
    public function it_uploads_with_custom_content_type(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            '*/api/file-store/v1/user' => Http::response('', 201),
        ]);

        $this->client->fileUpload('%PDF-1.4', 'application/pdf');

        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://api.example.com/api/file-store/v1/user'
                && $request->hasHeader('Content-Type', 'application/pdf');
        });
    }

    #[Test]
    public function it_refreshes_token_on_file_upload_401(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token' => Http::sequence()
                ->push(['access_token' => 'initial-token', 'expires_in' => 3600], 200)
                ->push(['access_token' => 'refreshed-token', 'expires_in' => 3600], 200),
            '*/api/file-store/v1/user' => Http::sequence()
                ->push(['error' => 'Unauthorized'], 401)
                ->push('', 201),
        ]);

        $response = $this->client->fileUpload('raw-file-content');

        $this->assertEquals(201, $response->status());
        Http::assertSentCount(4);
    }

    #[Test]
    public function it_maps_forbidden_file_upload_to_exception(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            '*/api/file-store/v1/user' => Http::response(['error' => 'Forbidden'], 403),
        ]);

        $this->expectException(AbacusForbiddenException::class);

        $this->client->fileUpload('raw-file-content');
    }
}
