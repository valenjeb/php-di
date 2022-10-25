<?php

declare(strict_types=1);

namespace Devly\DI\Contracts;

interface IBootableServiceProvider
{
    public function boot(): void;

    public function bootDeferred(): void;
}
