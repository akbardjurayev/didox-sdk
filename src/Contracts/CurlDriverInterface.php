<?php

declare(strict_types=1);

namespace Didox\Contracts;

interface CurlDriverInterface
{
    public function init(string $url): mixed;

    public function setOptions(mixed $handle, array $options): void;

    public function exec(mixed $handle): string|false;

    public function error(mixed $handle): string;

    public function info(mixed $handle, int $option): int;

    public function close(mixed $handle): void;
}
