<?php

declare(strict_types=1);

namespace Devly\DI\Contracts;

use Devly\DI\Exceptions\ResolverException;
use Reflector;

interface IResolver
{
    /**
     * @param callable|string|Reflector $definition
     * @param array<string|int, mixed>  $args
     *
     * @return mixed
     *
     * @throws ResolverException
     */
    public function resolve($definition, array $args = []);
}
