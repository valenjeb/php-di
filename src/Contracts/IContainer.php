<?php

declare(strict_types=1);

namespace Devly\DI\Contracts;

use Closure;
use Devly\DI\ContextualBindingBuilder;
use Devly\DI\Definition;
use Devly\DI\Exceptions\AliasNotFoundException;
use Devly\DI\Exceptions\ContainerException;
use Devly\DI\Exceptions\DefinitionNotFoundException;
use Devly\DI\Exceptions\InvalidDefinitionException;
use Devly\DI\Exceptions\NotFoundException;
use Devly\DI\Exceptions\OverwriteExistingServiceException;
use Devly\DI\Exceptions\ResolverException;
use Devly\Repository;

/**
 * Dependency Injection Container Contract
 */
interface IContainer
{
    /** @param string|array<string, Closure|Definition|Factory> $definitions */
    public function addDefinitions(IContainer|array|string $definitions): self;

    /**
     * Set the container services to be shared by default
     */
    public function sharedByDefault(bool $shared = true): self;

    /**
     * Enable autowiring
     */
    public function autowire(bool $enable = true): self;

    /**
     * Store an instance of a service in the container
     */
    public function instance(string $key, mixed $value): void;

    /**
     * Define an object or a value in the container.
     *
     * @param Definition|Factory|callable|class-string<T>|null $value
     *
     * @throws OverwriteExistingServiceException If an item with the given key already exists in the container.
     * @throws InvalidDefinitionException        If provided value is not a callable or a fully qualified class name.
     *
     * @template T of object
     */
    public function define(string $key, mixed $value = null): Definition;

    /**
     * Define or override an object or a value in the container.
     *
     * @param Definition|Factory|callable|class-string<T>|null $value
     *
     * @throws InvalidDefinitionException If provided value is not a callable or a fully qualified class name.
     *
     * @template T of object
     */
    public function override(string $key, mixed $value = null): Definition;

    /**
     * Add a shared factory definition.
     *
     * @param Definition|Factory|callable|class-string<T>|null $value
     *
     * @throws OverwriteExistingServiceException If an item with the given key already exists in the container.
     * @throws InvalidDefinitionException        If provided value is not a callable or a fully qualified class name.
     *
     * @template T of object
     */
    public function defineShared(string $key, mixed $value = null): Definition;

    /**
     * Add a shared factory definition.
     *
     * @param Definition|Factory|callable|class-string<T>|null $value
     *
     * @throws InvalidDefinitionException If provided value is not a callable or a fully qualified class name.
     *
     * @template T of object
     */
    public function overrideShared(string $key, mixed $value = null): Definition;

    /**
     * Extend existing factory definition.
     *
     * @throws NotFoundException If definition not found in the container.
     */
    public function extend(string $key): Definition;

    /**
     * Retrieve an object or a value from the container.
     *
     * The get() method will resolve an object only once and return the same instance
     * of the object every time it'll be called.
     *
     * If the provided id name not exists in the container, and is a valid class name,
     * the get method will try to resolve the class definition automatically and store
     * it in the container.
     *
     * @throws NotFoundException If definition not found in the container, and it is
     *                           could not be resolved automatically.
     * @throws ResolverException if error occurs during resolving.
     */
    public function get(string $key): mixed;

    /**
     * Retrieve an object or a value from the container or return default value if not found
     *
     * @throws ResolverException if error occurred during resolve operation.
     */
    public function getSafe(string $key, mixed $default = null): mixed;

    /**
     * Resolve an item in the container.
     *
     * This method will always resolve the provided item and return a new instance of the item.
     * If the provided id name not exists in the container, and is a valid class name, the get method will
     * try to resolve the class definition automatically and store it in the container.
     *
     * @param string $key The service name to resolve.
     *
     * @throws NotFoundException If definition not found in the container and it is could not be resolved automatically.
     * @throws ResolverException if error occurred during resolve operation.
     */
    public function make(string $key): mixed;

    /**
     * Resolve an item in the container with a list of args
     *
     * @param string               $key  The service name to resolve.
     * @param array<string, mixed> $args List of args to pass to the resolver.
     *
     * @throws NotFoundException If definition not found in the container and it is could not be resolved automatically.
     * @throws ResolverException if error occurred during resolve operation.
     */
    public function makeWith(string $key, array $args): mixed;

    /**
     * Add a named alias to a service in the container
     *
     * @param string $name   The alias name
     * @param string $target The name of the aliased service
     */
    public function alias(string $name, string $target): void;

    /**
     * Registers a service provider.
     *
     * @param IServiceProvider|IBootableProvider|object|class-string $provider
     *
     * @throws ContainerException if the service provider does not implement one of
     *                            IServiceProvider or IBootableProvider interface
     *                            and don't have an init method.
     */
    public function registerServiceProvider(mixed $provider): void;

    /**
     * Run boot() method for each bootable service provider set in the container.
     */
    public function bootServices(): void;

    /**
     * Bind another container as a definition source
     *
     * Bound containers will be searched for definitions when a definition
     * can't be found in the container or its service providers.
     */
    public function bindContainer(IContainer $container): self;

    /**
     * Checks wetter an item exists in the container.
     *
     * @param string $key Identifier of the entry to look for.
     */
    public function has(string $key): bool;

    /**
     * Resolve a callable or an object using the container
     *
     * @param callable|class-string    $callbackOrClassName
     * @param array<string|int, mixed> $args
     *
     * @throws ResolverException
     */
    public function call(callable|string $callbackOrClassName, array $args = []): mixed;

    /**
     * Drop a service definition and its instance from the container
     *
     * @param string $name The name of the service to remove.
     *
     * @throws NotFoundException if service name does not exist in the container.
     */
    public function forget(string $name): void;

    /**
     * Register a callback that will run before a service is resolved
     */
    public function beforeResolving(string $key, callable $callback): void;

    /**
     * Register a callback that will run after a service is resolved
     */
    public function afterResolving(string $key, callable $callback): void;

    /**
     * Add contextual bindings
     *
     * Useful when two classes that utilize the same interface, but you wish to inject
     * different implementations into each class.
     */
    public function addContextualBinding(string $concrete, string $needs, Closure|string $implementation): void;

    /**
     * Retrieves contextual bindings for the provided abstract name
     *
     * @return array<string|int, mixed>
     */
    public function getContextualBindings(string $abstract): array;

    /**
     * Checks whether a resolved instance of a provided key name exists in the container.
     */
    public function resolved(string $key): bool;

    /**
     * Retrieves the target service for the provided key
     *
     * @throws AliasNotFoundException if alias name does not exist.
     */
    public function getAlias(string $key): string;

    /**
     * Determines whether the provided key is an alias
     */
    public function isAlias(string $key): bool;

    /**
     * Add contextual bindings
     *
     * @param string|string[] $concrete
     */
    public function when(string|array $concrete): ContextualBindingBuilder;

    /**
     * @param string|null $key     Key name to retrieve. If null, returns the
     *                             underlying config object (Devly\Repository).
     * @param mixed       $default Default value to return if the provided key not found
     *
     * @return ($key is string ? mixed : Repository)
     */
    public function config(string|null $key = null, mixed $default = null): mixed;

    /**
     * Get a service factory definition
     *
     * @throws DefinitionNotFoundException if definition does not exist in the container.
     */
    public function getDefinition(string $name): Definition;

    /**
     * Checks whether a definition of a provided key name exists in the container.
     */
    public function hasDefinition(string $key): bool;

    /**
     * Export list of all container definitions
     *
     * @return Definition[]
     */
    public function export(): array;
}
