<?php

declare(strict_types=1);

namespace Didox\Services;

use Didox\DTO\TemplateResponse;
use Didox\Http\HttpClient;
use Didox\Support\Path;

final class DocumentTemplateService
{
    public function __construct(private readonly HttpClient $http)
    {
    }

    public function list(array $query = []): array
    {
        return $this->http->get('/v1/document-template', $query)->array();
    }

    public function find(int|string $id): TemplateResponse
    {
        return TemplateResponse::fromArray($this->http->get('/v1/document-template/' . Path::segment($id))->array());
    }

    public function create(array $payload): TemplateResponse
    {
        return TemplateResponse::fromArray($this->http->post('/v1/document-template', $payload)->array());
    }

    public function update(int|string $id, array $payload): TemplateResponse
    {
        return TemplateResponse::fromArray($this->http->put('/v1/document-template/' . Path::segment($id), $payload)->array());
    }

    public function delete(int|string $id): array
    {
        return $this->http->delete('/v1/document-template/' . Path::segment($id))->array();
    }
}
