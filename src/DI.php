<?php

declare(strict_types=1);

namespace Devly\DI;

class DI
{
    /**
     * @param callable|string          $definition
     * @param array<string|int, mixed> $parameters
     *
     * @throws Exceptions\DefinitionError
     */
    public static function factory($definition, array $parameters = []): Definition
    {
        return new Definition($definition, $parameters);
    }

    public static function get(string $key): Reference
    {
        return new Reference($key);
    }
}
