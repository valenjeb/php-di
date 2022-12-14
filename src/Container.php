<?php

declare(strict_types=1);

namespace Devly\DI;

use ArrayAccess;
use Closure;
use Devly\DI\Contracts\IBootableServiceProvider;
use Devly\DI\Contracts\IConfigProvider;
use Devly\DI\Contracts\IContainer;
use Devly\DI\Contracts\IDeferredServiceProvider;
use Devly\DI\Contracts\IResolver;
use Devly\DI\Contracts\IServiceProvider;
use Devly\DI\Exceptions\AliasNotFoundException;
use Devly\DI\Exceptions\ContainerException;
use Devly\DI\Exceptions\DefinitionException;
use Devly\DI\Exceptions\DefinitionNotFoundException;
use Devly\DI\Exceptions\InvalidDefinitionException;
use Devly\DI\Exceptions\NotFoundException;
use Devly\DI\Exceptions\OverwriteExistingServiceException;
use Devly\DI\Exceptions\ResolverException;
use Devly\DI\Helpers\Utils;
use Devly\Repository;
use ReflectionClass;
use ReflectionException;

use function array_key_exists;
use function class_exists;
use function func_get_args;
use function get_class;
use function is_array;
use function is_string;
use function sprintf;

/**
 * Dependency Injection Container
 *
 * @implements ArrayAccess<string, mixed>
 */
class Container implements IContainer, ArrayAccess
{
    /**
     * List of bound containers
     *
     * @var IContainer[]
     */
    private array $containers = [];
    /**
     * Whether the configured service providers booted
     */
    private bool $servicesBooted = false;
    private Repository $config;
    private IResolver $resolver;
    private bool $autowiringEnabled;
    private bool $sharedByDefault;
    /** @var array<string, Definition> */
    private array $definitions = [];
    /**
     * Resolved services
     *
     * @var array<string, mixed>
     */
    private array $instances = [];
    /**
     * Named aliases to services in the container
     *
     * @var array<string, string>
     */
    private array $aliases = [];
    /**
     * List of services and callbacks which run before service resolved
     *
     * @var array<string, callable>
     */
    private array $beforeResolve = [];
    /**
     * List of services and callbacks which run after service resolved
     *
     * @var array<string, callable>
     */
    private array $afterResolve = [];
    /** @var IServiceProvider[] */
    private array $registrableProviders = [];
    /** @var IBootableServiceProvider[] */
    protected array $bootableServices = [];
    /** @var IDeferredServiceProvider[] */
    protected array $deferredServices = [];
    /** @var array<string, true> */
    private array $skippedProviders = [];
    /**
     * The abstract binding map.
     *
     * @var array<string, array<string, mixed>>
     */
    private array $abstractBindings = [];
    /**
     * The contextual binding map.
     *
     * @var array<string, array<string, mixed>>
     */
    private array $contextual = [];

    /**
     * @param IContainer|array<string, mixed> $items             List of servo service definitions.
     * @param bool                            $autowiringEnabled Whether to services should be autowired
     *                                                           automatically
     *                                                           when not defined
     * @param bool                            $shared            Whether the container services should be
     *                                                           shared by default
     */
    public function __construct($items = [], bool $autowiringEnabled = false, bool $shared = false)
    {
        $this->autowiringEnabled = $autowiringEnabled;
        $this->sharedByDefault   = $shared;
        $this->resolver          = new Resolver($this);
        $this->config            = new Repository();

        $items = $items instanceof IContainer ? $items->export() : $items;

        foreach ($items as $name => $definition) {
            $this->define($name, $definition);
        }

        $this->instance(static::class, $this);

        if (static::class !== self::class) {
            $this->alias(self::class, static::class);
        }

        $this->alias(IContainer::class, static::class);
    }

    /**
     * Set the container services to be shared by default
     */
    public function sharedByDefault(bool $shared = true): self
    {
        $this->sharedByDefault = $shared;

        return $this;
    }

    /**
     * Enable autowiring
     */
    public function autowire(bool $enable = true): self
    {
        $this->autowiringEnabled = $enable;

        return $this;
    }

    /**
     * Checks wetter an item exists in the container.
     *
     * @param string $key Identifier of the entry to look for.
     */
    public function has(string $key): bool
    {
        if ($this->resolved($key)) {
            return true;
        }

        return $this->hasDefinition($key);
    }

    /**
     * Store an instance of a service in the container
     *
     * @param mixed $value
     */
    public function instance(string $key, $value): void
    {
        $this->instances[$key] = $value;
    }

    /**
     * Define an object or a value in the container.
     *
     * @param Definition|callable|string|null $value
     *
     * @throws OverwriteExistingServiceException If an item with the given key already exists in the container.
     * @throws InvalidDefinitionException        If provided value is not a callable or a fully qualified class name.
     */
    public function define(string $key, $value = null): Definition
    {
        if ($this->has($key)) {
            throw new OverwriteExistingServiceException(sprintf(
                'Key "%s" already defined in this container. Use the extend() method' .
                ' to extend its definition or override() to replace the existing definition.',
                $key
            ));
        }

        $factory = $value instanceof Definition ? $value : new Definition($value ?? $key);

        $this->definitions[$key] = $factory;

        return $factory;
    }

    /**
     * Add a shared factory definition.
     *
     * @param Definition|callable|string|null $value
     *
     * @throws OverwriteExistingServiceException If an item with the given key already exists in the container.
     * @throws InvalidDefinitionException        If provided value is not a callable or a fully qualified class name.
     */
    public function defineShared(string $key, $value = null): Definition
    {
        return $this->define($key, $value)->setShared();
    }

    /**
     * Define or override an object or a value in the container.
     *
     * @param mixed $value
     *
     * @throws InvalidDefinitionException If provided value is not a callable or a fully qualified class name.
     */
    public function override(string $key, $value = null): Definition
    {
        $factory = $value instanceof Definition ? $value : new Definition($value ?? $key);

        $this->definitions[$key] = $factory;

        if ($this->resolved($key)) {
            unset($this->instances[$key]);
        }

        return $factory;
    }

    /**
     * Add a shared factory definition.
     *
     * @param Definition|callable|string|null $value
     *
     * @throws OverwriteExistingServiceException If an item with the given key already exists in the container.
     * @throws InvalidDefinitionException        If provided value is not a callable or a fully qualified class name.
     */
    public function overrideShared(string $key, $value = null): Definition
    {
        return $this->override($key, $value)->setShared();
    }

    /**
     * Extend existing factory definition.
     *
     * @throws NotFoundException If definition not found in the container.
     */
    public function extend(string $key): Definition
    {
        if (! $this->has($key)) {
            throw new NotFoundException(sprintf(
                'Key "%s" can not be extended because it is not defined in this container.',
                $key
            ));
        }

        return $this->definitions[$key];
    }

    /**
     * Add a named alias to a service in the container
     */
    public function alias(string $name, string $target): void
    {
        $this->aliases[$name] = $target;
    }

    /**
     * Retrieve an object or a value from the container.
     *
     * The get() method will resolve an object only once and return the same instance of the object every
     * time it'll be called.
     * If the provided id name not exists in the container, and is a valid class name, the get method will
     * try to resolve the class definition automatically and store it in the container.
     *
     * @return mixed
     *
     * @throws NotFoundException If definition not found in the container, and it could
     *                           not be resolved automatically.
     * @throws ResolverException if error occurs during resolving.
     */
    public function get(string $key)
    {
        if (! $this->has($key)) {
            try {
                $key = $this->getAlias($key);

                return $this->get($key);
            } catch (AliasNotFoundException $e) {
            }

            if (! $this->findDefinition($key)) {
                if (! $this->autowiringEnabled) {
                    throw new NotFoundException(sprintf('Key "%s" is not found in this container.', $key));
                }

                try {
                    $this->define($key);
                } catch (DefinitionException $e) {
                    throw new NotFoundException(sprintf(
                        'Key "%s" is not found in this container and could not be' .
                        ' defined automatically using autowiring.',
                        $key
                    ), 0, $e);
                }
            }
        }

        try {
            $definition = $this->getDefinition($key);
            if (! $definition->isShared() && ! $this->sharedByDefault) {
                return $this->resolve($key, $definition);
            }

            if (! $this->resolved($key)) {
                $resolved = $this->resolve($key, $definition);

                $this->instance($key, $resolved);
            }
        } catch (DefinitionNotFoundException $e) {
        }

        return $this->instances[$key];
    }

    /**
     * Retrieve an object or a value from the container or return default value if not found
     *
     * @param mixed $default
     *
     * @return mixed|null
     *
     * @throws ResolverException if error occurred during resolve operation.
     */
    public function getSafe(string $key, $default = null)
    {
        try {
            return $this->get($key);
        } catch (NotFoundException $e) {
            return $default;
        }
    }

    /**
     * Resolve an item in the container.
     *
     * This method will always resolve the provided item and return a new instance of the item.
     * If the provided id name not exists in the container, and is a valid class name, the get method will
     * try to resolve the class definition automatically and store it in the container.
     *
     * @param string $key The service name to resolve.
     *
     * @return mixed
     *
     * @throws NotFoundException if no definition found for the provided key.
     * @throws ResolverException if error occurred during resolve operation.
     */
    public function make(string $key)
    {
        return $this->makeWith($key, []);
    }

    /**
     * Resolve an item in the container with a list of args
     *
     * @param array<string|int, mixed> $args
     *
     * @return mixed
     *
     * @throws NotFoundException if no definition found for the provided key.
     * @throws ResolverException if error occurred during resolve operation.
     */
    public function makeWith(string $key, array $args)
    {
        if (! $this->hasDefinition($key)) {
            try {
                $key = $this->getAlias($key);

                return $this->makeWith($key, $args);
            } catch (AliasNotFoundException $e) {
            }

            if (! $this->findDefinition($key)) {
                if (! $this->autowiringEnabled) {
                    throw new NotFoundException(sprintf('No definition found for key "%s".', $key));
                }

                try {
                    $this->define($key);
                } catch (DefinitionException $e) {
                    throw new NotFoundException(sprintf(
                        'No definition found for key "%s" and it could not be defined automatically using autowiring.',
                        $key
                    ), 0, $e);
                }
            }
        }

        $definition = $this->getDefinition($key);

        return $this->resolve($key, $definition, $args);
    }

    /**
     * Resolve a callable or an object using the container
     *
     * @param callable|string          $callbackOrClassName
     * @param array<string|int, mixed> $args
     *
     * @return mixed
     *
     * @throws ResolverException if error occurred during resolving.
     */
    public function call($callbackOrClassName, array $args = [])
    {
        return $this->getResolver()->resolve($callbackOrClassName, $args);
    }

    /**
     * Execute resolve definition.
     *
     * @param array<string|int, mixed> $args
     *
     * @return mixed
     *
     * @throws ResolverException   if error occurs during resolving.
     * @throws DefinitionException if setup or return action is set, and is not
     *                             a property name (prefixed with $) or a method
     *                             name (prefixed with @).
     */
    protected function resolve(string $key, Definition $definition, array $args = [])
    {
        if (isset($this->beforeResolve[$key])) {
            Utils::invokeBatch((array) $this->beforeResolve[$key], $definition, $this);
        }

        $instance = $definition->resolve($this, $args);

        if (isset($this->afterResolve[$key])) {
            Utils::invokeBatch((array) $this->afterResolve[$key], $instance, $this);
        }

        return $instance;
    }

    /**
     * Retrieves contextual bindings for the provided abstract name
     *
     * @return array<string|int, mixed>
     */
    public function getContextualBindings(string $abstract): array
    {
        if (array_key_exists($abstract, $this->abstractBindings)) {
            return $this->abstractBindings[$abstract];
        }

        $bindings = $this->findContextualBinding($abstract);
        $resolved = [];

        if ($bindings) {
            foreach ($bindings as $binding => $value) {
                try {
                    if (is_string($value) && class_exists($value)) {
                        $value = $this->get($value);
                    } else {
                        $value = $this->call($value);
                    }
                } catch (ResolverException $e) {
                }

                $resolved[$binding] = $value;
            }
        }

        return $this->abstractBindings[$abstract] = $resolved;
    }

    /**
     * Drop a service definition and its instance from the container
     *
     * @throws NotFoundException if service name does not exist in the container.
     */
    public function forget(string $name): void
    {
        if (! $this->has($name)) {
            throw new NotFoundException(sprintf('Service "%s" does not exist in the container.', $name));
        }

        if (isset($this->definitions[$name])) {
            unset($this->definitions[$name]);
        }

        if (! isset($this->instances[$name])) {
            return;
        }

        unset($this->instances[$name]);
    }

    /**
     * Register a callback that will run before a service is resolved
     */
    public function beforeResolving(string $key, callable $callback): void
    {
        $this->beforeResolve[$key][] = $callback;
    }

    /**
     * Register a callback that will run after a service is resolved
     */
    public function afterResolving(string $key, callable $callback): void
    {
        $this->afterResolve[$key][] = $callback;
    }

    /**
     * Checks whether a resolved instance of a provided key name exists in the container.
     */
    public function resolved(string $key): bool
    {
        return array_key_exists($key, $this->instances);
    }

    /**
     * Checks whether a definition of a provided key name exists in the container.
     */
    public function hasDefinition(string $key): bool
    {
        return array_key_exists($key, $this->definitions);
    }

    public function getResolver(): IResolver
    {
        return $this->resolver;
    }

    /**
     * Registers a service provider
     *
     * @see registerServiceProvider
     *
     * @param IServiceProvider|IBootableServiceProvider|IDeferredServiceProvider|IConfigProvider $provider
     */
    public function register($provider): void
    {
        $this->registerServiceProvider($provider);
    }

    /**
     * Registers a service provider
     *
     * @param IServiceProvider|IBootableServiceProvider|IDeferredServiceProvider|IConfigProvider $provider
     */
    public function registerServiceProvider($provider): void
    {
        if ($provider instanceof IConfigProvider) {
            $provider->provideConfig($this);
        }

        if ($provider instanceof IServiceProvider) {
            $this->registrableProviders[get_class($provider)] = $provider;
        }

        if ($provider instanceof IBootableServiceProvider) {
            $this->bootableServices[] = $provider;
        }

        if (! $provider instanceof IDeferredServiceProvider) {
            return;
        }

        $this->deferredServices[] = $provider;
    }

    public function serviceProviderExists(string $provider): bool
    {
        return array_key_exists($provider, $this->registrableProviders);
    }

    /**
     * Boot service providers
     *
     * Run boot() & bootDeferred() methods for each bootable service provider set in the container.
     *
     * @throws ContainerException
     */
    public function bootServices(): void
    {
        if ($this->servicesBooted) {
            throw new ContainerException('Services are already booted');
        }

        foreach ($this->bootableServices as $provider) {
            $provider->boot($this);
        }

        foreach ($this->deferredServices as $provider) {
            $provider->bootDeferred($this);
        }

        $this->servicesBooted = true;
    }

    public function bindContainer(IContainer $container): self
    {
        $this->containers[] = $container;

        return $this;
    }

    /**
     * Retrieves the target service for the provided key
     *
     * @throws AliasNotFoundException if alias name does not exist.
     */
    public function getAlias(string $key): string
    {
        if (! $this->isAlias($key)) {
            throw new AliasNotFoundException(sprintf('Alias name "%s" does not exist.', $key));
        }

        return $this->aliases[$key];
    }

    /**
     * Determines whether the provided key is an alias
     */
    public function isAlias(string $key): bool
    {
        return array_key_exists($key, $this->aliases);
    }

    /**
     * Add contextual bindings
     *
     * @param string|string[] $concrete
     */
    public function when($concrete): ContextualBindingBuilder
    {
        $concrete = is_array($concrete) ? $concrete : func_get_args();

        return new ContextualBindingBuilder($this, $concrete);
    }

    /**
     * Add contextual bindings
     *
     * Useful when two classes that utilize the same interface, but you wish to inject
     * different implementations into each class.
     *
     * @param Closure|string $implementation
     */
    public function addContextualBinding(string $concrete, string $needs, $implementation): void
    {
        try {
            $needs = $this->getAlias($needs);
        } catch (AliasNotFoundException $e) {
        }

        $this->contextual[$concrete][$needs] = $implementation;
    }

    /**
     * Find contextual bindings for the provided abstract
     *
     * @return mixed
     */
    public function findContextualBinding(string $abstract)
    {
        if (isset($this->contextual[$abstract])) {
            return $this->contextual[$abstract];
        }

        try {
            $rc = new ReflectionClass($abstract);
        } catch (ReflectionException $e) {
            throw new ContainerException($e->getMessage());
        }

        $parent = $rc->getParentClass();
        if ($parent) {
            $concrete = $this->findContextualBinding($parent->getName());
            if ($concrete) {
                return $concrete;
            }
        }

        foreach ($rc->getInterfaceNames() as $interfaceName) {
            $concrete = $this->findContextualBinding($interfaceName);
            if ($concrete) {
                return $concrete;
            }
        }

        return null;
    }

    /**
     * @param string|null $key     Key name to retrieve. If null, returns the underlying
     *                             config object (Devly\Repository).
     * @param mixed       $default Default value to return if the provided key not found
     *
     * @return Repository|mixed
     */
    public function config(?string $key = null, $default = null)
    {
        if (! $key) {
            return $this->config;
        }

        return $this->config->get($key, $default);
    }

    /**
     * Get a service factory definition
     *
     * @throws DefinitionNotFoundException if definition does not exist in the container.
     */
    public function getDefinition(string $name): Definition
    {
        if (! $this->hasDefinition($name) && ! $this->findDefinition($name)) {
            throw new DefinitionNotFoundException('Service definition ' . $name . ' does not exist in the container.');
        }

        return $this->definitions[$name];
    }

    protected function findDefinition(string $key): bool
    {
        $found = false;
        foreach ($this->registrableProviders as $provider) {
            $className = get_class($provider);

            if (isset($this->skippedProviders[$className]) || ! $provider instanceof IServiceProvider) {
                continue;
            }

            if (! $provider->provides($key)) {
                continue;
            }

            $provider->register($this);

            $this->skippedProviders[$className] = true;

            $found = true;
        }

        if ($found === true) {
            return true;
        }

        foreach ($this->containers as $container) {
            try {
                $definition = $container->getDefinition($key);

                $this->define($key, $definition);

                return true;
            } catch (DefinitionNotFoundException $e) {
            }
        }

        return false;
    }

    /**
     * --------------------------------------------
     * ArrayAccess Interface Implementation
     * --------------------------------------------
     * phpcs:disable SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingNativeTypeHint
     */

    /**
     * Determines whether an offset exists
     *
     * @param string $offset
     */
    public function offsetExists($offset): bool
    {
        return $this->has($offset);
    }

    /**
     * Retrieve an object or a value from the container
     *
     * @param string $offset
     *
     * @return mixed
     */
    public function offsetGet($offset)
    {
        return $this->get($offset);
    }

    /**
     * Define an object or a value in the container
     *
     * @param string $offset
     * @param mixed  $value
     */
    public function offsetSet($offset, $value): void
    {
        $this->define($offset, $value);
    }

    /**
     * Drops a service definition and its instance from the container
     *
     * @param string $offset
     */
    public function offsetUnset($offset): void
    {
        $this->forget($offset);
    }

    /** @return Definition[] */
    public function export(): array
    {
        return $this->definitions;
    }
}
