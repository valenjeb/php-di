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
    protected Container $container;

    protected function setUp(): void
    {
        $this->container       = new Container();
        $this->serviceProvider = new Provider();
    }

    public function testProvides(): void
    {
        $this->assertTrue($this->serviceProvider->provides(A::class));
        $this->assertFalse($this->serviceProvider->provides(B::class));
    }

    public function testRegister(): void
    {
        $this->serviceProvider->register($this->container);

        $this->assertInstanceOf(Definition::class, $this->container->getDefinition(A::class));
    }
}
