<?php

declare(strict_types=1);

namespace Didox\Services;

use Didox\Http\HttpClient;
use Didox\Support\Path;
use Didox\Support\DidoxConfig;

final class ProfileService
{
    public function __construct(
        private readonly HttpClient $http,
        private readonly string $defaultLocale = DidoxConfig::DEFAULT_LOCALE,
    )
    {
    }

    public function current(bool $isSeller = false, ?string $locale = null): array
    {
        return $this->http->get('/v1/profile', ['isSeller' => $isSeller ?: null], ['Accept-Language' => $this->resolveLocale($locale)])->array();
    }

    public function update(array $payload): array
    {
        return $this->http->post('/v1/profile/update', $payload)->array();
    }

    public function operators(): array
    {
        return $this->http->get('/v1/profile/operators')->array();
    }

    public function branches(): array
    {
        return $this->http->get('/v1/profile/branches')->array();
    }

    public function productClasses(array $query = []): array
    {
        return $this->http->get('/v1/profile/productClassCodes', $query)->array();
    }

    public function attachProductClass(string $classCode): array
    {
        return $this->http->post('/v1/profile/productClasses', compact('classCode'))->array();
    }

    public function detachProductClass(string $classCode): array
    {
        return $this->http->delete('/v1/profile/productClasses/' . Path::segment($classCode))->array();
    }

    public function vatRegistrationStatus(string $taxId, array $query = []): array
    {
        return $this->http->get('/v1/profile/vatRegStatus/' . Path::segment($taxId), $query)->array();
    }

    public function productPackages(string $taxId, string $code, ?string $locale = null): array
    {
        $locale = $this->resolveLocale($locale);
        return $this->http->get('/v1/profile/' . Path::segment($taxId) . '/productClasses/check/' . Path::segment($code) . '/' . Path::segment($locale), [], ['Accept-Language' => $locale])->array();
    }

    public function warehouses(string $taxId): array
    {
        return $this->http->get('/v1/profile/warehouses/' . Path::segment($taxId))->array();
    }

    public function taxpayerType(string $taxId, ?string $locale = null, array $query = []): array
    {
        $locale = $this->resolveLocale($locale);
        return $this->http->get('/v1/profile/taxpayerType/' . Path::segment($taxId) . '/' . Path::segment($locale), $query, ['Accept-Language' => $locale])->array();
    }

    public function configureCompanyUser(array $payload): array
    {
        return $this->http->put('/v1/profile/company/users', $payload)->array();
    }

    private function resolveLocale(?string $locale): string
    {
        return DidoxConfig::validateLocale($locale ?? $this->defaultLocale);
    }
}
