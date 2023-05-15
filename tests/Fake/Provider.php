<?php

declare(strict_types=1);

namespace Devly\DI\Tests\Fake;

use Devly\DI\Contracts\IContainer;
use Devly\DI\ServiceProvider;

class Provider extends ServiceProvider
{
    /**
     * List of services provided by the service provider.
     *
     * @var string[]
     */
    public array $provides = [A::class];
    protected IContainer $di;

    public function __construct(IContainer $di)
    {
        $this->di = $di;
    }

    public function register(): void
    {
        $this->di->define(A::class)->setParam('text', 'foo');
    }
}
