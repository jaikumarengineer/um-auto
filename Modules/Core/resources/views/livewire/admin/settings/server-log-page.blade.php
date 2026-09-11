<?php

use App\Services\LogViewerService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

new #[Layout('core::layouts.app')] class extends Component {
    use WithPagination;

    #[Url(as: 'file')]
    public string $file = '';

    #[Url]
    public string $search = '';

    #[Url]
    public string $level = '';

    public int $perPage = 25;

    public function mount(LogViewerService $logs): void
    {
        if ($this->file === '') {
            $this->file = (string) ($logs->listFiles()->first()['name'] ?? '');
        }
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingLevel(): void
    {
        $this->resetPage();
    }

    public function updatingFile(): void
    {
        $this->resetPage();
        $this->search = '';
        $this->level = '';
    }

    public function updatingPerPage(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'level']);
        $this->resetPage();
    }

    public function clearFile(LogViewerService $logs): void
    {
        if ($this->file === '') {
            return;
        }

        $logs->clear($this->file);
        $this->dispatch('toast', message: __('Log file cleared.'), type: 'success');
        $this->resetPage();
    }

    public function deleteFile(LogViewerService $logs): void
    {
        if ($this->file === '') {
            return;
        }

        $name = $this->file;
        $logs->delete($name);
        $this->file = (string) ($logs->listFiles()->first()['name'] ?? '');
        $this->dispatch('toast', message: __('Log file deleted.'), type: 'success');
        $this->resetPage();
    }

    public function download(LogViewerService $logs): BinaryFileResponse|StreamedResponse
    {
        abort_if($this->file === '', 404);

        return $logs->download($this->file);
    }

    public function with(LogViewerService $logs): array
    {
        $files = $logs->listFiles();
        $current = $logs->resolveFile($this->file ?: null);

        if ($current && $this->file !== $current['name']) {
            $this->file = $current['name'];
        }

        $parsed = $current
            ? $logs->paginate(
                $current['path'],
                $this->search,
                $this->level,
                $this->perPage,
                $this->getPage()
            )
            : [
                'entries' => new \Illuminate\Pagination\LengthAwarePaginator([], 0, $this->perPage),
                'stats' => [],
            ];

        return [
            'files' => $files,
            'current' => $current,
            'entries' => $parsed['entries'],
            'stats' => $parsed['stats'],
            'levels' => [
                '' => __('All levels'),
                'emergency' => 'Emergency',
                'alert' => 'Alert',
                'critical' => 'Critical',
                'error' => 'Error',
                'warning' => 'Warning',
                'notice' => 'Notice',
                'info' => 'Info',
                'debug' => 'Debug',
            ],
        ];
    }
}; ?>

@php
    $levelColors = [
        'EMERGENCY' => 'bg-red-700 text-white',
        'ALERT' => 'bg-red-600 text-white',
        'CRITICAL' => 'bg-red-500 text-white',
        'ERROR' => 'bg-rose-500 text-white',
        'WARNING' => 'bg-amber-400 text-zinc-900',
        'NOTICE' => 'bg-sky-400 text-zinc-900',
        'INFO' => 'bg-emerald-500 text-white',
        'DEBUG' => 'bg-zinc-400 text-zinc-900',
    ];
@endphp

<div class="space-y-6">
    <x-core::ui.page-header
        :title="__('Server Logs')"
        :subtitle="__('Browse, filter, download, or clear Laravel log files.')"
        :breadcrumbs="[
            ['label' => __('Dashboard'), 'route' => 'admin::dashboard'],
            ['label' => __('Settings')],
            ['label' => __('Server Logs')],
        ]"
    >
        <x-slot:actions>
            @if ($current)
                <flux:button variant="ghost" wire:click="download" icon="arrow-down-tray">{{ __('Download') }}</flux:button>
                <flux:button
                    variant="ghost"
                    wire:click="clearFile"
                    wire:confirm="{{ __('Clear all contents of this log file?') }}"
                    icon="trash"
                >{{ __('Clear') }}</flux:button>
                <flux:button
                    variant="danger"
                    wire:click="deleteFile"
                    wire:confirm="{{ __('Permanently delete this log file?') }}"
                    icon="x-mark"
                >{{ __('Delete file') }}</flux:button>
            @endif
        </x-slot:actions>
    </x-core::ui.page-header>

    @if ($files->isEmpty())
        <div class="admin-card p-8 text-center text-sm text-zinc-500">
            {{ __('No log files found in storage/logs.') }}
        </div>
    @else
        <div class="grid gap-4 lg:grid-cols-12">
            <aside class="admin-card lg:col-span-3 overflow-hidden">
                <div class="border-b border-zinc-200 px-4 py-3 dark:border-zinc-700">
                    <p class="text-sm font-semibold">{{ __('Log files') }}</p>
                </div>
                <ul class="max-h-[28rem] divide-y divide-zinc-100 overflow-y-auto dark:divide-zinc-800">
                    @foreach ($files as $item)
                        <li>
                            <button
                                type="button"
                                wire:click="$set('file', '{{ $item['name'] }}')"
                                @class([
                                    'flex w-full flex-col gap-0.5 px-4 py-3 text-left transition',
                                    'bg-[color-mix(in_srgb,var(--admin-primary)_10%,white)] dark:bg-[color-mix(in_srgb,var(--admin-primary)_18%,rgb(39_39_42))]' => $file === $item['name'],
                                    'hover:bg-zinc-50 dark:hover:bg-zinc-800/60' => $file !== $item['name'],
                                ])
                            >
                                <span class="truncate text-sm font-medium text-zinc-800 dark:text-zinc-100">{{ $item['name'] }}</span>
                                <span class="text-xs text-zinc-500">{{ $item['size_human'] }} · {{ $item['modified_human'] }}</span>
                            </button>
                        </li>
                    @endforeach
                </ul>
            </aside>

            <section class="space-y-4 lg:col-span-9">
                <div class="admin-card grid gap-4 p-4 sm:grid-cols-2 xl:grid-cols-4">
                    <flux:input wire:model.live.debounce.300ms="search" :label="__('Search')" placeholder="{{ __('Message or stack…') }}" icon="magnifying-glass" />
                    <flux:select wire:model.live="level" :label="__('Level')">
                        @foreach ($levels as $value => $label)
                            <flux:select.option :value="$value">{{ $label }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:select wire:model.live="perPage" :label="__('Per page')">
                        <flux:select.option value="10">10</flux:select.option>
                        <flux:select.option value="25">25</flux:select.option>
                        <flux:select.option value="50">50</flux:select.option>
                        <flux:select.option value="100">100</flux:select.option>
                    </flux:select>
                    <div class="flex items-end">
                        <flux:button variant="ghost" wire:click="clearFilters" class="w-full">{{ __('Reset filters') }}</flux:button>
                    </div>
                </div>

                @if (! empty($stats))
                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-4 xl:grid-cols-8">
                        @foreach (['error', 'warning', 'info', 'debug', 'critical', 'alert', 'notice', 'emergency'] as $statLevel)
                            <button
                                type="button"
                                wire:click="$set('level', '{{ $level === $statLevel ? '' : $statLevel }}')"
                                class="admin-card flex flex-col px-3 py-2 text-left transition hover:ring-1 hover:ring-[var(--admin-primary)]"
                            >
                                <span class="text-[11px] uppercase tracking-wide text-zinc-500">{{ $statLevel }}</span>
                                <span class="text-lg font-semibold tabular-nums">{{ $stats[$statLevel] ?? 0 }}</span>
                            </button>
                        @endforeach
                    </div>
                @endif

                <div class="admin-card overflow-hidden">
                    <div class="flex items-center justify-between gap-3 border-b border-zinc-200 px-4 py-3 dark:border-zinc-700">
                        <p class="text-sm font-semibold">
                            {{ $current['name'] ?? __('Logs') }}
                            <span class="font-normal text-zinc-500">({{ $entries->total() }} {{ __('matches') }})</span>
                        </p>
                        @if ($current)
                            <span class="text-xs text-zinc-500">{{ $current['size_human'] }}</span>
                        @endif
                    </div>

                    <div class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @forelse ($entries as $entry)
                            <article class="px-4 py-3">
                                <div class="mb-1 flex flex-wrap items-center gap-2">
                                    <span @class([
                                        'inline-flex rounded-md px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide',
                                        $levelColors[$entry['level']] ?? 'bg-zinc-200 text-zinc-800',
                                    ])>{{ $entry['level'] }}</span>
                                    <span class="text-xs text-zinc-500">{{ $entry['datetime'] }}</span>
                                    <span class="text-xs text-zinc-400">{{ $entry['env'] }}</span>
                                </div>
                                <pre class="overflow-x-auto whitespace-pre-wrap break-words font-mono text-xs leading-relaxed text-zinc-800 dark:text-zinc-200">{{ $entry['message'] }}</pre>
                                @if ($entry['context'] !== '')
                                    <details class="mt-2">
                                        <summary class="cursor-pointer text-xs font-medium text-[var(--admin-primary)]">{{ __('Stack / context') }}</summary>
                                        <pre class="mt-2 max-h-64 overflow-auto rounded-lg bg-zinc-950/95 p-3 font-mono text-[11px] leading-relaxed text-zinc-100">{{ $entry['context'] }}</pre>
                                    </details>
                                @endif
                            </article>
                        @empty
                            <div class="px-4 py-10 text-center text-sm text-zinc-500">
                                {{ __('No log entries match your filters.') }}
                            </div>
                        @endforelse
                    </div>

                    @if ($entries->hasPages())
                        <div class="flex flex-col gap-3 border-t border-zinc-200 px-4 py-3 sm:flex-row sm:items-center sm:justify-between dark:border-zinc-700">
                            <p class="text-xs text-zinc-500">
                                {{ __('Showing :from–:to of :total', [
                                    'from' => $entries->firstItem() ?? 0,
                                    'to' => $entries->lastItem() ?? 0,
                                    'total' => $entries->total(),
                                ]) }}
                            </p>
                            <div class="flex items-center gap-2">
                                <flux:button size="sm" variant="ghost" wire:click="previousPage" :disabled="$entries->onFirstPage()">{{ __('Previous') }}</flux:button>
                                <span class="text-xs tabular-nums text-zinc-600 dark:text-zinc-300">
                                    {{ $entries->currentPage() }} / {{ $entries->lastPage() }}
                                </span>
                                <flux:button size="sm" variant="ghost" wire:click="nextPage" :disabled="! $entries->hasMorePages()">{{ __('Next') }}</flux:button>
                            </div>
                        </div>
                    @endif
                </div>
            </section>
        </div>
    @endif
</div>
