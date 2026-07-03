<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name') }}</title>
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />
        <style>
            body { font-family: "Instrument Sans", ui-sans-serif, system-ui, sans-serif; margin: 0; min-height: 100dvh; display: flex; align-items: center; justify-content: center; background: #fafafa; color: #18181b; }
            @media (prefers-color-scheme: dark) { body { background: #09090b; color: #fafafa; } }
            .box { text-align: center; padding: 1.5rem; max-width: 28rem; }
            h1 { font-size: 1.5rem; font-weight: 600; letter-spacing: -0.02em; margin: 0 0 1rem; }
            p { font-size: 0.875rem; color: #71717a; margin: 0; line-height: 1.6; }
            @media (prefers-color-scheme: dark) { p { color: #a1a1aa; } }
            a { color: inherit; font-weight: 500; text-decoration: underline; text-underline-offset: 4px; }
            .sep { color: #a1a1aa; margin: 0 0.35rem; }
        </style>
    </head>
    <body>
        <div class="box">
            <h1>{{ config('app.name') }}</h1>
            <p>
                @if (Route::has('home'))
                    <a href="{{ route('home') }}">{{ __('Go to home') }}</a>
                @else
                    <a href="{{ url('/') }}">{{ __('Go to home') }}</a>
                @endif
                @if (Route::has('login'))
                    <span class="sep">·</span>
                    <a href="{{ route('login') }}">{{ __('Log in') }}</a>
                @endif
                @if (Route::has('admin::login'))
                    <span class="sep">·</span>
                    <a href="{{ route('admin::login') }}">{{ __('Admin') }}</a>
                @endif
            </p>
        </div>
    </body>
</html>
