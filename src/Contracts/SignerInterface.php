<?php

declare(strict_types=1);

namespace Didox\Contracts;

interface SignerInterface
{
    /**
     * Sign the supplied base64 or canonical JSON payload using an external signer.
     *
     * The SDK does not implement E-IMZO. Applications can adapt an E-IMZO
     * library or a remote signing service to this contract and pass the
     * returned signature to AuthService or DocumentService operations.
     */
    public function sign(string $payload): string;
}
