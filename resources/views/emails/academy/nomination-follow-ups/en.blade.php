@php
    /** @var string $name */
    /** @var string $url */
@endphp

<x-email.layout
    lang="en"
    title="Complete the nominations and nominate colleagues in the ROMIAS Academy"
>
    <p>Dear {{ $name }},</p>

    <p>
        Thank you for your trust and for being part of The Romanian Music
        Industry Awards Academy. Your involvement helps strengthen an
        initiative dedicated to recognizing professionalism, excellence,
        and outstanding contributions to Romania's music industry.
    </p>

    <p>
        We invite you to return to the ROMIAS platform to complete your
        nominations and nominate the colleagues and industry professionals
        you believe best represent the Romanian music scene. Every vote
        matters in building a recognition system founded on merit,
        integrity, and professionalism.
    </p>

    <x-email.button :href="$url">
        Login
    </x-email.button>

    <p>
        Thank you for your time and valuable contribution to the ROMIAS initiative!
    </p>

    <p>
        Kind regards,<br>
        The ROMIAS Team<br>
        The Romanian Music Industry Awards
    </p>
</x-email.layout>
