<?php

declare(strict_types=1);

namespace Devly\DI\Contracts;

interface IServiceProvider
{
    /**
     * Determines whether the service provider provides the provided key name
     */
    public function provides(string $key): bool;

    public function register(IContainer $di): void;
}
