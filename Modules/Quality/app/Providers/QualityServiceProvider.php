<?php

namespace Modules\Quality\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Modules\Quality\Console\SendDailyQualityDigest;
use Nwidart\Modules\Support\ModuleServiceProvider;

class QualityServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Quality';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'quality';

    /**
     * Command classes to register.
     *
     * @var string[]
     */
    protected array $commands = [
        SendDailyQualityDigest::class,
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
        $schedule->command('quality:daily-digest')->dailyAt('18:00');
    }
}
