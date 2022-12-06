<?php

declare(strict_types=1);

namespace Devly\DI\Tests\Fake;

use Devly\DI\Contracts\IContainer;
use Devly\DI\Contracts\IServiceProvider;

use function in_array;

class Provider implements IServiceProvider
{
    /**
     * List of services provided by the service provider.
     *
     * @var string[]
     */
    protected array $provides = [A::class];

    public function register(IContainer $di): void
    {
        $di->define(A::class)->setParam('text', 'foo');
    }

    public function provides(string $key): bool
    {
        return in_array($key, $this->provides);
    }
}
