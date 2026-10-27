<?php

declare(strict_types=1);

namespace Didox\Support;

final class Path
{
    public static function segment(int|string $value): string
    {
        return rawurlencode((string) $value);
    }
}
