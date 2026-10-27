<?php

declare(strict_types=1);

namespace Didox\Laravel;

use Didox\Client\DidoxClient;
use Didox\Client\DidoxClientFactory;
use Didox\Contracts\DidoxClientInterface;
use Didox\Contracts\HttpTransportInterface;
use Didox\Contracts\TokenStoreInterface;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

final class DidoxServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/didox.php', 'didox');
        $this->app->singleton(DidoxClientFactory::class, static function (Application $app): DidoxClientFactory {
            return new DidoxClientFactory($app['config']->get('didox', []));
        });
        // Scoped clients are flushed by Laravel between requests/jobs. On
        // older Laravel versions, bind transient clients and retain a
        // factory-created client in the request/job when state must persist.
        // A custom TokenStoreInterface must have the same identity scope.
        $clientBinder = method_exists($this->app, 'scoped') ? 'scoped' : 'bind';
        $this->app->{$clientBinder}(DidoxClient::class, static function (Application $app): DidoxClient {
            $transport = $app->bound(HttpTransportInterface::class)
                ? $app->make(HttpTransportInterface::class)
                : null;
            $tokenStore = $app->bound(TokenStoreInterface::class)
                ? $app->make(TokenStoreInterface::class)
                : null;

            return $app->make(DidoxClientFactory::class)->make($transport, $tokenStore);
        });
        $this->app->{$clientBinder}(DidoxClientInterface::class, static fn (Application $app): DidoxClientInterface => $app->make(DidoxClient::class));
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../../config/didox.php' => config_path('didox.php'),
        ], 'didox-config');
    }
}
