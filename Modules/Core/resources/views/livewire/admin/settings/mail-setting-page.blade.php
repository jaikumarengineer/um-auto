<?php

use App\Facades\Setting;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('core::layouts.app')] class extends Component {
    public string $mailer = 'smtp';

    public string $host = '';

    public string $port = '587';

    public string $username = '';

    public string $password = '';

    public string $encryption = 'tls';

    public string $from_address = '';

    public string $from_name = '';

    public string $support_phone = '';

    public function mount(): void
    {
        $this->mailer = (string) setting('mailer', 'smtp', 'mail');
        $this->host = (string) setting('host', '', 'mail');
        $this->port = (string) setting('port', '587', 'mail');
        $this->username = (string) setting('username', '', 'mail');
        $this->password = (string) setting('password', '', 'mail');
        $this->encryption = (string) setting('encryption', 'tls', 'mail');
        $this->from_address = (string) setting('from_address', '', 'mail');
        $this->from_name = (string) setting('from_name', '', 'mail');
        $this->support_phone = (string) setting('support_phone', '', 'mail');
    }

    public function save(): void
    {
        $this->validate([
            'mailer' => 'required|string',
            'host' => 'nullable|string|max:255',
            'port' => 'nullable|string|max:10',
            'username' => 'nullable|string|max:255',
            'password' => 'nullable|string|max:255',
            'encryption' => 'nullable|string|max:20',
            'from_address' => 'nullable|email|max:255',
            'from_name' => 'nullable|string|max:255',
            'support_phone' => 'nullable|string|max:40',
        ]);

        Setting::setMany([
            'mailer' => $this->mailer,
            'host' => $this->host,
            'port' => $this->port,
            'username' => $this->username,
            'password' => $this->password,
            'encryption' => $this->encryption,
            'from_address' => $this->from_address,
            'from_name' => $this->from_name,
            'support_phone' => $this->support_phone,
        ], 'mail');

        session()->flash('success', __('Mail settings saved.'));
    }
}; ?>

@php
    $mailerOptions = [
        'smtp' => 'SMTP',
        'sendmail' => 'Sendmail',
        'log' => 'Log (local)',
        'array' => 'Array',
    ];
    $encryptionOptions = [
        'tls' => 'TLS',
        'ssl' => 'SSL',
        '' => 'None',
    ];
@endphp

<div>
    <div class="mb-6">
        <flux:heading size="xl">{{ __('Mail Settings') }}</flux:heading>
        <flux:subheading>{{ __('Outgoing mailer configuration.') }}</flux:subheading>
    </div>

    @if (session('success'))
        <flux:callout variant="success" icon="check-circle" class="mb-4">{{ session('success') }}</flux:callout>
    @endif

    <form wire:submit="save" class="max-w-2xl">
        <div class="admin-card overflow-hidden">
            <div class="grid gap-5 px-6 py-6 sm:grid-cols-2">
                <x-core::forms.select2 wire:model="mailer" :label="__('Mailer')" :options="$mailerOptions" class="sm:col-span-2" />
                <flux:input wire:model="host" :label="__('Host')" />
                <flux:input wire:model="port" :label="__('Port')" />
                <flux:input wire:model="username" :label="__('Username')" />
                <flux:input wire:model="password" type="password" :label="__('Password')" viewable />
                <x-core::forms.select2 wire:model="encryption" :label="__('Encryption')" :options="$encryptionOptions" />
                <flux:input wire:model="from_address" type="email" :label="__('From address')" />
                <flux:input wire:model="from_name" :label="__('From name')" class="sm:col-span-2" />
                <x-core::forms.phone wire:model="support_phone" :label="__('Support phone')" class="sm:col-span-2" />
            </div>
            <div class="flex justify-end border-t border-zinc-200 bg-[var(--admin-surface-muted)] px-6 py-4 dark:border-zinc-700">
                <flux:button variant="primary" type="submit">{{ __('Save Settings') }}</flux:button>
            </div>
        </div>
    </form>
</div>
