<?php

declare(strict_types=1);

namespace Devly\DI;

use Devly\DI\Contracts\IBootableServiceProvider;
use Devly\DI\Contracts\IContainer;
use Devly\DI\Contracts\IServiceProvider;
use Devly\Repository;

use function in_array;

abstract class ServiceProvider implements IServiceProvider, IBootableServiceProvider
{
    /**
     * List of services provided by the service provider.
     *
     * @var string[]
     */
    protected array $provides = [];
    /**
     * List of named aliases to services in the container.
     *
     * @var string[]
     */
    protected array $aliases = [];

    private IContainer $app;

    public function __construct(IContainer $app)
    {
        $this->app = $app;
    }

    public function provides(string $key): bool
    {
        return in_array($key, $this->provides);
    }

    public function boot(): void
    {
    }

    public function bootDeferred(): void
    {
    }

    public function app(): IContainer
    {
        return $this->app;
    }

    /**
     * Merge Repository object or a config array into the container config
     *
     * @param Repository|array<string, mixed> $config
     */
    public function mergeConfig($config): void
    {
        $this->app()->config()->merge($config);
    }

    /** @inheritdoc */
    public function aliases(): array
    {
        return $this->aliases;
    }
}
