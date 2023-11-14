<?php

declare(strict_types=1);

namespace Devly\DI\Contracts;

use Devly\DI\Exceptions\ResolverException;
use ReflectionClass;
use ReflectionFunction;
use ReflectionMethod;

interface IResolver
{
    /**
     * @param callable|class-string<T>|ReflectionClass<T>|ReflectionMethod|ReflectionFunction $definition
     * @param array<array-key, mixed>                                                         $args
     *
     * @throws ResolverException
     *
     * @template T of object
     */
    public function resolve(
        callable|string|ReflectionClass|ReflectionMethod|ReflectionFunction $definition,
        array $args = []
    ): mixed;
}
