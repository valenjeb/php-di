<?php

declare(strict_types=1);

namespace Devly\DI\Tests\Fake;

class C
{
    public function __construct(public I $b)
    {
    }
}
