<?php

declare(strict_types=1);

namespace Didox\Http;

use Didox\Contracts\HttpTransportInterface;
use Didox\Contracts\CurlDriverInterface;
use Didox\Exceptions\TransportException;

final class CurlHttpTransport implements HttpTransportInterface
{
    public function __construct(private readonly CurlDriverInterface $curl = new NativeCurlDriver())
    {
    }

    public function send(HttpRequest $request, int $timeout): HttpResponse
    {
        $handle = $this->curl->init($request->url);
        if ($handle === false) {
            throw new TransportException('Unable to initialize the HTTP transport.');
        }

        $responseHeaders = [];
        $headerLines = [];
        foreach ($request->headers as $name => $value) {
            $headerLines[] = $name . ': ' . $value;
        }

        $this->curl->setOptions($handle, [
            CURLOPT_CUSTOMREQUEST => strtoupper($request->method),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => $timeout,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_POSTFIELDS => $request->body,
            CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$responseHeaders): int {
                $trimmed = trim($line);
                if ($trimmed === '' || str_starts_with($trimmed, 'HTTP/')) {
                    return strlen($line);
                }
                $parts = explode(':', $trimmed, 2);
                if (count($parts) === 2) {
                    $responseHeaders[trim($parts[0])] = trim($parts[1]);
                }
                return strlen($line);
            },
        ]);

        $body = $this->curl->exec($handle);
        if ($body === false) {
            $error = $this->curl->error($handle);
            $this->curl->close($handle);
            throw new TransportException('Didox HTTP transport failed: ' . $error);
        }

        $status = $this->curl->info($handle, CURLINFO_RESPONSE_CODE);
        $this->curl->close($handle);

        return new HttpResponse($status, $responseHeaders, (string) $body);
    }
}
