<?php

declare(strict_types=1);

namespace Devly\DI;

use Devly\DI\Exceptions\InvalidDefinitionException;

class DI
{
    /**
     * @param callable|class-string<T> $definition
     * @param array<string|int, mixed> $parameters
     *
     * @throws InvalidDefinitionException
     *
     * @template T of object
     */
    public static function factory(callable|string $definition, array $parameters = []): Definition
    {
        return new Definition($definition, $parameters);
    }

    /**
     * Create a reference to a service in the container.
     */
    public static function get(string $key): Reference
    {
        return new Reference($key);
    }
}
