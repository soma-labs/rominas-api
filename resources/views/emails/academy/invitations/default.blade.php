@php
    /** @var string|null $name */
    /** @var string $url */
    /** @var string|null $proposerName */
@endphp
<x-email.layout lang="ro" title="Invitație în Academia The Romanian Music Industry Awards">
    <h1 style="margin:0 0 16px;font-size:22px;">Salut{{ $name ? ', ' . $name : '' }}!</h1>

    <p style="margin:0 0 16px;font-size:15px;line-height:1.5;">
        Ai fost invitat(ă) să faci parte din Academia The Romanian Music Industry Awards{{ $proposerName ? ' de către ' . $proposerName : '' }}.
        Apasă butonul de mai jos pentru a-ți activa contul și a intra în panoul de nominalizări.
    </p>

    <p style="margin:0 0 24px;font-size:15px;line-height:1.5;">
        Ne bucurăm să te avem alături!
    </p>

    <x-email.button :href="$url">Activează contul</x-email.button>

    <p style="margin:0 0 16px;font-size:13px;color:#64748b;line-height:1.5;">
        Sau copiază acest link în browser:<br>
        <a href="{{ $url }}" style="color:#4f46e5;word-break:break-all;">{{ $url }}</a>
    </p>

    <p style="margin:0 0 8px;font-size:13px;color:#64748b;line-height:1.5;">
        Linkul expiră în 48 de ore.
    </p>
    <p style="margin:0;font-size:13px;color:#64748b;line-height:1.5;">
        Dacă nu te așteptai la această invitație, poți ignora acest mesaj.
    </p>
</x-email.layout>
