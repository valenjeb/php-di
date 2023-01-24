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
    protected IContainer $di;

    public function __construct(IContainer $di)
    {
        $this->di = $di;
    }

    public function register(): void
    {
        $this->di->define(A::class)->setParam('text', 'foo');
    }

    public function provides(string $key): bool
    {
        return in_array($key, $this->provides);
    }
}
