<?php

declare(strict_types=1);

namespace Didox\Tests\Unit;

use Didox\Exceptions\AuthenticationException;
use Didox\Exceptions\ResponseParseException;
use Didox\Http\HttpResponse;
use Didox\Client\DidoxClient;
use Didox\Support\InMemoryTokenStore;
use Didox\Tests\Fakes\FakeTransport;
use PHPUnit\Framework\TestCase;

final class AuthServiceTest extends TestCase
{
    public function testPasswordLoginStoresTheReturnedUserToken(): void
    {
        $transport = new FakeTransport(new HttpResponse(200, [], '{"token":"user-placeholder","taxId":"000000000"}'));
        $client = new DidoxClient(['base_url' => 'https://example.test', 'partner_token' => 'partner-placeholder'], $transport);

        $result = $client->auth()->loginWithPassword('000000000', 'password-placeholder');

        self::assertSame('user-placeholder', $result->token);
        self::assertArrayNotHasKey('user-key', $transport->lastRequest?->headers ?? []);
        self::assertSame('/v1/auth/000000000/password/ru', parse_url($transport->lastRequest?->url, PHP_URL_PATH));
        self::assertSame('{"password":"password-placeholder"}', $transport->lastRequest?->body);
    }

    public function testAuthenticationFailureIsNormalized(): void
    {
        $transport = new FakeTransport(new HttpResponse(401, [], '{"message":"Unauthorized","token":"secret"}'));
        $client = new DidoxClient(['base_url' => 'https://example.test', 'partner_token' => 'partner-placeholder'], $transport);

        try {
            $client->auth()->loginWithPassword('000000000', 'password-placeholder');
            self::fail('Expected an authentication exception.');
        } catch (AuthenticationException $exception) {
            self::assertSame(401, $exception->statusCode());
            self::assertStringNotContainsString('secret', $exception->responseBody());
        }
    }

    public function testTimestampRequestUsesPartnerAuthenticationOnly(): void
    {
        $transport = new FakeTransport(new HttpResponse(200, [], '{"timeStampTokenB64":"signature-placeholder","success":true}'));
        $client = new DidoxClient(['base_url' => 'https://example.test', 'partner_token' => 'partner-placeholder', 'user_token' => 'user-placeholder'], $transport);

        self::assertTrue($client->auth()->timestamp('pkcs7-placeholder', 'hex-placeholder')->success);
        self::assertArrayNotHasKey('user-key', $transport->lastRequest?->headers ?? []);
        self::assertSame('{"pkcs7":"pkcs7-placeholder","signatureHex":"hex-placeholder"}', $transport->lastRequest?->body);
    }

    public function testAuthenticationStoresTheDocumentedLocalExpiry(): void
    {
        $transport = new FakeTransport(new HttpResponse(200, [], '{"token":"user-placeholder"}'));
        $store = new InMemoryTokenStore();
        $client = new DidoxClient(['base_url' => 'https://example.test', 'partner_token' => 'partner-placeholder'], $transport, $store);

        $before = new \DateTimeImmutable('+359 minutes');
        $client->auth()->loginWithPassword('000000000', 'password-placeholder');
        $after = new \DateTimeImmutable('+361 minutes');

        self::assertNotNull($store->expiresAt());
        self::assertGreaterThan($before, $store->expiresAt());
        self::assertLessThan($after, $store->expiresAt());
    }

    public function testAuthenticationResponseWithoutTokenIsRejected(): void
    {
        $transport = new FakeTransport(new HttpResponse(200, [], '{"success":true}'));
        $client = new DidoxClient(['base_url' => 'https://example.test', 'partner_token' => 'partner-placeholder'], $transport);

        $this->expectException(ResponseParseException::class);
        $client->auth()->loginWithPassword('000000000', 'password-placeholder');
    }
}
