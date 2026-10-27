<?php

declare(strict_types=1);

namespace Didox\Tests\Unit;

use Didox\Client\DidoxClientFactory;
use Didox\Http\HttpResponse;
use Didox\Support\InMemoryTokenStore;
use Didox\Tests\Fakes\FakeTransport;
use PHPUnit\Framework\TestCase;

final class ClientFactoryTest extends TestCase
{
    public function testFactoryKeepsIdentityStoresIndependent(): void
    {
        $factory = new DidoxClientFactory([
            'base_url' => 'https://example.test',
            'partner_token' => 'partner-placeholder',
        ]);
        $storeA = new InMemoryTokenStore();
        $storeB = new InMemoryTokenStore('client-b-token');
        $transportA = new FakeTransport(new HttpResponse(200, [], '{"token":"client-a-token"}'));
        $transportB = new FakeTransport(new HttpResponse(200, [], '{"data":[]}'));
        $clientA = $factory->make($transportA, $storeA);
        $clientB = $factory->make($transportB, $storeB);

        $clientA->auth()->loginWithPassword('000000000', 'password-placeholder');
        self::assertSame('client-a-token', $storeA->get());
        $clientB->documents()->list(['page' => 1, 'limit' => 1]);
        $clientA->auth()->logout();

        self::assertNull($storeA->get());
        self::assertSame('client-b-token', $storeB->get());
        self::assertArrayNotHasKey('user-key', $transportA->lastRequest?->headers ?? []);
        self::assertSame('client-b-token', $transportB->lastRequest?->headers['user-key'] ?? null);
    }

    public function testTwoIdentitiesAuthenticateIndependently(): void
    {
        $factory = new DidoxClientFactory([
            'base_url' => 'https://example.test',
            'partner_token' => 'partner-placeholder',
        ]);
        $storeA = new InMemoryTokenStore();
        $storeB = new InMemoryTokenStore();
        $transportA = new FakeTransport(
            new HttpResponse(200, [], '{"token":"client-a-token"}'),
            new HttpResponse(200, [], '{"data":[]}'),
        );
        $transportB = new FakeTransport(
            new HttpResponse(200, [], '{"token":"client-b-token"}'),
            new HttpResponse(200, [], '{"data":[]}'),
            new HttpResponse(200, [], '{"data":[]}'),
        );
        $clientA = $factory->make($transportA, $storeA);
        $clientB = $factory->make($transportB, $storeB);

        $clientA->auth()->loginWithPassword('000000001', 'password-a-placeholder');
        $clientB->auth()->loginWithPassword('000000002', 'password-b-placeholder');
        self::assertSame('client-a-token', $storeA->get());
        self::assertSame('client-b-token', $storeB->get());

        $clientA->documents()->list(['page' => 1, 'limit' => 1]);
        $clientB->documents()->list(['page' => 1, 'limit' => 1]);
        self::assertSame('client-a-token', $transportA->lastRequest?->headers['user-key'] ?? null);
        self::assertSame('client-b-token', $transportB->lastRequest?->headers['user-key'] ?? null);

        $clientA->auth()->logout();
        $clientB->documents()->list(['page' => 1, 'limit' => 1]);
        self::assertNull($storeA->get());
        self::assertSame('client-b-token', $storeB->get());
        self::assertSame('client-b-token', $transportB->lastRequest?->headers['user-key'] ?? null);
    }
}
