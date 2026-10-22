
@php
    /** @var string|null $name */
    /** @var string $url */
@endphp

<x-email.layout
    lang="en"
    title="Your invitation to the ROMIAS Academy is still open!"
>
    <p>Dear {{ $name ?: 'Academy member' }},</p>

    <p>
        We are delighted to invite you to become part of The Romanian Music Industry Awards Academy,
        a community dedicated to recognizing excellence, professionalism, and outstanding
        contributions to the Romanian music industry.
    </p>

    <p>
        According to our records, your Academy membership account has not yet been activated.
        We encourage you to complete the activation process and join fellow industry
        professionals in celebrating the talent and achievements that contribute
        to the growth of Romanian music.
    </p>

    <x-email.button :href="$url">
        Activate your account
    </x-email.button>

    <p>
        Activate your account to access the ROMIAS platform and participate
        in the nomination process.
    </p>

    <p>
        Thank you for your involvement. We encourage you to complete your nominations
        before the nomination period closes.
    </p>

    <p>
        You are receiving this message because you are a member of
        The Romanian Music Industry Awards Academy.
    </p>
</x-email.layout>
