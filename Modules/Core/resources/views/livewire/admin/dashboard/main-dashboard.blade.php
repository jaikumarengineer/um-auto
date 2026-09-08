<?php

use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('core::layouts.app')] class extends Component {
    public function with(): array
    {
        $totalUsers = User::query()->count();
        $activeUsers = User::query()->where('is_active', true)->count();
        $adminUsers = User::query()->whereIn('user_type', array_keys(User::ADMIN_ROLES))->count();
        $newThisWeek = User::query()->where('created_at', '>=', now()->subWeek())->count();

        $recentUsers = User::query()
            ->latest()
            ->limit(5)
            ->get(['id', 'name', 'last_name', 'email', 'user_type', 'is_active', 'created_at']);

        $hour = (int) now()->format('G');
        $greeting = match (true) {
            $hour < 12 => __('Good morning'),
            $hour < 18 => __('Good afternoon'),
            default => __('Good evening'),
        };

        $dayProgress = min(100, (int) round(((now()->hour * 60 + now()->minute) / (24 * 60)) * 100));

        $schedule = [
            ['time' => '09:00', 'title' => __('Team standup'), 'meta' => __('15 min · Ops'), 'done' => true, 'icon' => 'users'],
            ['time' => '11:30', 'title' => __('Review user onboarding'), 'meta' => __('Product'), 'done' => true, 'icon' => 'clipboard-document-check'],
            ['time' => '14:00', 'title' => __('Clear application caches'), 'meta' => __('Maintenance'), 'done' => false, 'icon' => 'bolt'],
            ['time' => '16:30', 'title' => __('Security checklist'), 'meta' => __('Admin'), 'done' => false, 'icon' => 'shield-check'],
        ];

        $activity = [
            ['label' => __('New staff account invited'), 'when' => __('12 min ago'), 'tone' => 'primary'],
            ['label' => __('General settings updated'), 'when' => __('1 hr ago'), 'tone' => 'secondary'],
            ['label' => __('Failed login blocked'), 'when' => __('3 hr ago'), 'tone' => 'danger'],
            ['label' => __('Weekly digest queued'), 'when' => __('Yesterday'), 'tone' => 'muted'],
        ];

        $stats = [
            [
                'label' => __('Work hours'),
                'value' => '6.4h',
                'hint' => __('of 8h planned'),
                'progress' => 80,
                'color' => 'primary',
            ],
            [
                'label' => __('Tasks'),
                'value' => '12/18',
                'hint' => __('completed today'),
                'progress' => 67,
                'color' => 'secondary',
            ],
            [
                'label' => __('Uptime'),
                'value' => '99.9%',
                'hint' => __('last 30 days'),
                'progress' => 99,
                'color' => 'primary',
            ],
            [
                'label' => __('Support'),
                'value' => '4',
                'hint' => __('open tickets'),
                'progress' => 40,
                'color' => 'secondary',
            ],
        ];

        return compact(
            'totalUsers',
            'activeUsers',
            'adminUsers',
            'newThisWeek',
            'recentUsers',
            'greeting',
            'dayProgress',
            'schedule',
            'activity',
            'stats',
        );
    }
}; ?>

<div class="space-y-6">
    {{-- Hero --}}
    <section class="admin-card admin-fade-in overflow-hidden p-6 lg:p-7">
        <div class="flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
            <div class="min-w-0">
                <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ now()->translatedFormat('l, F j') }}</p>
                <h1 class="mt-1 text-2xl font-semibold tracking-tight lg:text-3xl">
                    {{ $greeting }}, {{ auth()->user()->name }}
                </h1>
                <p class="mt-2 max-w-xl text-sm text-zinc-500 dark:text-zinc-400">
                    {{ __('Next up: Clear application caches at 14:00 · :count users online sample workspace.', ['count' => max($activeUsers, 1)]) }}
                </p>
                <div class="mt-5 flex flex-wrap gap-2.5">
                    <flux:button variant="primary" icon="plus" icon:variant="outline" :href="route('admin::users.create')" wire:navigate class="!rounded-full">
                        {{ __('Add user') }}
                    </flux:button>
                    <flux:button variant="ghost" icon="users" icon:variant="outline" :href="route('admin::users.list')" wire:navigate class="!rounded-full">
                        {{ __('Manage users') }}
                    </flux:button>
                    <flux:modal.trigger name="admin-theme-panel">
                        <flux:button variant="ghost" icon="swatch" icon:variant="outline" class="!rounded-full">{{ __('Theme studio') }}</flux:button>
                    </flux:modal.trigger>
                </div>
            </div>

            <div class="flex shrink-0 items-center gap-4 rounded-3xl bg-[var(--admin-surface-muted)] px-5 py-4">
                <div class="relative flex size-16 items-center justify-center">
                    <svg class="absolute inset-0 size-16 -rotate-90" viewBox="0 0 36 36" aria-hidden="true">
                        <circle cx="18" cy="18" r="15.5" fill="none" class="stroke-zinc-200 dark:stroke-zinc-700" stroke-width="3" />
                        <circle
                            cx="18"
                            cy="18"
                            r="15.5"
                            fill="none"
                            stroke="var(--admin-primary)"
                            stroke-width="3"
                            stroke-linecap="round"
                            stroke-dasharray="{{ $dayProgress }}, 100"
                        />
                    </svg>
                    <span class="text-xs font-semibold">{{ $dayProgress }}%</span>
                </div>
                <div>
                    <p class="text-2xl font-semibold tabular-nums">{{ now()->format('H:i') }}</p>
                    <p class="text-xs text-zinc-500">{{ __('Day complete') }}</p>
                </div>
            </div>
        </div>
    </section>

    {{-- KPI strip --}}
    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="admin-card admin-card-hover admin-fade-in admin-fade-in-delay-1 p-5">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-sm text-zinc-500">{{ __('Total users') }}</p>
                    <p class="mt-1 text-3xl font-semibold tabular-nums">{{ number_format($totalUsers) }}</p>
                </div>
                <span class="flex size-10 items-center justify-center rounded-2xl bg-primary/15 text-primary">
                    <flux:icon.users variant="outline" class="size-5 stroke-[1.5]" />
                </span>
            </div>
            <p class="mt-3 text-xs text-zinc-500">+{{ $newThisWeek }} {{ __('this week') }}</p>
        </div>
        <div class="admin-card admin-card-hover admin-fade-in admin-fade-in-delay-2 p-5">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-sm text-zinc-500">{{ __('Active') }}</p>
                    <p class="mt-1 text-3xl font-semibold tabular-nums">{{ number_format($activeUsers) }}</p>
                </div>
                <span class="flex size-10 items-center justify-center rounded-2xl bg-secondary/15 text-secondary">
                    <flux:icon.check-circle variant="outline" class="size-5 stroke-[1.5]" />
                </span>
            </div>
            <p class="mt-3 text-xs text-zinc-500">{{ __('Currently enabled accounts') }}</p>
        </div>
        <div class="admin-card admin-card-hover admin-fade-in admin-fade-in-delay-3 p-5">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-sm text-zinc-500">{{ __('Admins') }}</p>
                    <p class="mt-1 text-3xl font-semibold tabular-nums">{{ number_format($adminUsers) }}</p>
                </div>
                <span class="flex size-10 items-center justify-center rounded-2xl bg-primary/15 text-primary">
                    <flux:icon.shield-check variant="outline" class="size-5 stroke-[1.5]" />
                </span>
            </div>
            <p class="mt-3 text-xs text-zinc-500">{{ __('Admin · sub-admin · staff') }}</p>
        </div>
        <div class="admin-card admin-card-hover admin-fade-in admin-fade-in-delay-4 p-5">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-sm text-zinc-500">{{ __('Sessions') }}</p>
                    <p class="mt-1 text-3xl font-semibold tabular-nums">128</p>
                </div>
                <span class="flex size-10 items-center justify-center rounded-2xl bg-secondary/15 text-secondary">
                    <flux:icon.chart-bar variant="outline" class="size-5 stroke-[1.5]" />
                </span>
            </div>
            <p class="mt-3 text-xs text-zinc-500">{{ __('Sample traffic today') }}</p>
        </div>
    </section>

    {{-- Mini progress stats --}}
    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($stats as $index => $stat)
            <div class="admin-card admin-fade-in p-4 admin-fade-in-delay-{{ min($index + 1, 4) }}">
                <div class="flex items-center justify-between gap-2">
                    <p class="text-sm text-zinc-500">{{ $stat['label'] }}</p>
                    <p class="text-lg font-semibold tabular-nums">{{ $stat['value'] }}</p>
                </div>
                <div class="mt-3 h-2 overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800">
                    <div
                        class="h-full rounded-full {{ $stat['color'] === 'secondary' ? 'bg-secondary' : 'bg-primary' }}"
                        style="width: {{ $stat['progress'] }}%"
                    ></div>
                </div>
                <p class="mt-2 text-xs text-zinc-500">{{ $stat['hint'] }}</p>
            </div>
        @endforeach
    </section>

    <div class="grid gap-4 xl:grid-cols-5">
        {{-- Schedule --}}
        <section class="admin-card admin-fade-in admin-fade-in-delay-2 p-5 xl:col-span-3">
            <div class="mb-4 flex items-center justify-between gap-3">
                <div>
                    <flux:heading size="lg">{{ __('Today’s schedule') }}</flux:heading>
                    <flux:subheading>{{ __('Sample admin checklist for the day.') }}</flux:subheading>
                </div>
                <span class="rounded-full bg-primary/10 px-3 py-1 text-xs font-medium text-primary">
                    {{ collect($schedule)->where('done', true)->count() }}/{{ count($schedule) }}
                </span>
            </div>

            <ul class="space-y-3">
                @foreach ($schedule as $item)
                    <li class="flex items-center gap-3 rounded-2xl border border-zinc-200/80 px-3 py-3 dark:border-zinc-700/80">
                        <span class="w-12 shrink-0 text-xs font-medium tabular-nums text-zinc-500">{{ $item['time'] }}</span>
                        <span @class([
                            'flex size-9 shrink-0 items-center justify-center rounded-xl',
                            'bg-primary/15 text-primary' => $item['done'],
                            'bg-zinc-100 text-zinc-500 dark:bg-zinc-800' => ! $item['done'],
                        ])>
                            <flux:icon :name="$item['icon']" variant="outline" class="size-4 stroke-[1.5]" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <p @class(['truncate text-sm font-medium', 'line-through opacity-60' => $item['done']])>
                                {{ $item['title'] }}
                            </p>
                            <p class="truncate text-xs text-zinc-500">{{ $item['meta'] }}</p>
                        </div>
                        @if ($item['done'])
                            <flux:icon.check-circle variant="outline" class="size-5 stroke-[1.5] text-primary" />
                        @else
                            <span class="size-2 rounded-full bg-secondary"></span>
                        @endif
                    </li>
                @endforeach
            </ul>
        </section>

        {{-- Activity --}}
        <section class="admin-card admin-fade-in admin-fade-in-delay-3 p-5 xl:col-span-2">
            <div class="mb-4">
                <flux:heading size="lg">{{ __('Activity') }}</flux:heading>
                <flux:subheading>{{ __('Recent sample events.') }}</flux:subheading>
            </div>
            <ul class="space-y-3">
                @foreach ($activity as $row)
                    <li class="flex items-start gap-3">
                        <span @class([
                            'mt-1 size-2.5 shrink-0 rounded-full',
                            'bg-primary' => $row['tone'] === 'primary',
                            'bg-secondary' => $row['tone'] === 'secondary',
                            'bg-red-500' => $row['tone'] === 'danger',
                            'bg-zinc-400' => $row['tone'] === 'muted',
                        ])></span>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium">{{ $row['label'] }}</p>
                            <p class="text-xs text-zinc-500">{{ $row['when'] }}</p>
                        </div>
                    </li>
                @endforeach
            </ul>
        </section>
    </div>

    {{-- Recent users table --}}
    <section class="admin-card admin-fade-in admin-fade-in-delay-4 overflow-hidden">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-zinc-200 px-5 py-4 dark:border-zinc-700">
            <div>
                <flux:heading size="lg">{{ __('Recent users') }}</flux:heading>
                <flux:subheading>{{ __('Latest accounts in the system.') }}</flux:subheading>
            </div>
            <flux:button size="sm" variant="ghost" :href="route('admin::users.list')" wire:navigate>
                {{ __('View all') }}
            </flux:button>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                <thead class="bg-[var(--admin-surface-muted)]">
                    <tr>
                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500">{{ __('Name') }}</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500">{{ __('Email') }}</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500">{{ __('Role') }}</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-zinc-500">{{ __('Status') }}</th>
                        <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wider text-zinc-500">{{ __('Joined') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse ($recentUsers as $user)
                        <tr class="transition-colors hover:bg-zinc-50 dark:hover:bg-zinc-800/40">
                            <td class="px-5 py-3">
                                <div class="flex items-center gap-3">
                                    <span class="flex size-8 items-center justify-center rounded-full bg-primary/15 text-xs font-semibold text-primary">
                                        {{ $user->initials() }}
                                    </span>
                                    <span class="text-sm font-medium">{{ $user->name }} {{ $user->last_name }}</span>
                                </div>
                            </td>
                            <td class="px-5 py-3 text-sm text-zinc-600 dark:text-zinc-300">{{ $user->email }}</td>
                            <td class="px-5 py-3 text-sm capitalize">{{ str_replace('_', ' ', $user->user_type ?? 'user') }}</td>
                            <td class="px-5 py-3">
                                @if ($user->is_active)
                                    <span class="inline-flex rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-700 dark:bg-green-900/40 dark:text-green-300">{{ __('Active') }}</span>
                                @else
                                    <span class="inline-flex rounded-full bg-zinc-100 px-2.5 py-0.5 text-xs font-medium text-zinc-500 dark:bg-zinc-700 dark:text-zinc-300">{{ __('Inactive') }}</span>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-right text-sm text-zinc-500">{{ $user->created_at?->diffForHumans() }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 py-10 text-center text-sm text-zinc-500">{{ __('No users yet.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
