<?php

declare(strict_types=1);

namespace Devly\DI\Contracts;

interface IServiceProvider
{
    /**
     * Initializes the service provider
     *
     * The init method is called immediately after the service provider
     * is registered on the service provider the container.
     */
    public function init(IContainer $app): void;

    /**
     * Determines whether the service provider provides the provided key name
     */
    public function provides(string $key): bool;

    /**
     * Register services provided by the service provider
     */
    public function register(): void;
}
