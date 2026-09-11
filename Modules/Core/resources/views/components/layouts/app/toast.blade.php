@persist('toast')
    <flux:toast position="bottom end" />
@endpersist

{{-- Bridge Livewire/browser `toast` events + session flashes to Flux --}}
<div
    x-data
    x-on:toast.window="
        window.AdminNotify?.toast(
            $event.detail?.message ?? $event.detail?.[0]?.message ?? 'Done',
            $event.detail?.type ?? $event.detail?.[0]?.type ?? 'success',
            $event.detail?.title ?? $event.detail?.[0]?.title ?? null,
        )
    "
    class="hidden"
    aria-hidden="true"
></div>

@if (session('success') || session('error') || session('warning') || session('info'))
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            @if (session('success'))
                window.AdminNotify?.toast(@js(session('success')), 'success');
            @endif
            @if (session('error'))
                window.AdminNotify?.toast(@js(session('error')), 'error');
            @endif
            @if (session('warning'))
                window.AdminNotify?.toast(@js(session('warning')), 'warning');
            @endif
            @if (session('info'))
                window.AdminNotify?.toast(@js(session('info')), 'info');
            @endif
        });
    </script>
@endif
