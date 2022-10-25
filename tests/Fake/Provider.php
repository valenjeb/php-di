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
    protected array $provides = [A::class];

    public function register(): void
    {
        $this->app()->define(A::class)->setParam('text', 'foo');
    }
}
