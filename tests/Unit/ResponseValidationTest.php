<?php

declare(strict_types=1);

namespace Didox\Tests\Unit;

use Didox\Client\DidoxClient;
use Didox\DTO\DocumentDraft;
use Didox\DTO\TimestampResponse;
use Didox\DTO\DocumentResponse;
use Didox\DTO\SigningPayload;
use Didox\DTO\TemplateResponse;
use Didox\Exceptions\ResponseParseException;
use Didox\Http\HttpResponse;
use Didox\Tests\Fakes\FakeTransport;
use PHPUnit\Framework\TestCase;

final class ResponseValidationTest extends TestCase
{
    public function testDocumentDraftRequiresNonEmptyIdentifier(): void
    {
        $this->expectException(ResponseParseException::class);
        DocumentDraft::fromArray(['pending_document' => ['document_json' => []]]);
    }

    public function testDocumentDraftRejectsUnexpectedIdentifierType(): void
    {
        $this->expectException(ResponseParseException::class);
        DocumentDraft::fromArray(['_id' => 123]);
    }

    public function testDocumentDraftRejectsEmptyIdentifier(): void
    {
        $this->expectException(ResponseParseException::class);
        DocumentDraft::fromArray(['_id' => '   ', 'pending_document' => ['document_json' => []]]);
    }

    public function testDocumentDraftRejectsMissingDocumentJson(): void
    {
        $this->expectException(ResponseParseException::class);
        DocumentDraft::fromArray(['_id' => 'document-placeholder', 'pending_document' => []]);
    }

    public function testEmptyDocumentAndTemplateObjectsAreRejected(): void
    {
        try {
            DocumentResponse::fromArray([]);
            self::fail('Expected an invalid document response.');
        } catch (ResponseParseException) {
            // Expected.
        }

        $this->expectException(ResponseParseException::class);
        TemplateResponse::fromArray(['data' => []]);
    }

    public function testSigningResponseRequiresData(): void
    {
        $this->expectException(ResponseParseException::class);
        SigningPayload::fromArray([]);
    }

    public function testTimestampResponseRejectsStringBoolean(): void
    {
        $this->expectException(ResponseParseException::class);
        TimestampResponse::fromArray(['timeStampTokenB64' => 'timestamp', 'success' => 'false']);
    }

    public function testFalseDraftDeletionResultRemainsFalse(): void
    {
        $transport = new FakeTransport(new HttpResponse(200, [], '{"data":false}'));
        $client = new DidoxClient(['base_url' => 'https://example.test', 'partner_token' => 'partner-placeholder'], $transport);

        self::assertFalse($client->documents()->deleteDraft('document-placeholder'));
    }

    public function testStringDraftDeletionResultIsRejected(): void
    {
        $transport = new FakeTransport(new HttpResponse(200, [], '{"data":"false"}'));
        $client = new DidoxClient(['base_url' => 'https://example.test', 'partner_token' => 'partner-placeholder'], $transport);

        $this->expectException(ResponseParseException::class);
        $client->documents()->deleteDraft('document-placeholder');
    }

    public function testStringSigningResultIsRejected(): void
    {
        $transport = new FakeTransport(new HttpResponse(200, [], '{"data":"false"}'));
        $client = new DidoxClient(['base_url' => 'https://example.test', 'partner_token' => 'partner-placeholder'], $transport);

        $this->expectException(ResponseParseException::class);
        $client->documents()->sign('document-placeholder', 'signature-placeholder');
    }

    public function testSigningWarningDetailsMustBeAnObjectOrNull(): void
    {
        $transport = new FakeTransport(new HttpResponse(200, [], '{"data":true,"warningDetails":"unexpected"}'));
        $client = new DidoxClient(['base_url' => 'https://example.test', 'partner_token' => 'partner-placeholder'], $transport);

        $this->expectException(ResponseParseException::class);
        $client->documents()->sign('document-placeholder', 'signature-placeholder');
    }
}
