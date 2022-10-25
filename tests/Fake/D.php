<?php

declare(strict_types=1);

namespace Devly\DI\Tests\Fake;

class D
{
    public static function getText(string $text): string
    {
        return $text;
    }
}
