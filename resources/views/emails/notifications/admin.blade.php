@php
    /** @var string $title */
    /** @var string $body */
    /** @var string $url */
@endphp
<x-email.layout lang="en" :title="$title">
    <h1 style="margin:0 0 16px;font-size:22px;">{{ $title }}</h1>

    <p style="margin:0 0 24px;font-size:15px;line-height:1.5;">{{ $body }}</p>

    <x-email.button :href="$url">Open in Romias Admin</x-email.button>

    <p style="margin:0;font-size:13px;color:#64748b;line-height:1.5;">
        You receive this message because your Admin account can act on it.
    </p>
</x-email.layout>
