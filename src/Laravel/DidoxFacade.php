<?php

declare(strict_types=1);

namespace Didox\Laravel;

use Didox\Contracts\DidoxClientInterface;
use Didox\Services\AuthService;
use Didox\Services\CatalogService;
use Didox\Services\DocumentService;
use Didox\Services\DocumentTemplateService;
use Didox\Services\ProfileService;
use Illuminate\Support\Facades\Facade;

/**
 * @method static AuthService auth()
 * @method static DocumentService documents()
 * @method static ProfileService profile()
 * @method static CatalogService catalogs()
 * @method static DocumentTemplateService templates()
 */
final class DidoxFacade extends Facade
{
    /**
     * Resolve through the container on each facade call. Modern Laravel
     * scoped bindings preserve state within a request/job and are flushed
     * between lifecycles; older versions should use DidoxClientFactory in jobs.
     */
    protected static $cached = false;

    protected static function getFacadeAccessor(): string
    {
        return DidoxClientInterface::class;
    }
}
