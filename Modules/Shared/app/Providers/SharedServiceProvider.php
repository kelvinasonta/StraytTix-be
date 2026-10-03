<?php

namespace Modules\Shared\app\Providers;

use Modules\Shared\app\Contracts\ImageUploadServiceInterface;
use Modules\Shared\app\Services\ImageUploadService;
use Modules\Shared\app\Services\Security\ImageProcessor;
use Modules\Shared\app\Services\Security\ImageSecurityScanner;
use Nwidart\Modules\Support\ModuleServiceProvider;

class SharedServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Shared';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'shared';

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

        $this->app->singleton(ImageSecurityScanner::class);
        $this->app->singleton(ImageProcessor::class);
        $this->app->singleton(ImageUploadServiceInterface::class, ImageUploadService::class);
        $this->app->singleton(ImageUploadService::class);
    }
}
