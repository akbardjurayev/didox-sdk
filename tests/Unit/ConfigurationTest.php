<?php

declare(strict_types=1);

namespace {
    if (!function_exists('env')) {
        function env(string $key, mixed $default = null): mixed
        {
            return $default;
        }
    }
}

namespace Didox\Tests\Unit {

use PHPUnit\Framework\TestCase;

final class ConfigurationTest extends TestCase
{
    public function testConfigurationFileProducesNoOutput(): void
    {
        ob_start();
        $config = require dirname(__DIR__, 2) . '/config/didox.php';
        $output = ob_get_clean();

        self::assertSame('', $output);
        self::assertSame('https://testapi3.didox.uz', $config['base_url']);
    }
}
}
