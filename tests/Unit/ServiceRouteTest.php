<?php

declare(strict_types=1);

namespace Didox\Tests\Unit;

use Didox\Client\DidoxClient;
use Didox\Http\HttpResponse;
use Didox\Support\DocumentAction;
use Didox\Tests\Fakes\FakeTransport;
use PHPUnit\Framework\TestCase;

final class ServiceRouteTest extends TestCase
{
    public function testSigningPayloadUsesDocumentActionAndComment(): void
    {
        $transport = new FakeTransport(new HttpResponse(200, [], '{"data":{"DocumentId":"fixture"}}'));
        $client = new DidoxClient(['base_url' => 'https://example.test', 'partner_token' => 'partner-placeholder', 'user_token' => 'user-placeholder'], $transport);

        $payload = $client->documents()->toSign('document-placeholder', DocumentAction::REJECT, 'fixture comment');

        self::assertSame(['DocumentId' => 'fixture'], $payload->data);
        self::assertSame('{"action":"reject","comment":"fixture comment"}', $transport->lastRequest?->body);
    }

    public function testProfileCatalogAndTemplateServicesUseDocumentedRoutes(): void
    {
        $transport = new FakeTransport(
            new HttpResponse(200, [], '{"tin":"000000000"}'),
            new HttpResponse(200, [], '[{"bankId":"00000"}]'),
            new HttpResponse(200, [], '{"data":{"doctype":"007"}}'),
        );
        $client = new DidoxClient(['base_url' => 'https://example.test', 'partner_token' => 'partner-placeholder', 'user_token' => 'user-placeholder'], $transport);

        self::assertSame('000000000', $client->profile()->current()['tin']);
        self::assertSame('00000', $client->catalogs()->banks()[0]['bankId']);
        self::assertSame('007', $client->templates()->find(1)->data['doctype']);
        self::assertSame('/v1/document-template/1', parse_url($transport->lastRequest?->url, PHP_URL_PATH));
    }

    public function testPrintFormCanUsePartnerOnlyAuthorization(): void
    {
        $transport = new FakeTransport(new HttpResponse(200, ['Content-Type' => 'text/html'], '<html>fixture</html>'));
        $client = new DidoxClient(['base_url' => 'https://example.test', 'partner_token' => 'partner-placeholder', 'user_token' => 'user-placeholder'], $transport);

        self::assertSame('<html>fixture</html>', $client->documents()->html('document-placeholder', 'ru', false));
        self::assertArrayNotHasKey('user-key', $transport->lastRequest?->headers ?? []);
        self::assertSame('/v1/documents/document-placeholder/html/ru', parse_url($transport->lastRequest?->url, PHP_URL_PATH));
    }
}
