<?php

declare(strict_types=1);

namespace Didox\Tests\Unit;

use DateTimeImmutable;
use Didox\Client\DidoxClient;
use Didox\Exceptions\AuthenticationException;
use Didox\Exceptions\ApiException;
use Didox\Exceptions\ResponseParseException;
use Didox\Exceptions\SerializationException;
use Didox\Exceptions\TransportException;
use Didox\Contracts\TokenStoreInterface;
use Didox\Http\CurlHttpTransport;
use Didox\Http\HttpResponse;
use Didox\Support\DidoxConfig;
use Didox\Support\InMemoryTokenStore;
use Didox\Tests\Fakes\FakeTransport;
use Didox\Tests\Fakes\FakeCurlDriver;
use PHPUnit\Framework\TestCase;

final class HardeningTest extends TestCase
{
    public function testExpiredUserTokenIsRejectedBeforeARequestIsSent(): void
    {
        $transport = new FakeTransport(new HttpResponse(200, [], '{"data":[]}'));
        $store = new InMemoryTokenStore('expired-placeholder', new DateTimeImmutable('-1 minute'));
        $client = new DidoxClient(['base_url' => 'https://example.test', 'partner_token' => 'partner-placeholder'], $transport, $store);

        try {
            $client->documents()->list(['page' => 1, 'limit' => 1]);
            self::fail('Expected an authentication exception.');
        } catch (AuthenticationException $exception) {
            self::assertSame(401, $exception->statusCode());
            self::assertNull($transport->lastRequest);
        }
    }

    public function testExpiredCustomStoreTokenIsRejectedBeforeARequestIsSent(): void
    {
        $transport = new FakeTransport(new HttpResponse(200, [], '{"data":[]}'));
        $store = new class implements TokenStoreInterface {
            public function get(): ?string { return 'expired-custom-token'; }
            public function set(string $token, ?DateTimeImmutable $expiresAt = null): void { }
            public function expiresAt(): ?DateTimeImmutable { return new DateTimeImmutable('-1 minute'); }
            public function clear(): void { }
        };
        $client = new DidoxClient(['base_url' => 'https://example.test', 'partner_token' => 'partner-placeholder'], $transport, $store);

        try {
            $client->documents()->list(['page' => 1, 'limit' => 1]);
            self::fail('Expected an authentication exception.');
        } catch (AuthenticationException) {
            self::assertNull($transport->lastRequest);
        }
    }

    public function testInvalidSuccessfulJsonIsNotSilentlyConvertedToAnEmptyResult(): void
    {
        $transport = new FakeTransport(new HttpResponse(200, [], '<html>not-json</html>'));
        $client = new DidoxClient(['base_url' => 'https://example.test', 'partner_token' => 'partner-placeholder'], $transport);

        try {
            $client->documents()->list(['page' => 1, 'limit' => 1]);
            self::fail('Expected a response parse exception.');
        } catch (ResponseParseException $exception) {
            self::assertInstanceOf(\JsonException::class, $exception->getPrevious());
        }
    }

    public function testTransportFailureIsExposedAsTransportException(): void
    {
        $driver = new FakeCurlDriver(false, 'fixture transport failure');
        $client = new DidoxClient(
            ['base_url' => 'https://example.test', 'partner_token' => 'partner-placeholder', 'timeout' => 7],
            new CurlHttpTransport($driver),
        );

        try {
            $client->documents()->list(['page' => 1, 'limit' => 1]);
            self::fail('Expected a transport exception.');
        } catch (TransportException $exception) {
            self::assertSame('Didox HTTP transport failed: fixture transport failure', $exception->getMessage());
            self::assertSame(7, $driver->options[CURLOPT_TIMEOUT]);
        }
    }

    public function testSensitiveErrorBodyAndHeadersAreRedacted(): void
    {
        $transport = new FakeTransport(new HttpResponse(500, ['user-key' => 'secret-header', 'X-Trace' => 'safe'], '{"token":"secret-body","message":"Bearer secret-message"}'));
        $client = new DidoxClient(['base_url' => 'https://example.test', 'partner_token' => 'partner-placeholder'], $transport);

        try {
            $client->documents()->list(['page' => 1, 'limit' => 1]);
            self::fail('Expected API exception.');
        } catch (ApiException $exception) {
            self::assertStringNotContainsString('secret', $exception->getMessage());
            self::assertStringNotContainsString('secret', $exception->responseBody());
            self::assertSame('[REDACTED]', $exception->responseHeaders()['user-key']);
            self::assertSame('safe', $exception->responseHeaders()['X-Trace']);
        }
    }

    public function testNestedSensitiveFieldsAreRedactedWithoutBreakingValidJson(): void
    {
        $body = json_encode([
            'error' => [
                'PASSWORD' => 'quoted "password\\value',
                'details' => [
                    'SignatureHex' => 'signature-value',
                    'signature1' => 'signature-one-value',
                    'signature2' => 'signature-two-value',
                    'PKCS7' => 'pkcs7-value',
                    'safeCode' => 'E-422',
                ],
            ],
            'message' => 'password=message-secret',
        ], JSON_THROW_ON_ERROR);
        $transport = new FakeTransport(new HttpResponse(422, ['X-SignatureHex' => 'header-secret', 'X-Api-Key' => 'api-secret'], $body));
        $client = new DidoxClient(['base_url' => 'https://example.test', 'partner_token' => 'partner-placeholder'], $transport);

        try {
            $client->documents()->list(['page' => 1, 'limit' => 1]);
            self::fail('Expected API exception.');
        } catch (ApiException $exception) {
            $sanitized = json_decode($exception->responseBody(), true, 512, JSON_THROW_ON_ERROR);
            self::assertSame('[REDACTED]', $sanitized['error']['PASSWORD']);
            self::assertSame('[REDACTED]', $sanitized['error']['details']['SignatureHex']);
            self::assertSame('[REDACTED]', $sanitized['error']['details']['signature1']);
            self::assertSame('[REDACTED]', $sanitized['error']['details']['signature2']);
            self::assertSame('[REDACTED]', $sanitized['error']['details']['PKCS7']);
            self::assertSame('E-422', $sanitized['error']['details']['safeCode']);
            self::assertStringNotContainsString('message-secret', $exception->getMessage());
            self::assertSame('[REDACTED]', $exception->responseHeaders()['X-SignatureHex']);
            self::assertSame('[REDACTED]', $exception->responseHeaders()['X-Api-Key']);
        }
    }

    public function testMalformedErrorBodyUsesConservativeRedaction(): void
    {
        $transport = new FakeTransport(new HttpResponse(500, [], 'PASSWORD="abc\\"trailing-secret'));
        $client = new DidoxClient(['base_url' => 'https://example.test', 'partner_token' => 'partner-placeholder'], $transport);

        try {
            $client->documents()->list(['page' => 1, 'limit' => 1]);
            self::fail('Expected API exception.');
        } catch (ApiException $exception) {
            self::assertStringNotContainsString('trailing-secret', $exception->responseBody());
            self::assertStringContainsString('[REDACTED]', $exception->responseBody());
        }
    }

    public function testKnownSensitiveValueIsRemovedFromExceptionMessage(): void
    {
        $transport = new FakeTransport(new HttpResponse(422, [], '{"password":"known-secret","message":"failed for known-secret"}'));
        $client = new DidoxClient(['base_url' => 'https://example.test', 'partner_token' => 'partner-placeholder'], $transport);

        try {
            $client->documents()->list(['page' => 1, 'limit' => 1]);
            self::fail('Expected API exception.');
        } catch (ApiException $exception) {
            self::assertStringNotContainsString('known-secret', $exception->getMessage());
        }
    }

    public function testConfiguredAndRequestCredentialsAreRemovedFromDiagnostics(): void
    {
        $store = new InMemoryTokenStore('user-secret');
        $transport = new FakeTransport(new HttpResponse(
            500,
            ['X-Diagnostics' => 'user-secret'],
            '{"message":"failed for partner-secret and user-secret"}',
        ));
        $client = new DidoxClient([
            'base_url' => 'https://example.test',
            'partner_token' => 'partner-secret',
        ], $transport, $store);

        try {
            $client->documents()->list(['page' => 1, 'limit' => 1]);
            self::fail('Expected an API exception.');
        } catch (ApiException $exception) {
            self::assertStringNotContainsString('partner-secret', $exception->getMessage());
            self::assertStringNotContainsString('user-secret', $exception->getMessage());
            self::assertStringNotContainsString('partner-secret', $exception->responseBody());
            self::assertStringNotContainsString('user-secret', $exception->responseBody());
            self::assertSame('[REDACTED]', $exception->responseHeaders()['X-Diagnostics']);
        }
    }

    public function testInvalidRequestUtf8UsesSdkSerializationException(): void
    {
        $client = new DidoxClient(['base_url' => 'https://example.test', 'partner_token' => 'partner-placeholder'], new FakeTransport());

        try {
            $client->documents()->sign('document-placeholder', "\xB1\x31");
            self::fail('Expected serialization exception.');
        } catch (SerializationException $exception) {
            self::assertInstanceOf(\JsonException::class, $exception->getPrevious());
            self::assertStringNotContainsString('document-placeholder', $exception->getMessage());
        }
    }

    public function testConfigRejectsNonHttpsUrls(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new DidoxConfig('http://example.test', 'partner-placeholder');
    }

    public function testConfigRejectsMissingPartnerCredentials(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new DidoxConfig('https://example.test', '');
    }

    public function testDocumentListRequiresDocumentedPaginationInputs(): void
    {
        $client = new DidoxClient(['base_url' => 'https://example.test', 'partner_token' => 'partner-placeholder'], new FakeTransport());

        $this->expectException(\InvalidArgumentException::class);
        $client->documents()->list();
    }
}
