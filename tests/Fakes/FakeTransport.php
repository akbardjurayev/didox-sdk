<?php

declare(strict_types=1);

namespace Didox\Tests\Fakes;

use Didox\Contracts\HttpTransportInterface;
use Didox\Http\HttpRequest;
use Didox\Http\HttpResponse;
use LogicException;

final class FakeTransport implements HttpTransportInterface
{
    /** @var list<HttpResponse|\Throwable> */
    private array $responses;

    public ?HttpRequest $lastRequest = null;
    public ?int $lastTimeout = null;

    public function __construct(HttpResponse|\Throwable ...$responses)
    {
        $this->responses = $responses;
    }

    public function send(HttpRequest $request, int $timeout): HttpResponse
    {
        $this->lastRequest = $request;
        $this->lastTimeout = $timeout;
        if ($this->responses === []) {
            throw new LogicException('No fake response queued.');
        }
        $response = array_shift($this->responses);
        if ($response instanceof \Throwable) {
            throw $response;
        }
        if (!$response instanceof HttpResponse) {
            throw new LogicException('Fake transport response is invalid.');
        }
        return $response;
    }
}
