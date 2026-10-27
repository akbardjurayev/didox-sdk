# Didox PHP SDK

Standalone PHP 8.2+ client for the documented Didox partner API. The core
package has no Laravel dependency; Laravel support is an optional provider and
facade layer.

## Status

Implemented routes are individually classified as confirmed, unverified, or
not implemented in [docs/didox-api-map.md](docs/didox-api-map.md). The SDK does
not claim that a mocked test proves production compatibility.

No production API calls are made by the test suite.

## Installation

```bash
composer require didox/sdk
```

The package requires PHP 8.2+ and the `curl` and `json` extensions.

## Configuration

```php
use Didox\Client\DidoxClient;

$didox = new DidoxClient([
    'base_url' => getenv('DIDOX_BASE_URL') ?: 'https://testapi3.didox.uz',
    'partner_token' => getenv('DIDOX_PARTNER_TOKEN') ?: '',
    'user_token' => getenv('DIDOX_USER_TOKEN') ?: null,
    'locale' => getenv('DIDOX_LOCALE') ?: 'ru',
    'timeout' => 30,
]);
```

Use `https://testapi3.didox.uz` for testing and
`https://api-partners.didox.uz` for production. Keep partner and user tokens in
environment or secret storage. HTTPS is required and the cURL transport
explicitly verifies both the peer certificate and hostname. Credentials are
never written to logs by this SDK.

## Authentication

Password login stores the returned user token in the client's in-memory token
store for later calls and records the documented 360-minute lifetime:

```php
$token = $didox->auth()->loginWithPassword($taxId, $password);
```

E-IMZO signing is intentionally external to this package. Pass the resulting
timestamped signature to the authentication or document methods:

```php
$token = $didox->auth()->loginWithSignature($taxId, $timestampedSignature);
$timestamp = $didox->auth()->timestamp($pkcs7, $signatureHex);
```

To switch a physical user into a company context:

```php
$companyToken = $didox->auth()->loginToCompany($companyTaxId, $branchNumber);
```

There is no documented Didox token-refresh operation in the official API
reference. Tokens received by the SDK expire locally after 360 minutes; supply
a new token or authenticate again when that happens. A custom `TokenStoreInterface`
can persist tokens outside process memory. A token supplied directly through
`DIDOX_USER_TOKEN` has no verifiable issue time, so the SDK cannot apply local
expiry to it; applications should rotate that value according to their own
secret-management policy.

Locale defaults to the configured `DIDOX_LOCALE` value (`ru` or `uz`). Methods
that expose a locale argument accept a per-call override; invalid values are
rejected before an HTTP request is sent.

`SignerInterface` is an extension contract only. It does not perform E-IMZO
cryptography automatically. An application may adapt its own signer:

```php
final class EimzoSigner implements \Didox\Contracts\SignerInterface
{
    public function __construct(private EimzoClient $eimzoClient) {}

    public function sign(string $payload): string
    {
        return $this->eimzoClient->sign($payload);
    }
}

$signer = new EimzoSigner($eimzoClient); // application-owned integration
$signature = $signer->sign($payload);
$didox->auth()->loginWithSignature($taxId, $signature);
```

The returned signature is then supplied explicitly to `loginWithSignature()`,
`timestamp()`, `toSign()`, or the appropriate document action. Incoming-document
signing and arbitrary PDF upload remain incomplete because Didox's published
documentation does not fully define those workflows.

## Documents

```php
$documents = $didox->documents()->list([
    'page' => 1,
    'limit' => 20,
    'owner' => 1,
]);

$document = $didox->documents()->find($id);
$draft = $didox->documents()->create('002', $invoicePayload);
$signingPayload = $didox->documents()->toSign($id, 'accept');
$didox->documents()->sign($id, $timestampedSignature);
```

The documented creation types include invoices (`002`, `008`, `023`), waybills
(`041`), acts, powers of attorney, contracts (`007`), arbitrary documents, and
other types listed in the API map. Invoice and contract operations use Didox's
generic document endpoints; no undocumented resource-specific URLs are
assumed.

Document service methods also cover draft deletion, rejection, cancellation,
TTN actions, privileges, statistics, print forms, PDF base64, archives, and
exchange-lot lookups. The full incoming-signing flow and arbitrary PDF upload
remain incomplete because the official documentation does not fully specify all
required transport steps.

## Profile, catalogs, and templates

```php
$profile = $didox->profile()->current();
$banks = $didox->catalogs()->banks('ru');
$templates = $didox->templates()->list(['page' => 1, 'size' => 20, 'docType' => '007']);
```

The profile service includes profile updates, operators, branches, product
classes, VAT status, warehouses, taxpayer type, and company-user roles. The
catalog service includes banks, measures, regions, districts, and railway
lines. The template service supports the documented CRUD endpoints.

## Errors

All requests pass through one HTTP client. Non-success responses become typed
exceptions:

- `AuthenticationException` for 401 and 423
- `AuthorizationException` for 403
- `NotFoundException` for 404
- `ValidationException` for 422
- `RateLimitException` for 429
- `ResponseParseException` for invalid successful response shapes or JSON
- `SerializationException` for outgoing JSON encoding failures
- `ApiException` for other API failures

Exceptions expose the status code, response headers, and a redacted response
body. Authentication material is removed from stored error content.
Valid JSON error bodies are redacted recursively by sensitive field name,
including nested passwords, tokens, authorization values, signatures, PKCS#7
material, private keys, and certificates. Malformed diagnostics are bounded and
sanitized conservatively. Request JSON encoding failures throw
`SerializationException` with the original `JsonException` as its previous
cause.

## Laravel integration

When installed in Laravel, the service provider is auto-discovered. Publish the
configuration with:

```bash
php artisan vendor:publish --tag=didox-config
```

The configuration uses `DIDOX_BASE_URL`, `DIDOX_PARTNER_TOKEN`,
`DIDOX_USER_TOKEN`, `DIDOX_LOCALE`, and `DIDOX_TIMEOUT`.

```php
use Didox\Laravel\DidoxFacade as Didox;

Didox::documents()->find($id);
app(\Didox\Client\DidoxClient::class)->profile()->current();
```

The facade class is `Didox\Laravel\DidoxFacade`. The provider binds
`DidoxClientInterface` and resolves custom `HttpTransportInterface` and
`TokenStoreInterface` implementations from the Laravel container.

Applications can provide those integrations with the same lifecycle as the
client. For example, a transport and token store can be scoped per request or
job:

```php
$this->app->scoped(\Didox\Contracts\HttpTransportInterface::class,
    fn () => new MyHttpTransport());
$this->app->scoped(\Didox\Contracts\TokenStoreInterface::class,
    fn () => new MyRequestTokenStore());
```

The token store is identity state, not just infrastructure. Scope it to the
same request, job, or company identity as the client; do not register one
mutable store as a process-wide singleton when multiple identities can run in
the same worker.

Client resolutions are transient by default, so each request or job receives a
fresh default in-memory token store. For multiple companies, create one client
per identity with `DidoxClientFactory` and provide a separate token store for
each client:

```php
$factory = app(\Didox\Client\DidoxClientFactory::class);
$companyA = $factory->make(tokenStore: new \Didox\Support\InMemoryTokenStore());
$companyB = $factory->make(tokenStore: new \Didox\Support\InMemoryTokenStore());
```

In long-running workers, resolve or create the client inside the job lifecycle.
If an application binds a custom token store as a singleton, that application
is responsible for scoping it per request/job/company; the SDK cannot infer the
application's tenancy model. On Laravel versions without scoped bindings, keep a
factory-created client in the request/job object when multiple authenticated
calls must share one identity; do not rely on a globally cached facade client.
The core SDK remains usable without Laravel.

## Testing

```bash
composer install
composer test
composer lint
```

Tests use an in-memory fake transport and never contact Didox production.

The optional Laravel smoke test is skipped in the core environment. To run it
against a separate Laravel installation, set `DIDOX_LARAVEL_AUTOLOAD` to that
installation's `vendor/autoload.php` before running PHPUnit.

`phpstan.neon.dist` contains the PHP 8.2, level-6 analysis configuration. The
PHPStan package is not bundled in this offline workspace; install
`phpstan/phpstan` in a network-enabled development environment before running
`vendor/bin/phpstan analyse -c phpstan.neon.dist`.

The optional Laravel integration tests can be run against a compatible Laravel
installation without making Laravel a core dependency:

```powershell
$env:DIDOX_LARAVEL_AUTOLOAD = 'C:\path\to\laravel\vendor\autoload.php'
vendor\bin\phpunit tests\Unit\LaravelIntegrationTest.php --testdox
```

## Extending the SDK

Inject a custom `HttpTransportInterface` for another HTTP implementation or a
test double, and inject a custom `TokenStoreInterface` when persistence is
required. Use `DidoxClientFactory` when independent identities are needed.
Implement `SignerInterface` for an application-specific E-IMZO adapter. New
API operations should be added as services using the central `HttpClient`,
after their endpoint and payload are confirmed in Didox's documentation.

## Limitations

Didox's published documentation does not provide a stable machine-readable
OpenAPI schema. Some document payloads vary by type, and the exact arbitrary-PDF
upload encoding is not fully specified. Those payloads remain arrays at the SDK
boundary until Didox provides a definitive schema.
