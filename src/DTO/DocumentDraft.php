<?php

declare(strict_types=1);

namespace Didox\DTO;

use Didox\Exceptions\ResponseParseException;

final readonly class DocumentDraft
{
    /** @param array<string, mixed> $documentJson @param array<string, mixed> $raw */
    public function __construct(
        public string $id,
        public ?string $createdDate,
        public array $documentJson,
        public array $raw,
    ) {
    }

    /** @param array<string, mixed> $payload */
    public static function fromArray(array $payload): self
    {
        if (array_key_exists('data', $payload) && !is_array($payload['data'])) {
            throw new ResponseParseException('Didox document draft response data must be an object.');
        }
        $source = array_key_exists('data', $payload) ? $payload['data'] : $payload;
        if (array_key_exists('pending_document', $source) && !is_array($source['pending_document'])) {
            throw new ResponseParseException('Didox document draft pending_document must be an object.');
        }
        $pending = array_key_exists('pending_document', $source) ? $source['pending_document'] : $source;

        $id = $source['_id'] ?? $payload['_id'] ?? null;
        if (!is_string($id) || trim($id) === '') {
            throw new ResponseParseException('Didox document draft response must contain a non-empty string _id.');
        }
        $createdDate = $source['created_date'] ?? null;
        if ($createdDate !== null && !is_string($createdDate)) {
            throw new ResponseParseException('Didox document draft created_date must be a string or null.');
        }
        if (!array_key_exists('document_json', $pending) || !is_array($pending['document_json'])) {
            throw new ResponseParseException('Didox document draft document_json must be an object.');
        }
        $documentJson = $pending['document_json'];

        return new self(
            $id,
            $createdDate,
            $documentJson,
            $payload,
        );
    }
}
