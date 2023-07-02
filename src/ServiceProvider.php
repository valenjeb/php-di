<?php

declare(strict_types=1);

namespace Devly\DI;

use Devly\DI\Contracts\IContainer;
use Devly\DI\Contracts\IServiceProvider;

use function in_array;

abstract class ServiceProvider implements IServiceProvider
{
    /**
     * List of services provided by the provider
     *
     * @var string[]
     */
    public array $provides = [];
    protected IContainer $container;

    public function provides(string $key): bool
    {
        return in_array($key, $this->provides);
    }

    public function injectContainer(IContainer $app): void
    {
        $this->container = $app;
    }
}
