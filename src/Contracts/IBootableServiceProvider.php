<?php

declare(strict_types=1);

namespace Devly\DI\Contracts;

interface IBootableServiceProvider
{
    public function boot(IContainer $di): void;
}
