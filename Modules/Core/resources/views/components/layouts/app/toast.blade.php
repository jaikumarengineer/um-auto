{{-- Global toast stack --}}
<div
    x-data="{
        toasts: [],
        add(event) {
            const id = Date.now() + Math.random()
            const toast = {
                id,
                message: event.detail.message ?? event.detail[0]?.message ?? 'Done',
                type: event.detail.type ?? event.detail[0]?.type ?? 'success',
            }
            this.toasts.push(toast)
            setTimeout(() => this.remove(id), 3500)
        },
        remove(id) {
            this.toasts = this.toasts.filter(t => t.id !== id)
        }
    }"
    x-on:toast.window="add($event)"
    class="pointer-events-none fixed inset-x-0 top-4 z-[100] flex flex-col items-end gap-2 px-4 sm:px-6"
    aria-live="polite"
>
    <template x-for="toast in toasts" :key="toast.id">
        <div
            x-show="true"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 translate-y-2"
            x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="pointer-events-auto flex max-w-sm items-start gap-3 rounded-xl border px-4 py-3 shadow-lg backdrop-blur-sm"
            :class="{
                'border-green-500/30 bg-green-50 text-green-900 dark:bg-green-950/90 dark:text-green-100': toast.type === 'success',
                'border-red-500/30 bg-red-50 text-red-900 dark:bg-red-950/90 dark:text-red-100': toast.type === 'error',
                'border-zinc-500/30 bg-zinc-50 text-zinc-900 dark:bg-zinc-900/90 dark:text-zinc-100': toast.type !== 'success' && toast.type !== 'error',
            }"
        >
            <div class="mt-0.5 shrink-0">
                <template x-if="toast.type === 'success'">
                    <svg class="size-5 text-green-600 dark:text-green-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                </template>
                <template x-if="toast.type === 'error'">
                    <svg class="size-5 text-red-600 dark:text-red-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                </template>
            </div>
            <p class="flex-1 text-sm font-medium leading-5" x-text="toast.message"></p>
            <button
                type="button"
                class="shrink-0 rounded-md p-0.5 opacity-60 hover:opacity-100"
                @click="remove(toast.id)"
            >
                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
            </button>
        </div>
    </template>
</div>
