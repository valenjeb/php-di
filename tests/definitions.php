<?php

declare(strict_types=1);

use Devly\DI\DI;
use Devly\DI\Tests\Fake\B;
use Devly\DI\Tests\Fake\I;

return [
    I::class => DI::factory(B::class),
];
