<?php

declare(strict_types=1);

namespace Didox\Services;

use Didox\Http\HttpClient;
use Didox\Support\DidoxConfig;

final class CatalogService
{
    public function __construct(
        private readonly HttpClient $http,
        private readonly string $defaultLocale = DidoxConfig::DEFAULT_LOCALE,
    )
    {
    }

    public function banks(?string $locale = null): array
    {
        return $this->getLocalized('/v1/banks/all', $locale);
    }

    public function measures(?string $locale = null): array
    {
        return $this->getLocalized('/v1/measures/all', $locale);
    }

    public function regions(?string $locale = null): array
    {
        return $this->getLocalized('/v1/regions/all', $locale);
    }

    public function districts(?string $locale = null): array
    {
        return $this->getLocalized('/v1/districts/all', $locale);
    }

    public function railwayLines(?string $locale = null): array
    {
        return $this->getLocalized('/v1/utils/waybills/railwaylines', $locale);
    }

    private function getLocalized(string $path, ?string $locale): array
    {
        $locale = DidoxConfig::validateLocale($locale ?? $this->defaultLocale);
        return $this->http->get($path, [], ['Accept-Language' => $locale])->array();
    }
}
