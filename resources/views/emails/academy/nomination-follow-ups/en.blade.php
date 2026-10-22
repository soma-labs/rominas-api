
@php
    /** @var string $name */
    /** @var string $url */
@endphp

<x-email.layout
    lang="en"
    title="Complete your nominations in the ROMIAS Academy"
>
    <p>Dear {{ $name }},</p>

    <p>
        The nomination process for The Romanian Music Industry Awards
        is still underway, and your participation is essential in
        recognizing those who contribute to excellence in the
        Romanian music industry.
    </p>

    <p>
        We would like to remind you to visit the ROMIAS platform
        to complete your nominations and nominate the artists,
        industry professionals, and peers who deserve recognition
        for their contributions.
    </p>

    <x-email.button :href="$url">
        Submit nominations
    </x-email.button>

    <p>
        Thank you for your participation. We encourage you to
        complete your nominations before the nomination period closes.
    </p>

    <p>
        You are receiving this message because you are a member
        of The Romanian Music Industry Awards Academy.
    </p>
</x-email.layout>
