@props(['status' => ''])

@php
    $tones = [
        'approved' => 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300',
        'accepted' => 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300',
        'pass' => 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300',
        'ok' => 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300',
        'verified' => 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300',
        'sent' => 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300',
        'active' => 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300',
        'closed' => 'bg-zinc-100 text-zinc-700 dark:bg-zinc-700 dark:text-zinc-200',
        'draft' => 'bg-zinc-100 text-zinc-600 dark:bg-zinc-700 dark:text-zinc-300',
        'logged' => 'bg-zinc-100 text-zinc-600 dark:bg-zinc-700 dark:text-zinc-300',
        'obsolete' => 'bg-zinc-100 text-zinc-600 dark:bg-zinc-700 dark:text-zinc-300',
        'open' => 'bg-sky-100 text-sky-700 dark:bg-sky-900/40 dark:text-sky-300',
        'received' => 'bg-sky-100 text-sky-700 dark:bg-sky-900/40 dark:text-sky-300',
        'partial' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200',
        'pending' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200',
        'under_review' => 'bg-sky-100 text-sky-700 dark:bg-sky-900/40 dark:text-sky-300',
        'sent_back' => 'bg-orange-100 text-orange-800 dark:bg-orange-900/40 dark:text-orange-200',
        'conditional' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200',
        'under_inspection' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200',
        'rejected' => 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
        'fail' => 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
        'ng' => 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
        'blocked' => 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
        'cancelled' => 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
        'mismatch' => 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
        'failed' => 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
    ];
    $label = str((string) $status)->replace('_', ' ')->title();
@endphp

<span {{ $attributes->class('inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium '.($tones[$status] ?? 'bg-zinc-100 text-zinc-700 dark:bg-zinc-700 dark:text-zinc-200')) }}>
    {{ $label }}
</span>
