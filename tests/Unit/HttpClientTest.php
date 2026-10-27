<?php

declare(strict_types=1);

namespace Didox\Tests\Unit;

use Didox\Client\DidoxClient;
use Didox\Http\HttpResponse;
use Didox\Tests\Fakes\FakeTransport;
use PHPUnit\Framework\TestCase;

final class HttpClientTest extends TestCase
{
    public function testSuccessfulGetUsesCentralTransportAndAuthHeaders(): void
    {
        $transport = new FakeTransport(new HttpResponse(200, ['Content-Type' => 'application/json'], '{"data":[]}'));
        $client = new DidoxClient([
            'base_url' => 'https://example.test',
            'partner_token' => 'partner-placeholder',
            'user_token' => 'user-placeholder',
        ], $transport);

        self::assertSame(['data' => []], $client->documents()->list(['page' => 1, 'limit' => 10]));
        self::assertSame('GET', $transport->lastRequest?->method);
        self::assertStringContainsString('/v2/documents?page=1&limit=10', $transport->lastRequest?->url);
        self::assertSame('partner-placeholder', $transport->lastRequest?->headers['Partner-Authorization']);
        self::assertSame('user-placeholder', $transport->lastRequest?->headers['user-key']);
    }

    public function testSuccessfulPostEncodesJsonBody(): void
    {
        $transport = new FakeTransport(new HttpResponse(200, [], '{"data":true}'));
        $client = new DidoxClient(['base_url' => 'https://example.test', 'partner_token' => 'partner-placeholder'], $transport);

        self::assertTrue($client->documents()->sign('abc', 'signature-placeholder')['data']);
        self::assertSame('POST', $transport->lastRequest?->method);
        self::assertSame('{"signature":"signature-placeholder"}', $transport->lastRequest?->body);
        self::assertSame('application/json', $transport->lastRequest?->headers['Content-Type']);
    }
}
