<?php

namespace Contoweb\AbacusApi\Tests\Fixtures;

/* Test response fixtures for consistent testing */
class ResponseFixtures
{
    public static function successfulTokenResponse(): array
    {
        return [
            'access_token' => 'test-access-token-12345',
            'token_type' => 'Bearer',
            'expires_in' => 3600,
            'scope' => 'api',
        ];
    }

    public static function entityListResponse(int $count = 3): array
    {
        $items = [];
        for ($i = 1; $i <= $count; $i++) {
            $items[] = [
                'Id' => $i,
                'Name' => "Entity {$i}",
                'Email' => "entity{$i}@example.com",
                'IsActive' => $i % 2 === 0,
                'CreatedAt' => '2024-01-'.str_pad($i, 2, '0', STR_PAD_LEFT),
            ];
        }

        return [
            '@odata.context' => 'https://api.example.com/$metadata#Subjects',
            'value' => $items,
        ];
    }

    public static function paginatedResponse(int $page = 1, bool $hasNextPage = true): array
    {
        $response = self::entityListResponse(10);

        if ($hasNextPage) {
            $response['@odata.nextLink'] = 'https://api.example.com/api/entities?skip='.($page * 10);
        }

        return $response;
    }

    public static function singleEntityResponse(int $id = 1): array
    {
        return [
            'Id' => $id,
            'Name' => "Test Entity {$id}",
            'Email' => "test{$id}@example.com",
            'Status' => 'Active',
            'CreatedAt' => '2024-01-15T10:30:00Z',
            'UpdatedAt' => '2024-01-20T14:45:00Z',
        ];
    }

    public static function createdEntityResponse(array $data): array
    {
        return array_merge([
            'Id' => rand(1000, 9999),
            'CreatedAt' => now()->toIso8601String(),
        ], $data);
    }

    public static function metadataResponse(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="utf-8"?>
<edmx:Edmx Version="4.0" xmlns:edmx="http://docs.oasis-open.org/odata/ns/edmx">
  <edmx:DataServices>
    <Schema Namespace="AbacusAPI" xmlns="http://docs.oasis-open.org/odata/ns/edm">
      <EntityType Name="Subject">
        <Key>
          <PropertyRef Name="Id"/>
        </Key>
        <Property Name="Id" Type="Edm.Int32" Nullable="false"/>
        <Property Name="Name" Type="Edm.String"/>
        <Property Name="Email" Type="Edm.String"/>
      </EntityType>
      <EntityContainer Name="Container">
        <EntitySet Name="Subjects" EntityType="AbacusAPI.Subject"/>
      </EntityContainer>
    </Schema>
  </edmx:DataServices>
</edmx:Edmx>
XML;
    }

    public static function reportJobSubmittedResponse(string $jobId = 'job-123'): array
    {
        return [
            'id' => $jobId,
            'state' => 'Running',
            'mandate' => 'test-mandate',
            'reportName' => 'test-report.avx',
            'submittedAt' => now()->toIso8601String(),
        ];
    }

    public static function reportJobRunningResponse(string $jobId = 'job-123'): array
    {
        return [
            'id' => $jobId,
            'state' => 'Running',
            'progress' => 50,
        ];
    }

    public static function reportJobFinishedResponse(string $jobId = 'job-123'): array
    {
        return [
            'id' => $jobId,
            'state' => 'FinishedSuccess',
            'progress' => 100,
            'completedAt' => now()->toIso8601String(),
        ];
    }

    public static function reportJobFailedResponse(string $jobId = 'job-123', string $error = 'Unknown error'): array
    {
        return [
            'id' => $jobId,
            'state' => 'Failed',
            'status' => 500,
            'title' => $error,
            'message' => "Report execution failed: {$error}",
        ];
    }

    public static function reportOutputResponse(int $count = 5): array
    {
        $data = [];
        for ($i = 1; $i <= $count; $i++) {
            $data[] = [
                'InvoiceId' => 'INV-'.str_pad($i, 4, '0', STR_PAD_LEFT),
                'CustomerName' => "Customer {$i}",
                'Amount' => $i * 100.50,
                'Date' => '2024-01-'.str_pad($i, 2, '0', STR_PAD_LEFT),
            ];
        }

        return $data;
    }

    public static function errorResponse(int $statusCode = 400, string $message = 'Bad Request'): array
    {
        return [
            'error' => [
                'code' => $statusCode,
                'message' => $message,
            ],
        ];
    }

    public static function unauthorizedResponse(): array
    {
        return [
            'error' => 'unauthorized',
            'error_description' => 'The access token is invalid or has expired',
        ];
    }
}
