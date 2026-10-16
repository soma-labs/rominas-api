@php
    /** @var string|null $name */
    /** @var string $url */
    /** @var string|null $proposerName */
@endphp
<x-email.layout lang="ro" title="Invitația în Academia The Romanian Music Industry Awards este încă deschisă">
    <p style="margin:0 0 16px;font-size:15px;line-height:1.5;">
        Stimate / Stimată{{ $name ? ' ' . $name : '' }},
    </p>

    <p style="margin:0 0 16px;font-size:15px;line-height:1.5;">
        Vă reamintim că invitația de a deveni Membru Fondator al Academiei The Romanian Music Industry Awards este în continuare deschisă.
    </p>

    <p style="margin:0 0 24px;font-size:15px;line-height:1.5;">
        Dacă doriți să vă alăturați acestei inițiative dedicate recunoașterii excelenței în industria muzicală din România, vă puteți autentifica pe platformă pentru a confirma participarea și a parcurge pașii necesari înscrierii.
    </p>

    <x-email.button :href="$url">Autentificare în platformă</x-email.button>

    <p style="margin:0 0 16px;font-size:15px;line-height:1.5;">
        Participarea dumneavoastră poate contribui la construirea unei comunități reprezentative de profesioniști, implicate în desemnarea celor mai importante realizări ale industriei muzicale românești.
        Vă mulțumim pentru timpul acordat și sperăm să vă avem alături în Academia ROMIAS.
    </p>

    <p style="margin:0 0 24px;font-size:15px;line-height:1.5;">
        Cu considerație,<br>
        The ROMIAS Team<br>
        The Romanian Music Industry Awards
    </p>

    <p style="margin:0 0 16px;font-size:13px;color:#64748b;line-height:1.5;">
        Sau copiați acest link în browser:<br>
        <a href="{{ $url }}" style="color:#4f46e5;word-break:break-all;">{{ $url }}</a>
    </p>

    <p style="margin:0;font-size:13px;color:#64748b;line-height:1.5;">
        Linkul expiră în 48 de ore.
    </p>
</x-email.layout>
