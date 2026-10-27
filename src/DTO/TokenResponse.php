<?php

declare(strict_types=1);

namespace Didox\DTO;

use Didox\Exceptions\ResponseParseException;

/**
 * Stable authentication fields are typed; company and permission payloads
 * remain mixed because Didox documents them as variable response data.
 */
final readonly class TokenResponse
{
    public function __construct(
        public string $token,
        public ?string $taxId = null,
        public ?string $pinfl = null,
        public ?string $name = null,
        public mixed $relatedCompanies = null,
        public mixed $relatedBranches = null,
        public mixed $permissions = null,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        if (!array_key_exists('token', $data) || !is_string($data['token']) || trim($data['token']) === '') {
            throw new ResponseParseException('Didox authentication response must contain a string token field.');
        }
        foreach (['taxId', 'pinfl', 'name'] as $field) {
            if (array_key_exists($field, $data) && $data[$field] !== null && !is_string($data[$field])) {
                throw new ResponseParseException('Didox authentication response field ' . $field . ' must be a string or null.');
            }
        }
        return new self(
            $data['token'],
            $data['taxId'] ?? null,
            $data['pinfl'] ?? null,
            $data['name'] ?? null,
            $data['related_companies'] ?? null,
            $data['related_branches'] ?? null,
            $data['permissions'] ?? null,
        );
    }
}
