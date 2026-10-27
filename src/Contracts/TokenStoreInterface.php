<?php

declare(strict_types=1);

namespace Didox\Contracts;

use DateTimeImmutable;

interface TokenStoreInterface
{
    public function get(): ?string;

    public function set(string $token, ?DateTimeImmutable $expiresAt = null): void;

    public function expiresAt(): ?DateTimeImmutable;

    public function clear(): void;
}
