<?php

declare(strict_types=1);

namespace Didox\DTO;

use Didox\Exceptions\ResponseParseException;

final readonly class TimestampResponse
{
    public function __construct(
        public string $timeStampTokenB64,
        public bool $success,
        public ?bool $isAttachedPkcs7 = null,
    ) {
    }

    /**
     * @param array{timeStampTokenB64?: mixed, success?: mixed, isAttachedPkcs7?: mixed} $data
     */
    public static function fromArray(array $data): self
    {
        if (!array_key_exists('timeStampTokenB64', $data) || !is_string($data['timeStampTokenB64'])) {
            throw new ResponseParseException('Didox timestamp response must contain a string timeStampTokenB64 field.');
        }
        if (!array_key_exists('success', $data) || !is_bool($data['success'])) {
            throw new ResponseParseException('Didox timestamp response must contain a boolean success field.');
        }
        if (array_key_exists('isAttachedPkcs7', $data) && $data['isAttachedPkcs7'] !== null && !is_bool($data['isAttachedPkcs7'])) {
            throw new ResponseParseException('Didox timestamp response isAttachedPkcs7 must be boolean or null.');
        }
        if ($data['success'] && trim($data['timeStampTokenB64']) === '') {
            throw new ResponseParseException('Didox successful timestamp response must contain a non-empty token.');
        }
        return new self(
            $data['timeStampTokenB64'],
            $data['success'],
            $data['isAttachedPkcs7'] ?? null,
        );
    }
}
