<?php

declare(strict_types=1);

namespace Didox\Tests\Unit;

use Didox\Client\DidoxClient;
use Didox\Http\HttpResponse;
use Didox\Tests\Fakes\FakeTransport;
use PHPUnit\Framework\TestCase;

final class DocumentServiceTest extends TestCase
{
    public function testDocumentDetailsAreMappedToAStableDto(): void
    {
        $transport = new FakeTransport(new HttpResponse(200, [], '{"data":{"json":{"version":1},"document":{"status":3}}}'));
        $client = new DidoxClient(['base_url' => 'https://example.test', 'partner_token' => 'partner-placeholder', 'user_token' => 'user-placeholder'], $transport);

        $document = $client->documents()->find('document-placeholder');

        self::assertSame(['version' => 1], $document->data['json']);
        self::assertSame(3, $document->data['document']['status']);
    }

    public function testDraftCreationMapsDocumentIdAndPayload(): void
    {
        $transport = new FakeTransport(new HttpResponse(200, [], '{"_id":"document-placeholder","created_date":"2026-01-01 00:00:00","pending_document":{"document_json":{"facturaid":"fixture"}}}'));
        $client = new DidoxClient(['base_url' => 'https://example.test', 'partner_token' => 'partner-placeholder', 'user_token' => 'user-placeholder'], $transport);

        $draft = $client->documents()->create('002', ['fixture' => true]);

        self::assertSame('document-placeholder', $draft->id);
        self::assertSame(['facturaid' => 'fixture'], $draft->documentJson);
    }

    public function testSigningUsesTheDocumentSignatureEndpoint(): void
    {
        $transport = new FakeTransport(new HttpResponse(200, [], '{"data":true,"warningDetails":null}'));
        $client = new DidoxClient(['base_url' => 'https://example.test', 'partner_token' => 'partner-placeholder', 'user_token' => 'user-placeholder'], $transport);

        self::assertTrue($client->documents()->sign('document-placeholder', 'signature-placeholder')['data']);
        self::assertStringContainsString('/v1/documents/document-placeholder/sign', $transport->lastRequest?->url);
    }
}
