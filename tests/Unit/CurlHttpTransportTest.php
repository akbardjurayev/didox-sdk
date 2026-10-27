<?php

declare(strict_types=1);

namespace Didox\Tests\Unit;

use Didox\Exceptions\TransportException;
use Didox\Http\CurlHttpTransport;
use Didox\Http\HttpRequest;
use Didox\Tests\Fakes\FakeCurlDriver;
use PHPUnit\Framework\TestCase;

final class CurlHttpTransportTest extends TestCase
{
    public function testCurlFailureIsNormalizedAndTlsVerificationIsEnabled(): void
    {
        $driver = new FakeCurlDriver(false, 'controlled curl failure');
        $transport = new CurlHttpTransport($driver);

        try {
            $transport->send(new HttpRequest('GET', 'https://example.test', [], ''), 9);
            self::fail('Expected a transport exception.');
        } catch (TransportException $exception) {
            self::assertSame('Didox HTTP transport failed: controlled curl failure', $exception->getMessage());
        }

        self::assertTrue($driver->options[CURLOPT_SSL_VERIFYPEER]);
        self::assertSame(2, $driver->options[CURLOPT_SSL_VERIFYHOST]);
        self::assertTrue($driver->closed);
    }
}
