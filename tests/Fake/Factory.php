<?php

declare(strict_types=1);

namespace Devly\DI\Tests\Fake;

use Devly\DI\Contracts\Factory as FactoryContract;

class Factory implements FactoryContract
{
    public function create(): A
    {
        return new A('foo');
    }
}
