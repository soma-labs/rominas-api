@php
    /** @var string $name */
    /** @var string $url */
@endphp

<x-email.layout
    lang="ro"
    title="Finalizează nominalizările și propune colegi în Academia ROMIAS"
>
    <p>Stimate / Stimată {{ $name }},</p>

    <p>
        Vă mulțumim pentru încrederea acordată și pentru că faceți parte din
        Academia The Romanian Music Industry Awards. Implicarea dumneavoastră
        contribuie la construirea unei inițiative dedicate recunoașterii
        profesionalismului și excelenței în industria muzicală românească.
    </p>

    <p>
        Vă invităm să reveniți pe platforma ROMIAS pentru a finaliza
        nominalizările și a propune colegii și profesioniștii pe care îi
        considerați reprezentativi. Fiecare vot contează în construirea
        unui sistem de recunoaștere bazat pe merit, integritate și profesionalism.
    </p>

    <x-email.button :href="$url">
        Autentificare
    </x-email.button>

    <p>
        Vă mulțumim pentru timpul acordat și pentru contribuția dumneavoastră!
    </p>

    <p>
        Cu apreciere și considerație,<br>
        The ROMIAS Team<br>
        The Romanian Music Industry Awards
    </p>
</x-email.layout>
