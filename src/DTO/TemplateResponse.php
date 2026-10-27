<?php

declare(strict_types=1);

namespace Didox\DTO;

use Didox\Exceptions\ResponseParseException;

final readonly class TemplateResponse
{
    /** @param array<string, mixed> $data */
    public function __construct(public array $data)
    {
    }

    /** @param array<string, mixed> $payload */
    public static function fromArray(array $payload): self
    {
        if (array_key_exists('data', $payload) && !is_array($payload['data'])) {
            throw new ResponseParseException('Didox template response data must be an object.');
        }
        $data = array_key_exists('data', $payload) ? $payload['data'] : $payload;
        if ($data === []) {
            throw new ResponseParseException('Didox template response must contain a non-empty object.');
        }
        return new self($data);
    }
}
