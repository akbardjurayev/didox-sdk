<?php

declare(strict_types=1);

namespace Didox\Services;

use DateTimeImmutable;
use Didox\DTO\TimestampResponse;
use Didox\DTO\TokenResponse;
use Didox\Contracts\TokenStoreInterface;
use Didox\Http\HttpClient;
use Didox\Support\Path;
use Didox\Support\DidoxConfig;

final class AuthService
{
    public function __construct(
        private readonly HttpClient $http,
        private readonly TokenStoreInterface $tokenStore,
        private readonly string $defaultLocale = DidoxConfig::DEFAULT_LOCALE,
    ) {
    }

    public function loginWithPassword(string $taxId, string $password, ?string $locale = null): TokenResponse
    {
        $locale = $this->resolveLocale($locale);
        $response = $this->http->post('/v1/auth/' . Path::segment($taxId) . '/password/' . Path::segment($locale), ['password' => $password], headers: ['Accept-Language' => $locale], includeUserToken: false);
        return $this->storeToken($response->array());
    }

    public function loginWithSignature(string $taxId, string $signature, ?string $locale = null): TokenResponse
    {
        $locale = $this->resolveLocale($locale);
        $response = $this->http->post('/v1/auth/' . Path::segment($taxId) . '/token/' . Path::segment($locale), ['signature' => $signature], headers: ['Accept-Language' => $locale], includeUserToken: false);
        return $this->storeToken($response->array());
    }

    public function loginToCompany(string $companyTaxId, ?string $branchNum = null, ?string $locale = null): TokenResponse
    {
        $locale = $this->resolveLocale($locale);
        $payload = $branchNum === null ? null : ['branchNum' => $branchNum];
        $response = $this->http->post('/v1/auth/company/' . Path::segment($companyTaxId) . '/login/' . Path::segment($locale), $payload, headers: ['Accept-Language' => $locale]);
        return $this->storeToken($response->array());
    }

    public function timestamp(string $pkcs7, string $signatureHex): TimestampResponse
    {
        $response = $this->http->post('/v1/dsvs/timestamp', compact('pkcs7', 'signatureHex'), includeUserToken: false);
        return TimestampResponse::fromArray($response->array());
    }

    public function register(array $payload): TokenResponse
    {
        $response = $this->http->post('/v1/auth/signup', $payload, includeUserToken: false);
        return $this->storeToken($response->array());
    }

    public function joinSignatures(string $signature1, string $signature2): array
    {
        return $this->http->post('/v1/dsvs/signature/join', compact('signature1', 'signature2'))->array();
    }

    public function logout(): void
    {
        $this->tokenStore->clear();
    }

    private function storeToken(array $payload): TokenResponse
    {
        $token = TokenResponse::fromArray($payload);
        if ($token->token === '') {
            throw new \Didox\Exceptions\ResponseParseException('Didox authentication response did not contain a user token.');
        }
        $this->tokenStore->set($token->token, new DateTimeImmutable('+360 minutes'));
        return $token;
    }

    private function resolveLocale(?string $locale): string
    {
        return DidoxConfig::validateLocale($locale ?? $this->defaultLocale);
    }
}
