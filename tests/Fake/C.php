<?php

declare(strict_types=1);

namespace Devly\DI\Tests\Fake;

class C
{
    public I $b;

    public function __construct(I $b)
    {
        $this->b = $b;
    }
}
