<?php

declare(strict_types=1);

namespace Devly\DI\Tests;

use Devly\DI\Container;
use Devly\DI\Exceptions\ResolverException;
use Devly\DI\Resolver;
use Devly\DI\Tests\Fake\A;
use PHPUnit\Framework\TestCase;

class ResolverTest extends TestCase
{
    protected Resolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new Resolver(new Container());
    }

    public function testResolveClosure(): void
    {
        $callback = static fn () => 'John Doe';

        $this->assertSame('John Doe', $this->resolver->resolve($callback));
    }

    public function testResolveObject(): void
    {
        $this->assertInstanceOf(A::class, $this->resolver->resolve(A::class));
    }

    public function testResolveStaticMethodWithStringDefinition(): void
    {
        $this->assertEquals(
            'foo',
            $this->resolver->resolve(A::class . '::getTextStatic', ['text' => 'foo'])
        );
    }

    public function testResolveStaticMethodWithArrayDefinition(): void
    {
        $this->assertEquals(
            'bar',
            $this->resolver->resolve([A::class, 'getTextStatic'], ['text' => 'bar'])
        );
    }

    public function testResolveWithParams(): void
    {
        $callback = static fn (string $first, string $last) => $first . ' ' . $last;

        $this->assertSame('John Doe', $this->resolver->resolve($callback, ['first' => 'John', 'last' => 'Doe']));
    }

    public function testResolveThrowsResolverExceptionIfMissingParam(): void
    {
        $this->expectException(ResolverException::class);

        $callback = static fn (string $name) => $name;
        $this->resolver->resolve($callback);
    }

    public function testResolveThrowsResolverExceptionIfWrongParamType(): void
    {
        $this->expectException(ResolverException::class);

        $callback = static fn (string $name) => $name;
        $this->resolver->resolve($callback, ['name' => 0]);
    }

    public function testResolveThrowsResolverExceptionIfInvalidClassNameOrCallback(): void
    {
        $this->expectException(ResolverException::class);
        $this->expectExceptionMessage('Class "Fake" does not exist');

        $this->resolver->resolve('Fake'); // @phpstan-ignore-line
    }
}
