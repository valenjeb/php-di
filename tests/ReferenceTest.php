<?php

declare(strict_types=1);

namespace Devly\DI\Tests;

use Devly\DI\Reference;
use PHPUnit\Framework\TestCase;

class ReferenceTest extends TestCase
{
    public function testCreateReferenceObjectAndGetTarget(): void
    {
        $ref = new Reference('foo');

        $this->assertEquals('foo', $ref->getTarget());
    }
}
