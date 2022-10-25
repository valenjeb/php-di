<?php

declare(strict_types=1);

namespace Devly\DI\Tests;

use Devly\DI\Container;
use Devly\DI\Contracts\IContainer;
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
        $this->serviceProvider = new Provider(new Container());
    }

    public function testProvides(): void
    {
        $this->assertTrue($this->serviceProvider->provides(A::class));
        $this->assertFalse($this->serviceProvider->provides(B::class));
    }

    public function testGetContainerInstance(): void
    {
        $this->assertInstanceOf(IContainer::class, $this->serviceProvider->app());
    }

    /** @noinspection PhpUnhandledExceptionInspection */
    public function testRegister(): void
    {
        $this->serviceProvider->register();

        $this->assertInstanceOf(Definition::class, $this->serviceProvider->app()->getDefinition(A::class));
    }

    public function testMergeConfig(): void
    {
        $this->serviceProvider->mergeConfig(['foo' => 'bar']);

        $this->assertEquals('bar', $this->serviceProvider->app()->config()->get('foo'));
    }
}
