<?php

declare(strict_types=1);

namespace Devly\DI;

class Reference
{
    protected string $target;

    public function __construct(string $target)
    {
        $this->target = $target;
    }

    public function getTarget(): string
    {
        return $this->target;
    }
}
