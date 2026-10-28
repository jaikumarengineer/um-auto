<?php

namespace Modules\Quality\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;
use Modules\Quality\Console\SendDailyQualityDigest;
use Modules\Quality\Support\QualityAlertCatalog;
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
        $time = '18:00';

        try {
            $time = QualityAlertCatalog::systemPreferences()['send_time'];
        } catch (\Throwable) {
            $time = '18:00';
        }

        $schedule->command('quality:digest daily')->dailyAt($time);
        $schedule->command('quality:digest weekly')->weeklyOn(Carbon::SUNDAY, $time);
        $schedule->command('quality:digest monthly')->lastDayOfMonth($time);
    }
}
