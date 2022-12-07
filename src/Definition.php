<?php

declare(strict_types=1);

namespace Devly\DI;

use Devly\DI\Contracts\IContainer;
use Devly\DI\Exceptions\DefinitionException;
use Devly\DI\Exceptions\InvalidDefinitionException;
use Devly\DI\Exceptions\ResolverException;
use Devly\DI\Helpers\Obj;
use Devly\DI\Helpers\Utils;
use ReflectionException;

use function is_int;
use function str_replace;

class Definition
{
    /**
     * Concrete definition
     *
     * @var callable|string
     */
    protected $concrete;
    /**
     * List of parameters to pass to the resolver
     *
     * @var array<string|int, mixed>
     */
    private array $parameters = [];
    /**
     * List of setup steps that run after the object is resolved
     *
     * @var array<array<string, string|mixed>>
     */
    private array $setup = [];
    /** @var array<string, string|mixed>|null */
    private ?array $return = null;
    private bool $shared   = false;

    /**
     * @param callable|class-string<T> $concrete
     * @param array<string|int, mixed> $args
     *
     * @throws InvalidDefinitionException if the #1 argument is not a callable or a fully qualified class name.
     *
     * @template T of object
     */
    public function __construct($concrete, array $args = [])
    {
        try {
            Obj::createReflection($concrete);
            $this->concrete = $concrete;

            $this->setParams($args);
        } catch (ReflectionException $e) {
            throw new InvalidDefinitionException(
                'Factory concrete definition must be a callable or a fully qualified class name.'
            );
        }
    }

    /**
     * @param array<string|int, mixed> $args
     *
     * @return mixed
     *
     * @throws DefinitionException if setup or return action is not a property name (prefixed with $)
     *                             or a method name (prefixed with @).
     * @throws ResolverException   if error occurs during resolving.
     */
    public function resolve(Container $container, array $args = [])
    {
        $object = $container->call($this->concrete, Utils::arrMergeTree($args, $this->parameters));

        if (! empty($this->setup)) {
            foreach ($this->setup as $setup) {
                $this->doSetupAction($container, $object, $setup['action'], $setup['value']);
            }
        }

        if (! empty($this->return)) {
            return $this->doReturnAction($container, $object, $this->return['action'], $this->return['value']);
        }

        return $object;
    }

    /**
     * @param string $action Property (prefixed with $) or method name (prefixed with @) to return
     * @param mixed  $value  Value to pass to the resolver if return action is method
     *
     * @return mixed|void
     *
     * @throws ResolverException   if error occurs during resolving.
     * @throws DefinitionException if return action is not a property name (prefixed with $)
     *                             or a method name (prefixed with @).
     */
    protected function doReturnAction(IContainer $container, object $object, string $action, $value = null)
    {
        if (Utils::strStartsWith($action, '$')) {
            $property = str_replace('$', '', $action);

            return $object->$property;
        }

        if (! Utils::strStartsWith($action, '@')) {
            $this->throwInvalidActionName();
        }

        $method = str_replace('@', '', $action);
        $action = [$object, $method];

        return $container->call($action, (array) $value);
    }

    /**
     * @param string|callable $action Property or method name to return
     * @param mixed           $value  Value to pass to the resolver if return action is method
     *
     * @throws ResolverException if error occurs during resolving.
     * @throws DefinitionException if action is not a property name (prefixed with $)
     *                             or a method name (prefixed with @).
     */
    private function doSetupAction(Container $container, object $object, $action, $value = null): void
    {
        if (Utils::strStartsWith($action, '$')) {
            $action          = str_replace('$', '', $action);
            $object->$action = $value;

            return;
        }

        if (! Utils::strStartsWith($action, '@')) {
            $this->throwInvalidActionName();
        }

        $action = [$object, str_replace('@', '', $action)];

        $container->call($action, (array) $value);
    }

    public function setShared(bool $shared = true): self
    {
        $this->shared = $shared;

        return $this;
    }

    /**
     * @param string|object $key   The parameter key name or an object.
     * @param mixed         $value
     */
    public function setParam($key, $value = null): self
    {
        $this->parameters[$key] = $value;

        return $this;
    }

    /** @param array<string|int, mixed> $args */
    public function setParams(array $args): self
    {
        foreach ($args as $key => $value) {
            if (is_int($key)) {
                $key   = $value;
                $value = null;
            }

            $this->setParam($key, $value);
        }

        return $this;
    }

    /**
     * @param string|callable $action
     * @param mixed           $value
     */
    public function addSetup($action, $value = []): self
    {
        $this->setup[] = [
            'action' => $action,
            'value' => $value,
        ];

        return $this;
    }

    /**
     * @param string|callable $action
     * @param mixed           $value
     */
    public function return($action, $value = []): self
    {
        $this->return = [
            'action' => $action,
            'value' => $value,
        ];

        return $this;
    }

    public function isShared(): bool
    {
        return $this->shared;
    }

    protected function throwInvalidActionName(): void
    {
        throw new DefinitionException(
            'The 3# parameter ($action) value must be a property (prefixed with $) or method name (prefixed with @)'
        );
    }
}
