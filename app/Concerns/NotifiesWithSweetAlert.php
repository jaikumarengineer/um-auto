<?php

namespace App\Concerns;

use Flux\Flux;

trait NotifiesWithSweetAlert
{
    protected function notifySuccess(string $message, ?string $title = null): void
    {
        Flux::toast(
            heading: $title,
            text: $message,
            variant: 'success',
        );
    }

    protected function notifyError(string $message, ?string $title = null): void
    {
        Flux::toast(
            heading: $title,
            text: $message,
            variant: 'danger',
        );
    }

    protected function notifyWarning(string $message, ?string $title = null): void
    {
        Flux::toast(
            heading: $title,
            text: $message,
            variant: 'warning',
        );
    }

    protected function notifyInfo(string $message, ?string $title = null): void
    {
        Flux::toast(
            heading: $title,
            text: $message,
            variant: 'success',
        );
    }

    /**
     * Modal SweetAlert used on save flows. Pass $url for a "Back To List" action.
     */
    protected function sweetMessage(
        string $message,
        string $type = 'success',
        ?string $title = null,
        string|false|null $url = false,
    ): void {
        $this->dispatch(
            'SweetMessage',
            type: $type,
            title: $title ?? ($type === 'success' ? __('Success') : __('Error')),
            message: $message,
            url: $url ?: false,
        );
    }
}
