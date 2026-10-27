<?php

declare(strict_types=1);

namespace Didox\Client;

use Didox\Contracts\HttpTransportInterface;
use Didox\Contracts\TokenStoreInterface;
use Didox\Support\DidoxConfig;

/**
 * Creates independent clients. Each call may receive its own token store.
 */
final class DidoxClientFactory
{
    public function __construct(private readonly DidoxConfig|array $config)
    {
    }

    public function make(
        ?HttpTransportInterface $transport = null,
        ?TokenStoreInterface $tokenStore = null,
        DidoxConfig|array|null $config = null,
    ): DidoxClient {
        return new DidoxClient($config ?? $this->config, $transport, $tokenStore);
    }
}
