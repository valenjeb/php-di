<?php

declare(strict_types=1);

namespace Devly\DI\Contracts;

use Devly\DI\Exceptions\ResolverError;
use Reflector;

interface IResolver
{
    /**
     * @param callable|string|Reflector $definition
     * @param array<string|int, mixed>  $args
     *
     * @return mixed
     *
     * @throws ResolverError
     */
    public function resolve($definition, array $args = []);
}
