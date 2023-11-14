<?php

declare(strict_types=1);

namespace Devly\DI\Tests\Fake;

use Devly\DI\ServiceProvider;

class Provider extends ServiceProvider
{
    /**
     * List of services provided by the service provider.
     *
     * @var string[]
     */
    public array $provides = [A::class];

    public function register(): void
    {
        $this->container->define(A::class)->setParam('text', 'foo');
    }
}
