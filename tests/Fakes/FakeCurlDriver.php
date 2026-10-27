<?php

declare(strict_types=1);

namespace Didox\Tests\Fakes;

use Didox\Contracts\CurlDriverInterface;

final class FakeCurlDriver implements CurlDriverInterface
{
    public array $options = [];
    public bool $closed = false;

    public function __construct(private readonly string|false $result, private readonly string $error = 'fixture curl failure')
    {
    }

    public function init(string $url): mixed
    {
        return ['url' => $url];
    }

    public function setOptions(mixed $handle, array $options): void
    {
        $this->options = $options;
    }

    public function exec(mixed $handle): string|false
    {
        return $this->result;
    }

    public function error(mixed $handle): string
    {
        return $this->error;
    }

    public function info(mixed $handle, int $option): int
    {
        return 200;
    }

    public function close(mixed $handle): void
    {
        $this->closed = true;
    }
}
