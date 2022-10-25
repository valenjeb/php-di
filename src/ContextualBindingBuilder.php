<?php

declare(strict_types=1);

namespace Devly\DI;

use Closure;
use Devly\DI\Contracts\IContainer;

class ContextualBindingBuilder
{
    /** @var string|string[] */
    protected $concrete;
    protected IContainer $container;
    protected string $needs;

    /** @param string|string[] $concrete */
    public function __construct(IContainer $container, $concrete)
    {
        $this->container = $container;
        $this->concrete  = $concrete;
    }

    public function needs(string $abstract): self
    {
        $this->needs = $abstract;

        return $this;
    }

    /** @param Closure|string $implementation */
    public function give($implementation): void
    {
        foreach ((array) $this->concrete as $concrete) {
            $this->container->addContextualBinding($concrete, $this->needs, $implementation);
        }
    }

    /**
     * Specify the configuration item to bind as a primitive.
     *
     * @param mixed $default
     */
    public function giveConfig(string $key, $default = null): void
    {
        $this->give(static fn (Container $container) => $container->config($key, $default));
    }
}
