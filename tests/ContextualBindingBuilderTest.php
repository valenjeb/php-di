<?php

declare(strict_types=1);

namespace Devly\DI\Tests;

use Devly\DI\Container;
use Devly\DI\Tests\Fake\A;
use Devly\DI\Tests\Fake\B;
use Devly\DI\Tests\Fake\C;
use Devly\DI\Tests\Fake\I;
use PHPUnit\Framework\TestCase;

class ContextualBindingBuilderTest extends TestCase
{
    public function testGiveConfig(): void
    {
        $container = new Container([], true);
        $container->config()->set('foo', 'bar');

        $container->when(A::class)->needs('$text')->giveConfig('foo');

        $a = $container->get(A::class);

        $this->assertEquals('bar', $a->getText());
    }

    public function testGiveClassName(): void
    {
        $container = new Container([], true);

        $container->when(C::class)->needs(I::class)->give(B::class);

        $logger = $container->get(C::class);

        $this->assertInstanceOf(C::class, $logger);
    }

    public function testGiveClosure(): void
    {
        $container = new Container([], true);

        $container->when(C::class)
            ->needs(I::class)
            ->give(static fn () => new B());

        $logger = $container->get(C::class);

        $this->assertInstanceOf(C::class, $logger);
    }
}
