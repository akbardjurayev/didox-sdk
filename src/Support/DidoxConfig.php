<?php

declare(strict_types=1);

namespace Didox\Support;

final readonly class DidoxConfig
{
    public const DEFAULT_LOCALE = 'ru';

    public function __construct(
        public string $baseUrl,
        public string $partnerToken,
        public ?string $userToken = null,
        public string $locale = self::DEFAULT_LOCALE,
        public int $timeout = 30,
    ) {
        if (!filter_var($this->baseUrl, FILTER_VALIDATE_URL)) {
            throw new \InvalidArgumentException('Didox base URL must be a valid URL.');
        }
        if (strtolower((string) parse_url($this->baseUrl, PHP_URL_SCHEME)) !== 'https') {
            throw new \InvalidArgumentException('Didox base URL must use HTTPS.');
        }
        if ($this->partnerToken === '') {
            throw new \InvalidArgumentException('A Didox partner token is required.');
        }
        self::validateLocale($this->locale);
        if ($this->timeout < 1) {
            throw new \InvalidArgumentException('Didox timeout must be positive.');
        }
    }

    public static function fromArray(array $config): self
    {
        return new self(
            (string) ($config['base_url'] ?? 'https://testapi3.didox.uz'),
            (string) ($config['partner_token'] ?? ''),
            isset($config['user_token']) ? (string) $config['user_token'] : null,
            (string) ($config['locale'] ?? self::DEFAULT_LOCALE),
            (int) ($config['timeout'] ?? 30),
        );
    }

    public static function validateLocale(string $locale): string
    {
        if (!in_array($locale, ['ru', 'uz'], true)) {
            throw new \InvalidArgumentException('Didox locale must be ru or uz.');
        }

        return $locale;
    }

    public function resolveLocale(?string $locale = null): string
    {
        return self::validateLocale($locale ?? $this->locale);
    }
}
