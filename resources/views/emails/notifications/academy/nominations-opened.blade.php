@php
    /** @var string $editionName */
    /** @var string $deadline */
    /** @var string $url */
@endphp
<x-email.layout lang="ro" :title="'Nominalizările ' . $editionName . ' sunt deschise!'">
    <h1 style="margin:0 0 16px;font-size:22px;">Nominalizările pentru the Romanian Music Industry Awards sunt deschise!</h1>

    <p style="margin:0 0 24px;font-size:15px;line-height:1.5;">
        Poți trimite de acum nominalizările tale pentru {{ $editionName }}. Le poți salva și
        continua oricând până la termenul-limită: <strong>{{ $deadline }}</strong> (ora României).
    </p>

    <x-email.button :href="$url">Deschide panoul de nominalizări</x-email.button>

    <p style="margin:0;font-size:13px;color:#64748b;line-height:1.5;">
        Primești acest mesaj pentru că ești membru al Academiei The Romanian Music Industry Awards.
    </p>
</x-email.layout>
