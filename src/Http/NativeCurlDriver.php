<?php

declare(strict_types=1);

namespace Didox\Http;

use Didox\Contracts\CurlDriverInterface;

final class NativeCurlDriver implements CurlDriverInterface
{
    public function init(string $url): mixed
    {
        return curl_init($url);
    }

    public function setOptions(mixed $handle, array $options): void
    {
        curl_setopt_array($handle, $options);
    }

    public function exec(mixed $handle): string|false
    {
        return curl_exec($handle);
    }

    public function error(mixed $handle): string
    {
        return curl_error($handle);
    }

    public function info(mixed $handle, int $option): int
    {
        return (int) curl_getinfo($handle, $option);
    }

    public function close(mixed $handle): void
    {
        curl_close($handle);
    }
}
