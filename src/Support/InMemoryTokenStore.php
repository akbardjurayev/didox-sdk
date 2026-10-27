<?php

declare(strict_types=1);

namespace Didox\Support;

use DateTimeImmutable;
use Didox\Contracts\TokenStoreInterface;

final class InMemoryTokenStore implements TokenStoreInterface
{
    public function __construct(private ?string $token = null, private ?DateTimeImmutable $expiresAt = null)
    {
    }

    public function get(): ?string
    {
        if ($this->expiresAt !== null && $this->expiresAt <= new DateTimeImmutable()) {
            return null;
        }
        return $this->token;
    }

    public function set(string $token, ?DateTimeImmutable $expiresAt = null): void
    {
        $this->token = $token;
        $this->expiresAt = $expiresAt;
    }

    public function expiresAt(): ?DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function clear(): void
    {
        $this->token = null;
        $this->expiresAt = null;
    }
}
