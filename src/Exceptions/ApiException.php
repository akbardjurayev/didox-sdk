<?php

declare(strict_types=1);

namespace Didox\Exceptions;

class ApiException extends DidoxException
{
    public function __construct(
        string $message,
        private readonly int $statusCode,
        private readonly string $responseBody = '',
        private readonly array $responseHeaders = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $statusCode, $previous);
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    public function responseBody(): string
    {
        return $this->responseBody;
    }

    public function responseHeaders(): array
    {
        return $this->responseHeaders;
    }
}
