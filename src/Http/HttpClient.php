<?php

declare(strict_types=1);

namespace Didox\Http;

use Didox\Contracts\HttpTransportInterface;
use Didox\Contracts\TokenStoreInterface;
use Didox\Exceptions\ApiException;
use Didox\Exceptions\AuthenticationException;
use Didox\Exceptions\AuthorizationException;
use Didox\Exceptions\NotFoundException;
use Didox\Exceptions\RateLimitException;
use Didox\Exceptions\SerializationException;
use Didox\Exceptions\ValidationException;
use Didox\Support\DidoxConfig;
use DateTimeImmutable;
use JsonException;

final class HttpClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $partnerToken,
        private readonly TokenStoreInterface $tokenStore,
        private readonly HttpTransportInterface $transport,
        private readonly int $timeout = 30,
        private readonly string $locale = DidoxConfig::DEFAULT_LOCALE,
    ) {
    }

    public function request(
        string $method,
        string $path,
        array $query = [],
        mixed $json = null,
        array $headers = [],
        bool $includeUserToken = true,
    ): HttpResponse {
        $url = rtrim($this->baseUrl, '/') . '/' . ltrim($path, '/');
        $query = array_filter($query, static fn (mixed $value): bool => $value !== null);
        if ($query !== []) {
            $url .= '?' . http_build_query($query);
        }

        $requestHeaders = array_merge([
            'Accept' => 'application/json',
            'Accept-Language' => $this->locale,
            'Partner-Authorization' => $this->partnerToken,
        ], $headers);
        if ($includeUserToken) {
            $userToken = $this->tokenStore->get();
            $expiresAt = $this->tokenStore->expiresAt();
            // Do not trust a custom store to hide an expired token from get().
            // The expiry metadata is an authentication boundary in its own
            // right and must prevent a request before the transport is called.
            if ($expiresAt !== null && $expiresAt <= new DateTimeImmutable()) {
                throw new AuthenticationException('The Didox user token has expired.', 401);
            }
            if ($userToken !== null) {
                $requestHeaders['user-key'] = $userToken;
            }
        }

        $body = '';
        if ($json !== null) {
            if (is_string($json)) {
                $body = $json;
            } else {
                try {
                    $body = json_encode($json, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                } catch (JsonException $exception) {
                    throw new SerializationException('Unable to serialize the Didox request payload as JSON.', 0, $exception);
                }
            }
            $requestHeaders['Content-Type'] ??= 'application/json';
        }

        $response = $this->transport->send(
            new HttpRequest($method, $url, $requestHeaders, $body),
            $this->timeout,
        );

        if (!$response->isSuccessful()) {
            throw $this->exceptionFor($response, $this->requestSensitiveValues($json));
        }

        return $response;
    }

    public function get(string $path, array $query = [], array $headers = [], bool $includeUserToken = true): HttpResponse
    {
        return $this->request('GET', $path, $query, null, $headers, $includeUserToken);
    }

    public function post(string $path, mixed $json = null, array $query = [], array $headers = [], bool $includeUserToken = true): HttpResponse
    {
        return $this->request('POST', $path, $query, $json, $headers, $includeUserToken);
    }

    public function put(string $path, mixed $json = null, array $query = [], array $headers = [], bool $includeUserToken = true): HttpResponse
    {
        return $this->request('PUT', $path, $query, $json, $headers, $includeUserToken);
    }

    public function delete(string $path, array $query = [], array $headers = [], bool $includeUserToken = true): HttpResponse
    {
        return $this->request('DELETE', $path, $query, null, $headers, $includeUserToken);
    }

    /** @param list<string> $requestSecrets */
    private function exceptionFor(HttpResponse $response, array $requestSecrets = []): ApiException
    {
        $decoded = $response->tryJson();
        $knownSecrets = array_values(array_filter(array_merge(
            $requestSecrets,
            [$this->partnerToken, $this->tokenStore->get()],
            $this->sensitiveValues($decoded),
        ), static fn (mixed $secret): bool => is_string($secret) && $secret !== ''));
        $safeBody = $this->redactKnownSecrets($this->redact($response->body), $knownSecrets);
        $message = $this->redact($this->errorMessage($response));
        $message = $this->redactKnownSecrets($message, $knownSecrets);
        $arguments = [$message, $response->statusCode, $safeBody, $this->redactHeaders($response->headers, $knownSecrets)];

        return match ($response->statusCode) {
            401, 423 => new AuthenticationException(...$arguments),
            403 => new AuthorizationException(...$arguments),
            404 => new NotFoundException(...$arguments),
            422 => new ValidationException(...$arguments),
            429 => new RateLimitException(...$arguments),
            default => new ApiException(...$arguments),
        };
    }

    private function errorMessage(HttpResponse $response): string
    {
        $decoded = $response->tryJson();
        if (is_array($decoded)) {
            foreach ([['data', 'message'], ['error', 'message'], ['message'], ['error']] as $path) {
                $value = $decoded;
                foreach ($path as $key) {
                    if (!is_array($value) || !array_key_exists($key, $value)) {
                        $value = null;
                        break;
                    }
                    $value = $value[$key];
                }
                if (is_string($value) && $value !== '') {
                    return $value;
                }
            }
        }

        return 'Didox API request failed with HTTP ' . $response->statusCode . '.';
    }

    private function redact(string $body): string
    {
        try {
            $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
            $sanitized = $this->redactValue($decoded);
            return json_encode($sanitized, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (JsonException) {
            // Malformed/non-JSON diagnostics are bounded and handled conservatively.
            $body = preg_replace(
                '/("?[^\s"=:,{}]*(?:password|passwd|passphrase|pwd|secret|credential|token|authorization|user-key|signature|signingpayload|pkcs7|private[_-]?key|certificate|cert|api[-_]?key|access[-_]?key)[^\s"=:,{}]*"?\s*[:=]\s*)"(?:\\\\.|[^"\\\\])*"/iu',
                '$1"[REDACTED]"',
                $body,
            ) ?? $body;
            $body = preg_replace('/\b(Bearer|Token)\s+[^\s,;]+/i', '$1 [REDACTED]', $body) ?? $body;
            $body = preg_replace('/(\b(?:password|passwd|passphrase|pwd|secret|credential|token|authorization|user-key|signature|signingpayload|pkcs7|private[_-]?key|certificate|cert|api[-_]?key|access[-_]?key)\s*[:=]\s*)[^\s,;]+/iu', '$1[REDACTED]', $body) ?? $body;
            return substr($body, 0, 8192);
        }
    }

    private function redactValue(mixed $value, ?string $key = null): mixed
    {
        if ($key !== null && $this->isSensitiveKey($key)) {
            return '[REDACTED]';
        }
        if (is_string($value)) {
            $value = preg_replace('/\b(Bearer|Token)\s+[^\s,;]+/i', '$1 [REDACTED]', $value) ?? $value;
            return preg_replace('/\b(?:password|passwd|passphrase|pwd|secret|credential|token|authorization|signature|signingpayload|pkcs7|private[_-]?key|certificate|cert|api[-_]?key|access[-_]?key)\s*[:=]\s*[^\s,;]+/iu', '[REDACTED]', $value) ?? $value;
        }
        if (!is_array($value)) {
            return $value;
        }
        foreach ($value as $childKey => $childValue) {
            $value[$childKey] = $this->redactValue($childValue, is_string($childKey) ? $childKey : null);
        }
        return $value;
    }

    /** @param list<string|null> $secrets */
    private function redactKnownSecrets(string $value, array $secrets): string
    {
        foreach ($secrets as $secret) {
            if (is_string($secret) && $secret !== '') {
                $value = str_replace($secret, '[REDACTED]', $value);
            }
        }

        return $value;
    }

    /** @return list<string> */
    private function requestSensitiveValues(mixed $value, ?string $key = null): array
    {
        if ($key !== null && $this->isSensitiveKey($key) && is_string($value)) {
            return [$value];
        }
        if (!is_array($value)) {
            return [];
        }

        $values = [];
        foreach ($value as $childKey => $childValue) {
            foreach ($this->requestSensitiveValues($childValue, is_string($childKey) ? $childKey : null) as $secret) {
                $values[] = $secret;
            }
        }

        return $values;
    }

    /** @return list<string> */
    private function sensitiveValues(mixed $value, ?string $key = null): array
    {
        if ($key !== null && $this->isSensitiveKey($key) && is_string($value)) {
            return [$value];
        }
        if (!is_array($value)) {
            return [];
        }
        $values = [];
        foreach ($value as $childKey => $childValue) {
            foreach ($this->sensitiveValues($childValue, is_string($childKey) ? $childKey : null) as $secret) {
                $values[] = $secret;
            }
        }
        return $values;
    }

    private function isSensitiveKey(string $key): bool
    {
        $normalized = strtolower(str_replace(['-', '_', ' '], '', $key));
        foreach (['password', 'passwd', 'passphrase', 'pwd', 'secret', 'credential', 'token', 'authorization', 'userkey', 'signature', 'signingpayload', 'pkcs7', 'privatekey', 'certificate', 'apikey', 'accesskey'] as $needle) {
            if (str_contains($normalized, $needle)) {
                return true;
            }
        }
        return false;
    }

    /** @param list<string> $knownSecrets */
    private function redactHeaders(array $headers, array $knownSecrets = []): array
    {
        foreach ($headers as $name => $value) {
            $lower = strtolower((string) $name);
            if (str_contains($lower, 'authorization') || str_contains($lower, 'user-key') || str_contains($lower, 'cookie') || str_contains($lower, 'token') || str_contains($lower, 'signature') || str_contains($lower, 'password') || str_contains($lower, 'credential') || str_contains($lower, 'pkcs7') || str_contains($lower, 'private') || str_contains($lower, 'certificate') || str_contains($lower, 'api-key') || str_contains($lower, 'access-key') || str_contains($lower, 'secret')) {
                $headers[$name] = '[REDACTED]';
            } elseif (is_string($value)) {
                $headers[$name] = $this->redactKnownSecrets($value, $knownSecrets);
            }
        }
        return $headers;
    }
}
