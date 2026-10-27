<?php

declare(strict_types=1);

namespace Didox\DTO;

use Didox\Exceptions\ResponseParseException;

/** @property-read mixed $data @property-read array<string, mixed> $raw */
final readonly class SigningPayload
{
    public function __construct(public mixed $data, public array $raw)
    {
    }

    /** @param array<string, mixed> $payload */
    public static function fromArray(array $payload): self
    {
        if (!array_key_exists('data', $payload) || $payload['data'] === null) {
            throw new ResponseParseException('Didox signing response must contain a data field.');
        }

        return new self($payload['data'], $payload);
    }
}
