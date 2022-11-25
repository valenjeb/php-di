<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Devly\DI\Tests;

use Devly\DI\Container;
use Devly\DI\Contracts\IBootableServiceProvider;
use Devly\DI\Contracts\IContainer;
use Devly\DI\Definition;
use Devly\DI\Exceptions\AliasNotFoundError;
use Devly\DI\Exceptions\ContainerError;
use Devly\DI\Exceptions\DefinitionError;
use Devly\DI\Exceptions\DefinitionNotFoundError;
use Devly\DI\Exceptions\NotFoundError;
use Devly\DI\Tests\Fake\A;
use Devly\DI\Tests\Fake\B;
use Devly\DI\Tests\Fake\C;
use Devly\DI\Tests\Fake\D;
use Devly\DI\Tests\Fake\I;
use Devly\DI\Tests\Fake\Provider;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use stdClass;

class ContainerTest extends TestCase
{
    protected Container $container;

    protected function setUp(): void
    {
        parent::setUp();

        $this->container = new Container();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        unset($this->container);
    }

    public function testDefineService(): void
    {
        $definition = $this->container->define('foo', static fn () => '');

        $this->assertInstanceOf(Definition::class, $definition);
    }

    public function testGetServiceDefinition(): void
    {
        $definition = $this->container->define('foo', static fn () => '');

        $this->assertSame($definition, $this->container->getDefinition('foo'));
    }

    public function testGetDefinitionThrowsNotFoundException(): void
    {
        $this->expectException(NotFoundError::class);

        $this->assertInstanceOf(Definition::class, $this->container->getDefinition('foo'));
    }

    public function testDefineServiceThrowsContainerExceptionIfDefinitionExists(): void
    {
        $this->expectException(ContainerError::class);
        $this->expectErrorMessage(
            'Key "foo" already defined in this container. Use the extend() method' .
            ' to extend its definition or override() to replace the existing definition.'
        );

        $this->container->define('foo', static fn () => '');
        $this->container->define('foo', static fn () => '');
    }

    public function testDefineServiceThrowsDefinitionExceptionIfIsNotCallableOrClassName(): void
    {
        $this->expectException(DefinitionError::class);
        $this->expectErrorMessage(
            'Factory concrete definition must be a callable or a fully qualified class name.'
        );

        $this->container->define('foo', 'bar');
    }

    public function testGetService(): void
    {
        $this->container->define('stdClass', static fn () => new stdClass());
        $resolved = $this->container->get('stdClass');

        $this->assertInstanceOf(stdClass::class, $resolved);
        $this->assertNotSame($resolved, $this->container->get('stdClass'));
    }

    public function testGetServiceThrowsNotFoundException(): void
    {
        $this->expectException(NotFoundError::class);
        $this->expectExceptionMessage('Key "stdClass" is not found in this container.');

        $this->container->get('stdClass');
    }

    public function testMakeService(): void
    {
        $this->container->define('stdClass', static fn () => new stdClass());
        $resolved = $this->container->make('stdClass');

        $this->assertInstanceOf(stdClass::class, $resolved);
        $this->assertNotSame($resolved, $this->container->make('stdClass'));
    }

    /** @noinspection PhpUnhandledExceptionInspection */
    public function testMakeThrowsNotFoundException(): void
    {
        $this->expectException(DefinitionNotFoundError::class);

        $this->container->make('stdClass');
    }

    public function testMakeWith(): void
    {
        $this->container->define('foo', static fn (string $text) => $text);
        $resolved = $this->container->makeWith('foo', ['text' => 'bar']);

        $this->assertEquals('bar', $resolved);
    }

    public function testMakeWithThrowsNotFoundException(): void
    {
        $this->expectException(DefinitionNotFoundError::class);

        $this->container->makeWith('stdClass', []);
    }

    public function testDefineSharedService(): void
    {
        $factory = $this->container->defineShared('stdClass', static fn () => new stdClass());
        $this->assertTrue($factory->isShared());
        $resolved = $this->container->get('stdClass');

        $this->assertSame($resolved, $this->container->get('stdClass'));
    }

    public function testDefineSharedThrowsDefinitionExceptionIfIsNotCallableOrClassName(): void
    {
        $this->expectException(DefinitionError::class);
        $this->expectErrorMessage(
            'Factory concrete definition must be a callable or a fully qualified class name.'
        );

        $this->container->defineShared('foo', 'bar');
    }

    public function testDefineSharedThrowsContainerExceptionIfDefinitionExists(): void
    {
        $this->expectException(ContainerError::class);
        $this->expectErrorMessage(
            'Key "foo" already defined in this container. Use the extend() method' .
            ' to extend its definition or override() to replace the existing definition.'
        );

        $this->container->defineShared('foo', static fn () => '');
        $this->container->defineShared('foo', static fn () => '');
    }

    public function testOverrideService(): void
    {
        $this->container->defineShared('stdClass', static fn () => (object) []);
        $resolved      = $this->container->get('stdClass');
        $resolved->foo = 'bar';

        $this->assertEquals('bar', $this->container->get('stdClass')->foo);

        $this->container->override('stdClass', static fn () => new stdClass());

        $this->expectError();
        $this->expectErrorMessage('Undefined property: stdClass::$foo');

        /** @noinspection PhpUnusedLocalVariableInspection */
        $foo = $this->container->get('stdClass')->foo;
    }

    public function testOverrideThrowsDefinitionExceptionIfIsNotCallableOrClassName(): void
    {
        $this->expectException(DefinitionError::class);
        $this->expectErrorMessage(
            'Factory concrete definition must be a callable or a fully qualified class name.'
        );

        $this->container->override('foo', 'bar');
    }

    public function testForgetService(): void
    {
        $this->container->define('foo', static fn () => 'bar');
        $this->container->forget('foo');
        $this->assertFalse($this->container->has('foo'));
    }

    public function testForgetServiceThrowsNotFoundException(): void
    {
        $this->expectException(NotFoundError::class);

        $this->container->forget('foo');
    }

    public function testSharedByDefaultEnabled(): void
    {
        $this->container->sharedByDefault();

        $this->container->define(A::class);

        $resolved = $this->container->get(A::class);

        $this->assertSame($resolved, $this->container->get(A::class));
    }

    public function testAutowiringEnabled(): void
    {
        $this->container->autowire();

        $this->assertInstanceOf(A::class, $this->container->get(A::class));
    }

    public function testRunCallbackBeforeResolvingService(): void
    {
        $this->container->define(A::class);
        $this->container->config()->set('foo', 'bar');

        $this->container->beforeResolving(A::class, static function (Definition $def, IContainer $c): void {
            $def->setParam('text', $c->config('foo'));
        });

        $std = $this->container->get(A::class);
        $this->assertEquals('bar', $std->getText());
    }

    public function testRunCallbackAfterResolvingService(): void
    {
        $this->container->define(A::class);

        $this->container->afterResolving(A::class, static fn ($obj) => $obj->setText('baz'));

        $std = $this->container->get(A::class);

        $this->assertEquals('baz', $std->getText());
    }

    public function testAddContextualBindings(): void
    {
        $this->container->define(B::class);
        $this->container->define(C::class);

        $this->container->when(C::class)->needs(I::class)->give(B::class);

        $this->assertInstanceOf(C::class, $this->container->get(C::class));
    }

    public function testAddPrimitiveBindings(): void
    {
        $this->container->define(A::class);

        $this->container->when(A::class)->needs('$text')->give('foo');

        $this->assertEquals('foo', $this->container->get(A::class)->getText());
    }

    public function testAddPrimitiveBindingsWithConfigValue(): void
    {
        $this->container->config()->set('foo', 'bar');
        $this->container->define(A::class);

        $this->container->when(A::class)->needs('$text')->giveConfig('foo');

        $this->assertEquals('bar', $this->container->get(A::class)->getText());
    }

    public function testResolveObjectUsingCallMethod(): void
    {
        $resolved = $this->container->call(A::class);

        $this->assertInstanceOf(A::class, $resolved);
    }

    public function testResolveCallableUsingCallMethod(): void
    {
        $resolved = $this->container->call([D::class, 'getText'], ['text' => 'foo']);

        $this->assertEquals('foo', $resolved);
    }

    public function testBindContainer(): void
    {
        $container = new Container();
        $container->define(A::class);

        $this->container->bindContainer($container);

        $this->assertSame($container->getDefinition(A::class), $this->container->getDefinition(A::class));
    }

    public function testResolveServiceUsingBoundContainer(): void
    {
        $container = new Container();
        $container->define(A::class)->setParam('text', 'foo');

        $this->container->bindContainer($container);

        $this->assertEquals('foo', $this->container->get(A::class)->getText());
    }

    public function testAddAndGetNamedAlias(): void
    {
        $this->container->define(A::class);

        $this->container->alias('a', A::class);

        $this->assertTrue($this->container->isAlias('a'));
        $this->assertEquals(A::class, $this->container->getAlias('a'));
    }

    public function testResolveServiceUsingAliasName(): void
    {
        $this->container->define(A::class);
        $this->container->alias('a', A::class);

        $this->assertInstanceOf(A::class, $this->container->get('a'));
    }

    public function testGetAliasThrowsAliasNotFoundException(): void
    {
        $this->expectException(AliasNotFoundError::class);
        $this->container->getAlias('a');
    }

    public function testRegisterServiceProvider(): void
    {
        $this->container->registerServiceProvider(new Provider($this->container));

        $this->assertTrue($this->container->serviceProviderExists(Provider::class));
    }

    public function testRegisterServiceProviderThrowsInvalidArgumentException(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->container->registerServiceProvider(new stdClass()); // @phpstan-ignore-line
    }

    public function testRegisterServiceProviderWithClassImplementsBootableServiceProviderShouldPassTypeCheck(): void
    {
        $provider = new class implements IBootableServiceProvider {
            public function boot(): void
            {
            }

            public function bootDeferred(): void
            {
            }
        };

        $this->container->registerServiceProvider($provider);

        $this->assertTrue(true);
    }

    public function testResolveObjectDefinedByServiceProvider(): void
    {
        $this->container->registerServiceProvider(new Provider($this->container));

        $this->assertEquals('foo', $this->container->get(A::class)->getText());
    }

    public function testExtendServiceDefinition(): void
    {
        $this->container->define(A::class);

        $this->container->extend(A::class)->setParam('text', 'foo');

        $this->assertEquals('foo', $this->container->get(A::class)->getText());
    }

    public function testExtendThrowsNotFoundException(): void
    {
        $this->expectException(NotFoundError::class);

        $this->container->extend(A::class)->setParam('text', 'foo');
    }
}
