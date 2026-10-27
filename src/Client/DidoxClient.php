<?php

declare(strict_types=1);

namespace Didox\Client;

use Didox\Contracts\DidoxClientInterface;
use Didox\Contracts\HttpTransportInterface;
use Didox\Contracts\TokenStoreInterface;
use Didox\Http\CurlHttpTransport;
use Didox\Http\HttpClient;
use Didox\Services\AuthService;
use Didox\Services\CatalogService;
use Didox\Services\DocumentService;
use Didox\Services\DocumentTemplateService;
use Didox\Services\ProfileService;
use Didox\Support\DidoxConfig;
use Didox\Support\InMemoryTokenStore;

final class DidoxClient implements DidoxClientInterface
{
    private readonly TokenStoreInterface $tokenStore;
    private readonly HttpClient $http;
    private ?AuthService $authService = null;
    private ?DocumentService $documentService = null;
    private ?ProfileService $profileService = null;
    private ?CatalogService $catalogService = null;
    private ?DocumentTemplateService $templateService = null;
    private readonly string $serviceLocale;

    public function __construct(
        DidoxConfig|array $config,
        ?HttpTransportInterface $transport = null,
        ?TokenStoreInterface $tokenStore = null,
    ) {
        $config = is_array($config) ? DidoxConfig::fromArray($config) : $config;
        $this->tokenStore = $tokenStore ?? new InMemoryTokenStore($config->userToken);
        $this->http = new HttpClient(
            $config->baseUrl,
            $config->partnerToken,
            $this->tokenStore,
            $transport ?? new CurlHttpTransport(),
            $config->timeout,
            $config->locale,
        );
        $this->serviceLocale = $config->locale;
    }

    public function auth(): AuthService
    {
        return $this->authService ??= new AuthService($this->http, $this->tokenStore, $this->serviceLocale);
    }

    public function documents(): DocumentService
    {
        return $this->documentService ??= new DocumentService($this->http, $this->serviceLocale);
    }

    public function profile(): ProfileService
    {
        return $this->profileService ??= new ProfileService($this->http, $this->serviceLocale);
    }

    public function catalogs(): CatalogService
    {
        return $this->catalogService ??= new CatalogService($this->http, $this->serviceLocale);
    }

    public function templates(): DocumentTemplateService
    {
        return $this->templateService ??= new DocumentTemplateService($this->http);
    }
}
