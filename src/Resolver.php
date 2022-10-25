<?php

declare(strict_types=1);

namespace Devly\DI;

use Closure;
use Devly\DI\Contracts\IContainer;
use Devly\DI\Contracts\IResolver;
use Devly\DI\Exceptions\FailedResolveParameter;
use Devly\DI\Exceptions\ResolverError;
use Devly\DI\Helpers\Obj;
use ReflectionClass;
use ReflectionException;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use Reflector;
use Throwable;

use function array_filter;
use function array_key_exists;
use function class_exists;
use function class_implements;
use function class_parents;
use function explode;
use function get_class;
use function in_array;
use function is_callable;
use function is_string;
use function sprintf;
use function strpos;

class Resolver implements IResolver
{
    protected IContainer $container;

    public function __construct(IContainer $container)
    {
        $this->container = $container;
    }

    /**
     * @param callable|class-string<T>|ReflectionClass<T>|ReflectionMethod|ReflectionFunction $definition
     * @param array<string|int, mixed>                                                        $args
     *
     * @return mixed
     *
     * @throws ResolverError
     * @throws ReflectionException
     *
     * @template T of object
     */
    public function resolve($definition, array $args = [])
    {
        $reflection = $this->getReflection($definition);

        if ($reflection instanceof ReflectionClass) {
            return $this->resolveClassDefinition($reflection, $args);
        }

        if ($reflection instanceof ReflectionMethod) {
            if (is_string($definition)) {
                $definition = explode('::', $definition);
            }

            $obj = $definition[0];

            return $this->resolveMethodDefinition($reflection, $obj, $args);
        }

        if ($reflection instanceof ReflectionFunction) {
            return $this->resolveFunctionDefinition($reflection, $args);
        }

        // @phpstan-ignore-next-line
        $message = 'The first %s::resolver() parameter can be an instance of ReflectionClass, ReflectionMethod'
                 . ' or ReflectionFunction. Provided %s';

        throw new ResolverError(sprintf($message, self::class, get_class($reflection)));
    }

    /**
     * @param ReflectionClass<T>       $class
     * @param array<string|int, mixed> $args
     *
     * @return object Resolved object
     *
     * @throws ResolverError
     * @throws ReflectionException
     *
     * @template T of object
     */
    protected function resolveClassDefinition(ReflectionClass $class, array $args = []): object
    {
        if (! $class->isInstantiable()) {
            throw new ResolverError(sprintf('Class "%s" is not instantiable.', $class->getName()));
        }

        $construct = $class->getConstructor();

        if (! $construct) {
            try {
                $object = $class->newInstance();
            } catch (ReflectionException $e) {
                throw new ResolverError(sprintf(
                    'Class %s could not be instantiated: %s.',
                    $class->name,
                    $e->getMessage()
                ));
            }
        } else {
            try {
                $resolveParameters = $this->resolveParameters($construct->getParameters(), $args);
            } catch (FailedResolveParameter $e) {
                throw new ResolverError($e->getMessage(), $e->getCode(), $e);
            }

            try {
                $object = $class->newInstanceArgs($resolveParameters);
            } catch (ReflectionException $e) {
                throw new ResolverError(sprintf(
                    '%s can not be instantiated because its __construct() method is not public.',
                    $class->name
                ), $e->getCode(), $e);
            }
        }

        $this->resolveInjectors($class, $object, $args);

        return $object;
    }

    /**
     * @param string|object|null       $object
     * @param array<string|int, mixed> $args
     *
     * @return mixed
     *
     * @throws ResolverError
     */
    private function resolveMethodDefinition(ReflectionMethod $method, $object = null, array $args = [])
    {
        if ($method->isStatic()) {
            $object = null;
        } else {
            if (is_string($object)) {
                try {
                    $class  = new ReflectionClass($object);
                    $object = $this->resolveClassDefinition($class, []);
                } catch (Throwable $e) {
                    throw new ResolverError(sprintf(
                        "Method %s::%s() could not be invoked because it's declaring class could not be instantiated.",
                        $method->getDeclaringClass()->getName(),
                        $method->getName()
                    ), 0, $e);
                }
            }
        }

        try {
            $args = $this->resolveParameters($method->getParameters(), $args);
        } catch (FailedResolveParameter $e) {
            throw new ResolverError($e->getMessage());
        }

        try {
            return $method->invokeArgs($object, $args);
        } catch (ReflectionException $e) {
            throw new ResolverError(sprintf(
                'Method %s::%s() could not be invoked.',
                $method->getDeclaringClass()->getName(),
                $method->getName()
            ), 0, $e);
        }
    }

    /**
     * @param ReflectionParameter[] $params
     * @param array<string, mixed>  $args
     *
     * @return array<int, mixed>
     *
     * @throws FailedResolveParameter
     */
    private function resolveParameters(array $params, array $args = []): array
    {
        $resolved = [];
        foreach ($params as $param) {
            try {
                $resolved[] = $this->resolveParameter($param, $args);
            } catch (FailedResolveParameter | ReflectionException $e) {
                $class   = $param->getDeclaringClass();
                $method  = $class ? $class->getName() . '::' : '';
                $method .= $param->getDeclaringFunction()->getName();
                $pos     = sprintf('#%d', $param->getPosition() + 1);

                throw new FailedResolveParameter(sprintf(
                    'The %s %s() %s',
                    $pos,
                    $method,
                    $e->getMessage()
                ), $e->getCode(), $e);
            }
        }

        return $resolved;
    }

    /**
     * @param array<string, mixed> $args
     *
     * @return mixed
     *
     * @throws FailedResolveParameter
     * @throws ReflectionException
     */
    private function resolveParameter(ReflectionParameter $parameter, array $args = [])
    {
        $reflectionNamedType = $parameter->getType();

        if (isset($args[$parameter->getName()])) {
            return $this->checkProvidedValue($parameter, $reflectionNamedType, $args[$parameter->getName()]);
        }

        if ($reflectionNamedType && ! $reflectionNamedType->isBuiltin()) {
            if (isset($args[$reflectionNamedType->getName()])) {
                return $this->checkProvidedValue(
                    $parameter,
                    $reflectionNamedType,
                    $args[$reflectionNamedType->getName()]
                );
            }
        }

        $b = $this->container->getContextualBindings($parameter->getDeclaringClass()->getName());

        if (array_key_exists('$' . $parameter->getName(), $b)) {
            return $this->checkProvidedValue($parameter, $reflectionNamedType, $b['$' . $parameter->getName()]);
        }

        if ($reflectionNamedType && array_key_exists($reflectionNamedType->getName(), $b)) {
            return $this->checkProvidedValue($parameter, $reflectionNamedType, $b[$reflectionNamedType->getName()]);
        }

        $error = null;
        if ($reflectionNamedType && ! $reflectionNamedType->isBuiltin()) {
            $class = new ReflectionClass($reflectionNamedType->getName());

            try {
                return $this->container->get($class->getName());
            } catch (Throwable $e) {
                $error = $e;
            }
        }

        if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }

        if ($parameter->allowsNull()) {
            return null;
        }

        if (! $reflectionNamedType->isBuiltin()) {
            throw new FailedResolveParameter(sprintf(
                'Parameter $%s (type: %s) could not be resolved automatically, ' .
                'it is not allowing null and no default value provided.',
                $parameter->getName(),
                $reflectionNamedType->getName()
            ), 0, $error);
        }

        throw new FailedResolveParameter(sprintf(
            'Parameter $%s (type: %s) is not allowing null and no default value provided.',
            $parameter->getName(),
            $reflectionNamedType->getName()
        ), 0, $error);
    }

    /**
     * @param array<string, mixed> $args
     *
     * @return mixed
     *
     * @throws ResolverError
     */
    private function resolveFunctionDefinition(ReflectionFunction $function, array $args = [])
    {
        try {
            $args = $this->resolveParameters($function->getParameters(), $args);
        } catch (FailedResolveParameter $e) {
            throw new ResolverError($e->getMessage());
        }

        return $function->invokeArgs($args);
    }

    /**
     * @param mixed $value
     *
     * @return mixed
     *
     * @throws FailedResolveParameter
     */
    private function checkProvidedValue(ReflectionParameter $param, ?ReflectionNamedType $namedType, $value)
    {
        if ($value instanceof Reference) {
            try {
                $value = $this->container->get($value->getTarget());
            } catch (Throwable $e) {
                $value = null;
            }
        }

        if (! $namedType) {
            return $value;
        }

        $valueType = Obj::getType($value);

        if ($namedType->getName() === $valueType || $namedType->getName() === 'callable' && is_callable($value)) {
            return $value;
        }

        if (! $namedType->isBuiltin() && class_exists($valueType)) {
            if (
                in_array($namedType->getName(), class_implements($valueType))
                || in_array($namedType->getName(), class_parents($valueType))
            ) {
                return $value;
            }
        }

        throw new FailedResolveParameter(sprintf(
            'Parameter $%s expects %s. Provided %s.',
            $param->name,
            $namedType->isBuiltin() ? $namedType->getName() : 'an instance of ' . $namedType->getName(),
            $valueType
        ));
    }

    /**
     * @param class-string<T>|callable-string|Closure|Reflector $definition
     *
     * @return ReflectionClass<T>|ReflectionFunction|ReflectionMethod
     *
     * @throws ResolverError if provided definition is invalid.
     *
     * @template T of object
     */
    protected function getReflection($definition)
    {
        try {
            if (
                $definition instanceof ReflectionClass
                || $definition instanceof ReflectionMethod
                || $definition instanceof ReflectionFunction
            ) {
                return $definition;
            }

            return Obj::createReflection($definition);
        } catch (ReflectionException $e) {
            throw new ResolverError($e->getMessage());
        }
    }

    /**
     * @param ReflectionClass<T>       $reflectionClass
     * @param object                   $object          The object to be resolved
     * @param array<string|int, mixed> $args
     *
     * @throws ReflectionException
     * @throws ResolverError
     *
     * @template T of object
     */
    protected function resolveInjectors(ReflectionClass $reflectionClass, object $object, array $args): void
    {
        $injects = array_filter(
            Obj::getMethods($reflectionClass, ReflectionMethod::IS_PUBLIC, true, true),
            static function ($method) {
                return strpos($method->getName(), 'inject') === 0 && ! $method->isAbstract();
            }
        );

        foreach ($injects as $injector) {
            $this->resolve([$object, $injector->getName()], $args);
        }
    }
}
