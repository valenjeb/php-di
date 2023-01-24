<?php

declare(strict_types=1);

namespace Devly\DI\Contracts;

interface IConfigProvider
{
    public function provideConfig(): void;
}
