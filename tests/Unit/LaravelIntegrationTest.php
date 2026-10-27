<?php

declare(strict_types=1);

namespace Didox\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Didox\Contracts\DidoxClientInterface;
use Didox\Contracts\HttpTransportInterface;
use Didox\Contracts\TokenStoreInterface;
use Didox\Http\HttpResponse;
use Didox\Support\InMemoryTokenStore;
use Didox\Tests\Fakes\FakeTransport;

final class LaravelIntegrationTest extends TestCase
{
    public function testLaravelIntegrationIsOptional(): void
    {
        $application = $this->application();
        $provider = new \Didox\Laravel\DidoxServiceProvider($application);
        $provider->register();

        self::assertTrue(class_exists('Didox\\Laravel\\DidoxServiceProvider'));
        self::assertInstanceOf('Didox\\Client\\DidoxClient', $application->make('Didox\\Client\\DidoxClient'));
        self::assertInstanceOf(DidoxClientInterface::class, $application->make(DidoxClientInterface::class));
    }

    public function testLaravelClientResolutionsHaveIndependentDefaultStores(): void
    {
        $application = $this->application();
        (new \Didox\Laravel\DidoxServiceProvider($application))->register();

        $transport = new FakeTransport(
            new HttpResponse(200, [], '{"token":"client-a-token"}'),
            new HttpResponse(200, [], '{"data":[]}'),
        );
        $application->singleton(HttpTransportInterface::class, static fn () => $transport);
        $clientA = $application->make('Didox\\Client\\DidoxClient');
        $clientB = $application->make('Didox\\Client\\DidoxClient');

        $clientA->auth()->loginWithPassword('000000000', 'password-placeholder');
        $clientB->documents()->list(['page' => 1, 'limit' => 1]);

        self::assertNotSame($clientA, $clientB);
        self::assertArrayNotHasKey('user-key', $transport->lastRequest?->headers ?? []);
    }

    public function testLaravelCustomTransportAndTokenStoreAreResolved(): void
    {
        $application = $this->application();
        $transport = new FakeTransport(
            new HttpResponse(200, [], '{"token":"custom-token"}'),
            new HttpResponse(200, [], '{"data":[]}'),
        );
        $store = new InMemoryTokenStore();
        $application->singleton(HttpTransportInterface::class, static fn () => $transport);
        $application->singleton(TokenStoreInterface::class, static fn () => $store);
        (new \Didox\Laravel\DidoxServiceProvider($application))->register();

        $client = $application->make('Didox\\Client\\DidoxClient');
        $client->auth()->loginWithPassword('000000000', 'password-placeholder');
        $client->documents()->list(['page' => 1, 'limit' => 1]);

        self::assertSame('custom-token', $store->get());
        self::assertSame('custom-token', $transport->lastRequest?->headers['user-key'] ?? null);
    }

    public function testLaravelInterfaceBindingUsesScopedLifecycleWhenAvailable(): void
    {
        $application = $this->application();
        (new \Didox\Laravel\DidoxServiceProvider($application))->register();

        $first = $application->make(DidoxClientInterface::class);
        $second = $application->make(DidoxClientInterface::class);

        if (method_exists($application, 'scoped')) {
            self::assertSame($first, $second);
        } else {
            self::assertNotSame($first, $second);
        }
    }

    private function application(): object
    {
        $autoload = getenv('DIDOX_LARAVEL_AUTOLOAD');
        if (!is_string($autoload) || $autoload === '' || !is_file($autoload)) {
            self::markTestSkipped('Set DIDOX_LARAVEL_AUTOLOAD to a Laravel vendor/autoload.php to run the optional integration test.');
        }

        require_once $autoload;
        if (!class_exists('Illuminate\\Foundation\\Application')) {
            self::markTestSkipped('The supplied autoloader does not provide Laravel.');
        }

        $application = new \Illuminate\Foundation\Application(sys_get_temp_dir() . '/didox-laravel-sdk-test');
        $application->instance('config', new \Illuminate\Config\Repository([
            'didox' => [
                'base_url' => 'https://example.test',
                'partner_token' => 'partner-placeholder',
            ],
        ]));
        return $application;
    }
}
