<?php

declare(strict_types=1);

namespace Didox\Contracts;

use Didox\Http\HttpRequest;
use Didox\Http\HttpResponse;

interface HttpTransportInterface
{
    public function send(HttpRequest $request, int $timeout): HttpResponse;
}
