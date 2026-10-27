<?php

declare(strict_types=1);

namespace Didox\Tests\Unit;

use Didox\Client\DidoxClient;
use Didox\Http\HttpResponse;
use Didox\Tests\Fakes\FakeTransport;
use PHPUnit\Framework\TestCase;

final class LocaleTest extends TestCase
{
    public function testConfiguredUzLocalePropagatesToHeadersAndAuthPaths(): void
    {
        $transport = new FakeTransport(
            new HttpResponse(200, [], '[]'),
            new HttpResponse(200, [], '{"token":"user-placeholder"}'),
        );
        $client = new DidoxClient([
            'base_url' => 'https://example.test',
            'partner_token' => 'partner-placeholder',
            'locale' => 'uz',
        ], $transport);

        $client->catalogs()->banks();
        self::assertSame('uz', $transport->lastRequest?->headers['Accept-Language']);
        $client->auth()->loginWithPassword('000000000', 'password-placeholder');
        self::assertSame('/v1/auth/000000000/password/uz', parse_url($transport->lastRequest?->url, PHP_URL_PATH));
        self::assertSame('uz', $transport->lastRequest?->headers['Accept-Language']);
    }

    public function testExplicitLocaleOverridesConfiguredLocale(): void
    {
        $transport = new FakeTransport(
            new HttpResponse(200, [], '[]'),
            new HttpResponse(200, [], '{"token":"user-placeholder"}'),
        );
        $client = new DidoxClient([
            'base_url' => 'https://example.test',
            'partner_token' => 'partner-placeholder',
            'locale' => 'uz',
        ], $transport);

        $client->catalogs()->banks('ru');
        self::assertSame('ru', $transport->lastRequest?->headers['Accept-Language']);
        $client->auth()->loginWithPassword('000000000', 'password-placeholder', 'ru');
        self::assertSame('/v1/auth/000000000/password/ru', parse_url($transport->lastRequest?->url, PHP_URL_PATH));
        self::assertSame('ru', $transport->lastRequest?->headers['Accept-Language']);
    }

    public function testInvalidPerCallLocaleIsRejectedBeforeRequest(): void
    {
        $transport = new FakeTransport(new HttpResponse(200, [], '[]'));
        $client = new DidoxClient(['base_url' => 'https://example.test', 'partner_token' => 'partner-placeholder'], $transport);

        $this->expectException(\InvalidArgumentException::class);
        $client->catalogs()->banks('en');
    }

    public function testConfiguredLocalePropagatesAcrossLocalizedDocumentAndProfileRoutes(): void
    {
        $transport = new FakeTransport(
            new HttpResponse(200, [], '{"_id":"document-placeholder","pending_document":{"document_json":[]}}'),
            new HttpResponse(200, [], '[]'),
        );
        $client = new DidoxClient([
            'base_url' => 'https://example.test',
            'partner_token' => 'partner-placeholder',
            'locale' => 'uz',
        ], $transport);

        $client->documents()->create('002', []);
        self::assertSame('/v1/documents/002/create/uz', parse_url($transport->lastRequest?->url, PHP_URL_PATH));
        self::assertSame('uz', $transport->lastRequest?->headers['Accept-Language']);

        $client->profile()->productPackages('000000000', 'fixture');
        self::assertSame('/v1/profile/000000000/productClasses/check/fixture/uz', parse_url($transport->lastRequest?->url, PHP_URL_PATH));
        self::assertSame('uz', $transport->lastRequest?->headers['Accept-Language']);
    }
}
