<?php

declare(strict_types=1);

namespace Devly\DI\Contracts;

interface IDeferredServiceProvider
{
    public function bootDeferred(): void;
}
