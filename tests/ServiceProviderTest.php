<?php

declare(strict_types=1);

namespace Devly\DI\Tests;

use Devly\DI\Container;
use Devly\DI\Definition;
use Devly\DI\Tests\Fake\A;
use Devly\DI\Tests\Fake\B;
use Devly\DI\Tests\Fake\Provider;
use PHPUnit\Framework\TestCase;

class ServiceProviderTest extends TestCase
{
    protected Provider $serviceProvider;

    protected function setUp(): void
    {
        $this->serviceProvider = new Provider();
    }

    public function testProvides(): void
    {
        $this->assertTrue($this->serviceProvider->provides(A::class));
        $this->assertFalse($this->serviceProvider->provides(B::class));
    }

    /** @noinspection PhpUnhandledExceptionInspection */
    public function testRegister(): void
    {
        $container = new Container();
        $this->serviceProvider->register($container);

        $this->assertInstanceOf(Definition::class, $container->getDefinition(A::class));
    }
}
