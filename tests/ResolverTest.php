<?php

declare(strict_types=1);

namespace Devly\DI\Tests;

use Devly\DI\Container;
use Devly\DI\Exceptions\ResolverError;
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

    /** @noinspection PhpUnhandledExceptionInspection */
    public function testResolveClosure(): void
    {
        $callback = static fn () => 'John Doe';

        $this->assertSame('John Doe', $this->resolver->resolve($callback));
    }

    /** @noinspection PhpUnhandledExceptionInspection */
    public function testResolveObject(): void
    {
        $this->assertInstanceOf(A::class, $this->resolver->resolve(A::class));
    }

    /** @noinspection PhpUnhandledExceptionInspection */
    public function testResolveStaticMethodWithStringDefinition(): void
    {
        $this->assertEquals(
            'foo',
            $this->resolver->resolve(A::class . '::getTextStatic', ['text' => 'foo'])
        );
    }

    /** @noinspection PhpUnhandledExceptionInspection */
    public function testResolveStaticMethodWithArrayDefinition(): void
    {
        $this->assertEquals(
            'bar',
            $this->resolver->resolve([A::class, 'getTextStatic'], ['text' => 'bar'])
        );
    }

    /** @noinspection PhpUnhandledExceptionInspection */
    public function testResolveWithParams(): void
    {
        $callback = static fn (string $first, string $last) => $first . ' ' . $last;

        $this->assertSame('John Doe', $this->resolver->resolve($callback, ['first' => 'John', 'last' => 'Doe']));
    }

    /** @noinspection PhpUnhandledExceptionInspection */
    public function testResolveThrowsResolverExceptionIfMissingParam(): void
    {
        $this->expectException(ResolverError::class);
        $this->expectErrorMessage(
            'Parameter $name (type: string) is not allowing null and no default value provided.'
        );

        $callback = static fn (string $name) => $name;
        $this->resolver->resolve($callback);
    }

    /** @noinspection PhpUnhandledExceptionInspection */
    public function testResolveThrowsResolverExceptionIfWrongParamType(): void
    {
        $this->expectException(ResolverError::class);
        $this->expectErrorMessage('Parameter $name expects string. Provided int.');

        $callback = static fn (string $name) => $name;
        $this->resolver->resolve($callback, ['name' => 0]);
    }

    /** @noinspection PhpUnhandledExceptionInspection */
    public function testResolveThrowsResolverExceptionIfInvalidClassNameOrCallback(): void
    {
        $this->expectException(ResolverError::class);
        $this->expectErrorMessage('Class Fake does not exist');

        $this->resolver->resolve('Fake'); // @phpstan-ignore-line
    }
}
