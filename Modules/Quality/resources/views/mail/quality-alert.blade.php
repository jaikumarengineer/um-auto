<x-mail::message>
# {{ $payload['heading'] }}

{{ __('As of :time', ['time' => $payload['as_of']]) }}

@if (($payload['badge'] ?? '') !== '')
<x-mail::panel>
**{{ $payload['badge'] }}**
</x-mail::panel>
@endif

@foreach ($payload['preamble'] ?? [] as $line)
{{ $line }}

@endforeach

@foreach ($payload['sections'] as $section)
## {{ $section['title'] }}

@forelse ($section['lines'] as $line)
- {{ $line }}
@empty
- {{ __('None.') }}
@endforelse

@endforeach

<x-mail::button :url="$url">
{{ __('Open quality alerts') }}
</x-mail::button>

{{ __('Regards,') }}<br>
{{ config('app.name') }}
</x-mail::message>
