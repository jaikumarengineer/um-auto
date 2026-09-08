<?php

use Illuminate\Support\Facades\Artisan;
use Livewire\Volt\Component;

new class extends Component {
    public function clearCache(): void
    {
        Artisan::call('cache:clear');
        $this->toast(__('Application cache cleared.'), 'success');
    }

    public function clearConfig(): void
    {
        Artisan::call('config:clear');
        $this->toast(__('Config cache cleared.'), 'success');
    }

    public function clearRoutes(): void
    {
        Artisan::call('route:clear');
        $this->toast(__('Route cache cleared.'), 'success');
    }

    public function clearViews(): void
    {
        Artisan::call('view:clear');
        $this->toast(__('Compiled views cleared.'), 'success');
    }

    public function optimizeClear(): void
    {
        Artisan::call('optimize:clear');
        $this->toast(__('All caches cleared successfully.'), 'success');
    }

    protected function toast(string $message, string $type = 'success'): void
    {
        $this->dispatch('toast', message: $message, type: $type);
    }
}; ?>

<div>
    <flux:dropdown position="bottom" align="end">
        <flux:tooltip :content="__('Boost — clear caches')" position="bottom">
            <button
                type="button"
                class="admin-icon-btn inline-flex items-center justify-center text-zinc-500 hover:text-zinc-800 dark:text-zinc-400 dark:hover:text-white"
                wire:loading.attr="disabled"
                aria-label="{{ __('Boost') }}"
            >
                <flux:icon.bolt variant="outline" class="size-5 stroke-[1.5]" />
            </button>
        </flux:tooltip>

        <flux:menu class="admin-menu w-[240px] overflow-hidden rounded-2xl!">
            <div class="px-3 py-2">
                <p class="text-sm font-semibold">{{ __('Boost') }}</p>
                <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Clear Laravel caches') }}</p>
            </div>

            <flux:menu.separator />

            <flux:menu.item icon="circle-stack" icon:variant="outline" wire:click="clearCache" wire:loading.class="opacity-50">
                {{ __('Clear cache') }}
            </flux:menu.item>
            <flux:menu.item icon="cog-6-tooth" icon:variant="outline" wire:click="clearConfig" wire:loading.class="opacity-50">
                {{ __('Clear config') }}
            </flux:menu.item>
            <flux:menu.item icon="map" icon:variant="outline" wire:click="clearRoutes" wire:loading.class="opacity-50">
                {{ __('Clear routes') }}
            </flux:menu.item>
            <flux:menu.item icon="document-text" icon:variant="outline" wire:click="clearViews" wire:loading.class="opacity-50">
                {{ __('Clear views') }}
            </flux:menu.item>

            <flux:menu.separator />

            <flux:menu.item icon="rocket-launch" icon:variant="outline" wire:click="optimizeClear" wire:loading.class="opacity-50">
                {{ __('Clear all') }}
            </flux:menu.item>
        </flux:menu>
    </flux:dropdown>
</div>
