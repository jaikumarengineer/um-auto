<flux:card padding="px-6 py-4">
    <div class="flex justify-between items-center">
        <div class="flex items-center gap-2">
            <x-app-logo class="h-6 w-6" />
            <span class="text-sm">
                © {{ date('Y') }}, made with <span class="text-red-500">❤️</span> by
                <a href="{{ url(config('app.company_url')) }}" target="_blank" class="hover:underline font-semibold">
                    {{ config('app.company_name') ?? 'Agile Legions' }}
                </a>
            </span>
        </div>
        <flux:text>
            Page Load : <b>{{ round(microtime(true) - LARAVEL_START, 2) }}s</b>
        </flux:text>
    </div>
</flux:card>
