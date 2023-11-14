<?php

declare(strict_types=1);

namespace Devly\DI\Contracts;

interface IServiceProvider
{
    /**
     * Determines whether the service provider provides the provided key name
     *
     * @param string|null $key Name of service to check or null to check whether
     *                         the provider is empty.
     */
    public function provides(string|null $key = null): bool;

    /**
     * Register services provided by the service provider
     */
    public function register(): void;
}
