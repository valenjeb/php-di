<?php

declare(strict_types=1);

namespace Devly\DI;

class Reference
{
    public function __construct(protected string $target)
    {
    }

    public function getTarget(): string
    {
        return $this->target;
    }
}
