<?php

namespace Modules\Credits\App\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Modules\Credits\app\Console\Commands\ReapExpiredReservationsCommand;
use Modules\Credits\app\Console\Commands\ReconcileCreditWalletsCommand;
use Nwidart\Modules\Support\ModuleServiceProvider;

class CreditsServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Credits';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'credits';

    /**
     * Command classes to register.
     *
     * @var string[]
     */
    protected array $commands = [
        ReapExpiredReservationsCommand::class,
        ReconcileCreditWalletsCommand::class,
    ];

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
     * Define module schedules.
     */
    protected function configureSchedules(Schedule $schedule): void
    {
        $schedule->command('credits:reap-reservations')->everyFiveMinutes();
        $schedule->command('credits:reconcile')->dailyAt('02:00');
    }
}
