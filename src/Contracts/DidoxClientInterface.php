<?php

declare(strict_types=1);

namespace Didox\Contracts;

use Didox\Services\AuthService;
use Didox\Services\CatalogService;
use Didox\Services\DocumentService;
use Didox\Services\DocumentTemplateService;
use Didox\Services\ProfileService;

interface DidoxClientInterface
{
    public function auth(): AuthService;

    public function documents(): DocumentService;

    public function profile(): ProfileService;

    public function catalogs(): CatalogService;

    public function templates(): DocumentTemplateService;
}
