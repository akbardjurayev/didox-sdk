<?php

declare(strict_types=1);

namespace Didox\Tests\Unit;

use Didox\Client\DidoxClient;
use Didox\Exceptions\ApiException;
use Didox\Exceptions\AuthenticationException;
use Didox\Exceptions\AuthorizationException;
use Didox\Exceptions\NotFoundException;
use Didox\Exceptions\RateLimitException;
use Didox\Exceptions\ValidationException;
use Didox\Http\HttpResponse;
use Didox\Tests\Fakes\FakeTransport;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ExceptionMappingTest extends TestCase
{
    #[DataProvider('statusProvider')]
    public function testStatusCodesMapToUsefulExceptions(int $status, string $expected): void
    {
        $transport = new FakeTransport(new HttpResponse($status, [], '{"message":"fixture error"}'));
        $client = new DidoxClient(['base_url' => 'https://example.test', 'partner_token' => 'partner-placeholder'], $transport);

        try {
            $client->documents()->list(['page' => 1, 'limit' => 1]);
            self::fail('Expected an API exception.');
        } catch (ApiException $exception) {
            self::assertInstanceOf($expected, $exception);
            self::assertSame($status, $exception->statusCode());
        }
    }

    public static function statusProvider(): array
    {
        return [
            [401, AuthenticationException::class],
            [403, AuthorizationException::class],
            [404, NotFoundException::class],
            [422, ValidationException::class],
            [429, RateLimitException::class],
            [500, ApiException::class],
            [503, ApiException::class],
        ];
    }
}
