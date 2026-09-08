<?php

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('core::layouts.app')] class extends Component {
    /** @var array<string, mixed> */
    public array $data = [];

    public function mount(): void
    {
        $this->data = [
            'software' => [
                'os' => php_uname('s').' '.php_uname('r'),
                'hostname' => php_uname('n'),
                'arch' => php_uname('m'),
                'webserver' => $_SERVER['SERVER_SOFTWARE'] ?? php_sapi_name(),
                'php' => PHP_VERSION,
                'laravel' => app()->version(),
                'timezone' => config('app.timezone'),
                'env' => config('app.env'),
                'debug' => config('app.debug') ? 'On' : 'Off',
            ],
            'hardware' => [
                'disk_total' => $this->formatBytes(@disk_total_space(base_path()) ?: 0),
                'disk_free' => $this->formatBytes(@disk_free_space(base_path()) ?: 0),
                'memory_limit' => ini_get('memory_limit') ?: 'Unknown',
                'upload_max' => ini_get('upload_max_filesize') ?: 'Unknown',
                'post_max' => ini_get('post_max_size') ?: 'Unknown',
                'max_execution' => (ini_get('max_execution_time') ?: '0').'s',
            ],
            'database' => [
                'driver' => DB::getDriverName(),
                'version' => $this->databaseVersion(),
                'connection' => config('database.default'),
            ],
            'cache' => [
                'default' => config('cache.default'),
                'queue' => config('queue.default'),
                'session' => config('session.driver'),
            ],
        ];
    }

    protected function databaseVersion(): string
    {
        try {
            return (string) DB::connection()->getPdo()->getAttribute(\PDO::ATTR_SERVER_VERSION);
        } catch (\Throwable) {
            return 'Unavailable';
        }
    }

    protected function formatBytes(int|float $bytes, int $precision = 2): string
    {
        $bytes = max(0, (float) $bytes);
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $pow = $bytes > 0 ? (int) floor(log($bytes, 1024)) : 0;
        $pow = min($pow, count($units) - 1);

        return round($bytes / (1024 ** $pow), $precision).' '.$units[$pow];
    }
}; ?>

@php
    $cards = [
        [
            'title' => __('Software'),
            'rows' => [
                [__('OS'), Arr::get($data, 'software.os')],
                [__('Hostname'), Arr::get($data, 'software.hostname')],
                [__('Architecture'), Arr::get($data, 'software.arch')],
                [__('Web server'), Arr::get($data, 'software.webserver')],
                [__('PHP'), Arr::get($data, 'software.php')],
                [__('Laravel'), Arr::get($data, 'software.laravel')],
                [__('Environment'), Arr::get($data, 'software.env')],
                [__('Debug'), Arr::get($data, 'software.debug')],
                [__('Timezone'), Arr::get($data, 'software.timezone')],
            ],
        ],
        [
            'title' => __('Runtime / disk'),
            'rows' => [
                [__('Disk total'), Arr::get($data, 'hardware.disk_total')],
                [__('Disk free'), Arr::get($data, 'hardware.disk_free')],
                [__('Memory limit'), Arr::get($data, 'hardware.memory_limit')],
                [__('Upload max'), Arr::get($data, 'hardware.upload_max')],
                [__('Post max'), Arr::get($data, 'hardware.post_max')],
                [__('Max execution'), Arr::get($data, 'hardware.max_execution')],
            ],
        ],
        [
            'title' => __('Database & drivers'),
            'rows' => [
                [__('Connection'), Arr::get($data, 'database.connection')],
                [__('Driver'), Arr::get($data, 'database.driver')],
                [__('Version'), Arr::get($data, 'database.version')],
                [__('Cache'), Arr::get($data, 'cache.default')],
                [__('Queue'), Arr::get($data, 'cache.queue')],
                [__('Session'), Arr::get($data, 'cache.session')],
            ],
        ],
    ];
@endphp

<div class="space-y-6">
    <div>
        <flux:heading size="xl">{{ __('Server Information') }}</flux:heading>
        <flux:subheading>{{ __('Runtime, disk, and database details for this environment.') }}</flux:subheading>
    </div>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        @foreach ($cards as $card)
            <div class="admin-card overflow-hidden">
                <div class="border-b border-zinc-200 px-5 py-3 dark:border-zinc-700">
                    <p class="text-sm font-semibold">{{ $card['title'] }}</p>
                </div>
                <ul class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @foreach ($card['rows'] as [$label, $value])
                        <li class="flex items-start justify-between gap-4 px-5 py-3 text-sm">
                            <span class="shrink-0 font-medium text-zinc-600 dark:text-zinc-300">{{ $label }}</span>
                            <span class="break-all text-right text-zinc-800 dark:text-zinc-100">{{ $value ?: '—' }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endforeach
    </div>
</div>
