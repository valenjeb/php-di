<?php

declare(strict_types=1);

namespace Devly\DI\Contracts;

interface IServiceProvider
{
    /**
     * Determines whether the service provider provides the provided key name
     */
    public function provides(string $key): bool;

    /**
     * Register services provided by the service provider
     */
    public function register(): void;
}
