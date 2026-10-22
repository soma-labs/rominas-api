
@php
    /** @var string $name */
    /** @var string $url */
@endphp

<x-email.layout
    lang="ro"
    title="Finalizează nominalizările în Academia ROMIAS"
>
    <p>Stimate / Stimată {{ $name }},</p>

    <p>
        Procesul de nominalizare pentru The Romanian Music Industry Awards
        continuă, iar implicarea dumneavoastră este esențială pentru
        recunoașterea celor care contribuie la excelența industriei
        muzicale românești.
    </p>

    <p>
        Vă reamintim să accesați platforma ROMIAS pentru a finaliza
        nominalizările și pentru a propune artiștii, profesioniștii și
        colegii care merită să fie recunoscuți pentru contribuția lor.
    </p>

    <x-email.button :href="$url">
        Trimite nominalizările
    </x-email.button>

    <p>
        Vă mulțumim pentru implicare și vă încurajăm să vă finalizați
        nominalizările înainte de încheierea perioadei dedicate
        acestui proces.
    </p>

    <p>
        Primiți acest mesaj pentru că sunteți membru al Academiei
        The Romanian Music Industry Awards.
    </p>
</x-email.layout>
