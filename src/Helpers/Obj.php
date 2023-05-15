<?php

declare(strict_types=1);

namespace Devly\DI\Helpers;

use ReflectionClass;
use ReflectionException;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionParameter;

use function class_parents;
use function explode;
use function gettype;
use function is_array;
use function is_callable;
use function is_string;
use function strpos;

class Obj
{
    /**
     * @param callable|class-string<T> $definition
     *
     * @return ReflectionFunction|ReflectionMethod|ReflectionClass<T>
     *
     * @throws ReflectionException
     *
     * @template T of object
     */
    public static function createReflection($definition)
    {
        if (is_callable($definition)) {
            $definition = self::parseDefinition($definition);

            if (is_array($definition)) {
                return new ReflectionMethod(...$definition);
            }

            return new ReflectionFunction($definition);
        }

        if (is_array($definition)) {
            return new ReflectionMethod(...$definition);
        }

        return new ReflectionClass($definition);
    }

    /**
     * @param ReflectionFunction|ReflectionMethod|ReflectionClass<T> $reflection
     *
     * @return ReflectionParameter[]
     *
     * @template T of object
     */
    public static function getReflectionParameters($reflection): array
    {
        if ($reflection instanceof ReflectionClass) {
            if (! $reflection->isInstantiable()) {
                return [];
            }

            $constructor = $reflection->getConstructor();
            if (! $constructor) {
                return [];
            }

            return $constructor->getParameters();
        }

        return $reflection->getParameters();
    }

    /**
     * Gets an array of methods for the class
     *
     * @param ReflectionClass<T>|class-string<T> $class          A class name or instance of ReflectionClass.
     * @param int|null                           $filter         Filter the results to include only methods with.
     *                                                                                                                                                                 certain attributes. Defaults to no filtering.
     * @param bool                               $includeParents Whether to include the parent object methods.
     * @param bool                               $includeTraits  Whether to include the traits methods.
     *
     * @return ReflectionMethod[]
     *
     * @throws ReflectionException
     *
     * @template T of object
     */
    public static function getMethods(
        $class,
        ?int $filter = null,
        bool $includeParents = false,
        bool $includeTraits = false
    ): array {
        if (is_string($class)) {
            $class = new ReflectionClass($class);
        }

        $methods = $class->getMethods($filter);

        if (! $includeTraits) {
            foreach ($class->getTraits() as $trait) {
                $methods += $trait->getMethods($filter);
            }
        }

        if (! $includeParents) {
            return $methods;
        }

        $parents = class_parents($class->getName());

        if (empty($parents)) {
            return $methods;
        }

        foreach ($parents as $parent) {
            $rc = new ReflectionClass($parent);

            $methods += $rc->getMethods($filter);
        }

        return $methods;
    }

    /**
     * Get the type of variable
     *
     * @param mixed $value The value to be checked
     */
    public static function getType($value): string
    {
        $type = gettype($value);

        switch ($type) {
            case 'integer':
                return 'int';

            case 'boolean':
                return 'bool';

            case 'NULL':
                return 'null';

            case 'object':
                try {
                    return (new ReflectionClass($value))->getName();
                } catch (ReflectionException $e) {
                    return 'object';
                }
            case 'string':
                if (is_callable($value)) {
                    return 'callable';
                }

                return 'string';

            case 'array':
                if (is_callable($value)) {
                    return 'callable';
                }

                return 'array';

            default:
                return $type;
        }
    }

    /**
     * @internal
     *
     * @param mixed $definition
     *
     * @return array<string, string>|string|mixed
     */
    final protected static function parseDefinition($definition)
    {
        if (is_string($definition) && strpos($definition, '::') !== false) {
            $definition = explode('::', $definition);
        }

        return $definition;
    }
}
