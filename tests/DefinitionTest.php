<?php

declare(strict_types=1);

namespace Devly\DI\Tests;

use Devly\DI\Container;
use Devly\DI\Definition;
use Devly\DI\Exceptions\DefinitionError;
use Devly\DI\Tests\Fake\A;
use PHPUnit\Framework\TestCase;

class DefinitionTest extends TestCase
{
    /** @noinspection PhpUnhandledExceptionInspection */
    public function testCreateDefinitionWithClassName(): void
    {
        $factory = new Definition(A::class);
        $object  = $factory->resolve(new Container());

        $this->assertInstanceOf(A::class, $object);
    }

    public function testCreateDefinitionThrowsDefinitionException(): void
    {
        $this->expectException(DefinitionError::class);
        $this->expectExceptionMessage(
            'Factory concrete definition must be a callable or a fully qualified class name.'
        );

        $factory = new Definition('Fake'); // @phpstan-ignore-line
    }

    /** @noinspection PhpUnhandledExceptionInspection */
    public function testCreateObjectDefinitionWithSetupSteps(): void
    {
        $container = new Container();
        $factory   = (new Definition(A::class))->addSetup('$text', 'foo');
        $object    = $factory->resolve($container);

        $this->assertEquals('foo', $object->getText());

        $factory2 = (new Definition(A::class))->addSetup('@setText', ['text' => 'foo']);
        $object2  = $factory2->resolve($container);

        $this->assertEquals('foo', $object2->getText());
    }

    /** @noinspection PhpUnhandledExceptionInspection */
    public function testCreateObjectDefinitionWithReturnPropertyStatement(): void
    {
        $factory = new Definition(A::class);
        $factory->setParam('text', 'foo')->return('$text');

        $object = $factory->resolve(new Container());

        $this->assertEquals('foo', $object);
    }

    /** @noinspection PhpUnhandledExceptionInspection */
    public function testCreateObjectDefinitionWithReturnMethodStatement(): void
    {
        $factory = new Definition(A::class);
        $factory->setParam('text', 'foo')->return('@getText');

        $resolved = $factory->resolve(new Container());

        $this->assertEquals('foo', $resolved);
    }

    /** @noinspection PhpUnhandledExceptionInspection */
    public function testReturnStatementThrowsDefinitionExceptionIfInvalidActionName(): void
    {
        $this->expectException(DefinitionError::class);
        $this->expectExceptionMessage(
            'The 3# parameter ($action) value must be a property (prefixed with $) or method name (prefixed with @)'
        );

        $factory = (new Definition(A::class))->return('text');
        $factory->resolve(new Container());
    }

    /** @noinspection PhpUnhandledExceptionInspection */
    public function testCreateStaticMethodDefinition(): void
    {
        $factory = (new Definition(A::class . '::getTextStatic'))->setParam('text', 'foo');
        $object  = $factory->resolve(new Container());

        $this->assertEquals('foo', $object);
    }

    /** @noinspection PhpUnhandledExceptionInspection */
    public function testCreateClosureDefinition(): void
    {
        $factory = new Definition(static fn ($text) => $text, ['text' => 'foo']);
        $object  = $factory->resolve(new Container());

        $this->assertEquals('foo', $object);
    }
}
