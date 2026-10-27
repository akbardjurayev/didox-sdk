<?php

declare(strict_types=1);

namespace Didox\Services;

use Didox\DTO\DocumentDraft;
use Didox\DTO\DocumentResponse;
use Didox\DTO\SigningPayload;
use Didox\Http\HttpClient;
use Didox\Http\HttpResponse;
use Didox\Support\DocumentAction;
use Didox\Support\Path;
use Didox\Support\DidoxConfig;
use Didox\Exceptions\ResponseParseException;

final class DocumentService
{
    public function __construct(
        private readonly HttpClient $http,
        private readonly string $defaultLocale = DidoxConfig::DEFAULT_LOCALE,
    )
    {
    }

    public function list(array $filters = []): array
    {
        if (!array_key_exists('page', $filters) || !array_key_exists('limit', $filters)) {
            throw new \InvalidArgumentException('Didox document lists require page and limit filters.');
        }
        return $this->http->get('/v2/documents', $filters)->array();
    }

    public function statistics(array $filters = []): array
    {
        return $this->http->get('/v2/documents/statistics/all', $filters)->array();
    }

    public function find(string $id, ?bool $owner = null): DocumentResponse
    {
        return DocumentResponse::fromArray($this->http->get('/v1/documents/' . Path::segment($id), ['owner' => $owner])->array());
    }

    public function privileges(string $id, ?string $locale = null): array
    {
        $locale = $this->resolveLocale($locale);
        return $this->http->get('/v1/documents/' . Path::segment($id) . '/privileges/' . Path::segment($locale), [], ['Accept-Language' => $locale])->array();
    }

    public function create(string $documentType, array $payload, ?string $locale = null): DocumentDraft
    {
        $locale = $this->resolveLocale($locale);
        return DocumentDraft::fromArray($this->http->post('/v1/documents/' . Path::segment($documentType) . '/create/' . Path::segment($locale), $payload, headers: ['Accept-Language' => $locale])->array());
    }

    public function update(string $id, string $documentType, array $payload, ?string $locale = null): DocumentDraft
    {
        $locale = $this->resolveLocale($locale);
        return DocumentDraft::fromArray($this->http->post('/v1/documents/' . Path::segment($id) . '/update/' . Path::segment($documentType) . '/' . Path::segment($locale), $payload, headers: ['Accept-Language' => $locale])->array());
    }

    public function deleteDraft(string $id): bool
    {
        $payload = $this->http->post('/v1/documents/' . Path::segment($id) . '/delete/draft')->array();
        if (!array_key_exists('data', $payload) || !is_bool($payload['data'])) {
            throw new ResponseParseException('Didox draft deletion response must contain a boolean data field.');
        }
        return $payload['data'];
    }

    public function toSign(string $id, string|DocumentAction $action, ?string $comment = null): SigningPayload
    {
        $payload = ['action' => $action instanceof DocumentAction ? $action->value : $action];
        if ($comment !== null) {
            $payload['comment'] = $comment;
        }
        return SigningPayload::fromArray($this->http->post('/v1/documents/' . Path::segment($id) . '/tosign', $payload)->array());
    }

    public function sign(string $id, string $signature): array
    {
        $payload = $this->http->post('/v1/documents/' . Path::segment($id) . '/sign', ['signature' => $signature])->array();
        if (array_key_exists('data', $payload) && !is_bool($payload['data'])) {
            throw new ResponseParseException('Didox signing response data must be boolean when present.');
        }
        if (array_key_exists('warningDetails', $payload) && $payload['warningDetails'] !== null && !is_array($payload['warningDetails'])) {
            throw new ResponseParseException('Didox signing response warningDetails must be an object or null when present.');
        }
        return $payload;
    }

    public function reject(string $id, string $signature, string $comment): array
    {
        return $this->http->post('/v1/documents/' . Path::segment($id) . '/reject', compact('signature', 'comment'))->array();
    }

    public function cancel(string $id, string $signature): array
    {
        return $this->http->post('/v1/documents/' . Path::segment($id) . '/delete', ['signature' => $signature])->array();
    }

    public function give(string $id, string $signature): array
    {
        return $this->http->post('/v1/documents/' . Path::segment($id) . '/give', ['signature' => $signature])->array();
    }

    public function tillReturn(string $id, string $signature, string $comment): array
    {
        return $this->http->post('/v1/documents/' . Path::segment($id) . '/tillreturn', compact('signature', 'comment'))->array();
    }

    public function returnDelivered(string $id, string $signature, string $comment): array
    {
        return $this->http->post('/v1/documents/' . Path::segment($id) . '/return', compact('signature', 'comment'))->array();
    }

    public function html(string $id, ?string $locale = null, bool $scopedToUser = true): string
    {
        return $this->printResponse($id, 'html', $locale, $scopedToUser)->body;
    }

    public function pdf(string $id, ?string $locale = null, bool $scopedToUser = true): HttpResponse
    {
        return $this->printResponse($id, 'pdf', $locale, $scopedToUser);
    }

    public function pdfBase64(string $id, ?string $locale = null): array
    {
        $locale = $this->resolveLocale($locale);
        return $this->http->get('/v1/documents/' . Path::segment($id) . '/file/true/' . Path::segment($locale), [], ['Accept-Language' => $locale], false)->array();
    }

    public function archive(string $id, ?string $locale = null): HttpResponse
    {
        $locale = $this->resolveLocale($locale);
        return $this->http->get('/v1/documents/' . Path::segment($id) . '/archive', [], ['Accept-Language' => $locale]);
    }

    public function contractInfo(int|string $contractId, ?string $locale = null): array
    {
        $locale = $this->resolveLocale($locale);
        return $this->http->get('/v1/documents/contract/' . Path::segment($contractId) . '/info/' . Path::segment($locale), [], ['Accept-Language' => $locale])->array();
    }

    public function exchange(array $query = []): array
    {
        return $this->http->get('/v1/documents/exchange', $query)->array();
    }

    public function exchangeTypes(): array
    {
        return $this->http->get('/v1/documents/exchange/types')->array();
    }

    public function exchangeLotTypes(string $buyerTin, string $lotId, ?string $locale = null): array
    {
        $locale = $this->resolveLocale($locale);
        return $this->http->get('/v1/documents/exchange/lotswithtypes/' . Path::segment($locale), compact('buyerTin', 'lotId'), ['Accept-Language' => $locale])->array();
    }

    private function printResponse(string $id, string $format, ?string $locale, bool $scopedToUser): HttpResponse
    {
        $locale = $this->resolveLocale($locale);
        $prefix = $scopedToUser ? '/v1/documents/view' : '/v1/documents';
        return $this->http->get($prefix . '/' . Path::segment($id) . '/' . Path::segment($format) . '/' . Path::segment($locale), [], ['Accept-Language' => $locale], !$scopedToUser ? false : true);
    }

    private function resolveLocale(?string $locale): string
    {
        return DidoxConfig::validateLocale($locale ?? $this->defaultLocale);
    }
}
