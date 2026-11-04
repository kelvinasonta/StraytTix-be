<?php

namespace Modules\Audit\app\Providers;

use Modules\Audit\app\Services\AuditService;
use Nwidart\Modules\Support\ModuleServiceProvider;

class AuditServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Audit';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'audit';

    /**
     * Command classes to register.
     *
     * @var string[]
     */
    // protected array $commands = [];

    /**
     * Provider classes to register.
     *
     * @var string[]
     */
    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        parent::register();

        $this->app->singleton(AuditService::class);
    }
}
