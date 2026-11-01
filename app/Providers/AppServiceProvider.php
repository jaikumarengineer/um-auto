<?php

namespace App\Providers;

use App\Models\User;
use App\Repositories\SettingRepository;
use App\Repositories\SettingRepositoryInterface;
use App\Services\SettingService;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(SettingRepositoryInterface::class, SettingRepository::class);

        $this->app->singleton(SettingService::class, function ($app) {
            return new SettingService($app->make(SettingRepositoryInterface::class));
        });

        $this->app->singleton('setting', function ($app) {
            return $app->make(SettingService::class);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::before(function ($user, string $ability) {
            return $user instanceof User && $user->isUnrestrictedAdmin() ? true : null;
        });

        Blade::if('modulecan', function (string $module, string $action = 'access') {
            return module_can($module, $action);
        });

        $this->applyMailSettings();
    }

    /**
     * Prefer Settings → Mail when those values are stored.
     */
    private function applyMailSettings(): void
    {
        try {
            $mail = $this->app->make(SettingService::class)->getGroup('mail');
        } catch (\Throwable) {
            return;
        }

        if ($mail === []) {
            return;
        }

        $mailer = trim((string) ($mail['mailer'] ?? ''));
        $host = trim((string) ($mail['host'] ?? ''));
        $port = trim((string) ($mail['port'] ?? ''));
        $username = (string) ($mail['username'] ?? '');
        $password = (string) ($mail['password'] ?? '');
        $encryption = trim((string) ($mail['encryption'] ?? ''));
        $fromAddress = trim((string) ($mail['from_address'] ?? ''));
        $fromName = trim((string) ($mail['from_name'] ?? ''));

        $config = [];

        if ($mailer !== '') {
            $config['mail.default'] = $mailer;
        }

        if ($host !== '') {
            $config['mail.mailers.smtp.host'] = $host;
        }

        if ($port !== '') {
            $config['mail.mailers.smtp.port'] = (int) $port;
        }

        if ($username !== '') {
            $config['mail.mailers.smtp.username'] = $username;
        }

        if ($password !== '') {
            $config['mail.mailers.smtp.password'] = $password;
        }

        if ($encryption === '' || $encryption === 'null') {
            $config['mail.mailers.smtp.encryption'] = null;
            $config['mail.mailers.smtp.scheme'] = null;
        } elseif (in_array($encryption, ['tls', 'ssl'], true)) {
            $config['mail.mailers.smtp.encryption'] = $encryption;
            $config['mail.mailers.smtp.scheme'] = $encryption === 'ssl' ? 'smtps' : 'smtp';
        }

        if ($fromAddress !== '') {
            $config['mail.from.address'] = $fromAddress;
        }

        if ($fromName !== '') {
            $config['mail.from.name'] = $fromName;
        }

        if ($config !== []) {
            config($config);
        }
    }
}
