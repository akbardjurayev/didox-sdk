<?php

declare(strict_types=1);

namespace Didox\Http;

use Didox\Exceptions\ResponseParseException;
use JsonException;

final readonly class HttpResponse
{
    public function __construct(
        public int $statusCode,
        public array $headers,
        public string $body,
    ) {
    }

    public function isSuccessful(): bool
    {
        return $this->statusCode >= 200 && $this->statusCode < 300;
    }

    /** @return mixed */
    public function json(): mixed
    {
        if ($this->body === '') {
            return null;
        }

        try {
            return json_decode($this->body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new ResponseParseException('Didox returned an invalid JSON response.', 0, $exception);
        }
    }

    /** @return mixed */
    public function tryJson(): mixed
    {
        try {
            return $this->json();
        } catch (ResponseParseException) {
            return null;
        }
    }

    public function array(): array
    {
        $decoded = $this->json();
        if (!is_array($decoded)) {
            throw new ResponseParseException('Didox returned JSON with an unexpected shape.');
        }
        return $decoded;
    }
}
