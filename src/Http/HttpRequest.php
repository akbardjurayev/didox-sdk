<?php

declare(strict_types=1);

namespace Didox\Http;

final readonly class HttpRequest
{
    public function __construct(
        public string $method,
        public string $url,
        public array $headers = [],
        public string $body = '',
    ) {
    }
}
