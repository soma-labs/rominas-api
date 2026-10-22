
@php
    /** @var string|null $name */
    /** @var string $url */
@endphp

<x-email.layout
    lang="ro"
    title="Invitația în Academia ROMIAS este încă valabilă!"
>
    <p>Stimate / Stimată {{ $name ?: 'membru al Academiei' }},</p>

    <p>
        Ne bucurăm să vă invităm să faceți parte din Academia The Romanian Music Industry Awards,
        o comunitate dedicată recunoașterii excelenței, profesionalismului și contribuțiilor
        remarcabile la dezvoltarea industriei muzicale românești.
    </p>

    <p>
        Conform informațiilor din platformă, contul dumneavoastră de membru nu a fost încă activat.
        Vă încurajăm să finalizați procesul de activare și să vă alăturați profesioniștilor
        din industrie pentru a celebra talentul și realizările care contribuie la evoluția
        muzicii românești.
    </p>

    <x-email.button :href="$url">
        Activează contul
    </x-email.button>

    <p>
        Activați-vă contul pentru a accesa platforma ROMIAS și pentru a participa
        la procesul de nominalizare.
    </p>

    <p>
        Vă mulțumim pentru implicare și vă încurajăm să vă finalizați nominalizările
        înainte de încheierea perioadei dedicate acestui proces.
    </p>

    <p>
        Primiți acest mesaj pentru că sunteți membru al Academiei
        The Romanian Music Industry Awards.
    </p>
</x-email.layout>
