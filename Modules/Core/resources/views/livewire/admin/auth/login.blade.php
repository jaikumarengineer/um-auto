<?php

use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;

new #[Layout(name: 'core::components.layouts.admin.auth')] class extends Component {
    #[Validate('required|string')]
    public string $username = '';

    #[Validate('required|string')]
    public string $password = '';

    public bool $remember = false;

    /**
     * Handle an incoming authentication request.
     */
    public function login(): void
    {
        $this->validate();

        $this->ensureIsNotRateLimited();

        if (! Auth::attempt(['username' => $this->username, 'password' => $this->password], $this->remember)) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'username' => __('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());
        Session::regenerate();

        $this->redirectIntended(default: route(auth()->user()->adminHomeRoute(), absolute: false), navigate: true);
    }

    /**
     * Ensure the authentication request is not rate limited.
     */
    protected function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout(request()));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'username' => __('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the authentication rate limiting throttle key.
     */
    protected function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->username).'|'.request()->ip());
    }
}; ?>

<div class="admin-auth-enter admin-auth-enter-2 flex flex-col">
    <div class="mb-8">
        <h1 class="admin-auth-title text-2xl font-bold tracking-tight sm:text-[1.75rem]">
            {{ __('Log in to your account.') }}
        </h1>
        <p class="admin-auth-subtitle mt-2 text-sm leading-relaxed">
            {{ __('Enter your username and password to log in.') }}
        </p>
    </div>

    <x-auth-session-status class="mb-4 text-sm" :status="session('status')" />

    <form wire:submit="login" class="flex flex-col gap-4">
        <div class="admin-auth-input">
            <flux:input
                wire:model="username"
                name="username"
                type="text"
                icon="user"
                required
                autofocus
                autocomplete="username"
                :placeholder="__('Username')"
            />
            <flux:error name="username" />
        </div>

        <div class="admin-auth-input">
            <flux:input
                wire:model="password"
                name="password"
                type="password"
                icon="lock-closed"
                required
                autocomplete="current-password"
                :placeholder="__('Password')"
                viewable
            />
            <flux:error name="password" />
        </div>

        <div class="flex items-center justify-between gap-3 pt-0.5">
            <flux:checkbox wire:model="remember" :label="__('Remember me')" />

            @if (Route::has('password.request'))
                <flux:link class="text-sm font-medium" :href="route('password.request')" wire:navigate>
                    {{ __('Forgot password?') }}
                </flux:link>
            @endif
        </div>

        <flux:button variant="primary" type="submit" class="admin-auth-submit mt-2 w-full">
            {{ __('Login') }}
        </flux:button>
    </form>
</div>
