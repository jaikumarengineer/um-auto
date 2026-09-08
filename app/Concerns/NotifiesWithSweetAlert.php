<?php

namespace App\Concerns;

trait NotifiesWithSweetAlert
{
    protected function notifySuccess(string $message, ?string $title = null): void
    {
        $this->dispatch('toast', message: $message, type: 'success', title: $title);
    }

    protected function notifyError(string $message, ?string $title = null): void
    {
        $this->dispatch('toast', message: $message, type: 'error', title: $title);
    }

    protected function notifyWarning(string $message, ?string $title = null): void
    {
        $this->dispatch('toast', message: $message, type: 'warning', title: $title);
    }

    protected function notifyInfo(string $message, ?string $title = null): void
    {
        $this->dispatch('toast', message: $message, type: 'info', title: $title);
    }
}
