<?php

declare(strict_types=1);

namespace Devly\DI;

use Closure;
use Devly\DI\Contracts\IContainer;

class ContextualBindingBuilder
{
    protected string $needs;

    /** @param string|string[] $concrete */
    public function __construct(protected IContainer $container, protected array|string $concrete)
    {
    }

    public function needs(string $abstract): self
    {
        $this->needs = $abstract;

        return $this;
    }

    public function give(Closure|string $implementation): void
    {
        foreach ((array) $this->concrete as $concrete) {
            $this->container->addContextualBinding($concrete, $this->needs, $implementation);
        }
    }

    /**
     * Specify the configuration item to bind as a primitive.
     */
    public function giveConfig(string $key, mixed $default = null): void
    {
        $this->give(static fn (Container $container) => $container->config($key, $default));
    }
}
