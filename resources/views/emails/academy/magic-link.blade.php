@php
    /** @var string|null $name */
    /** @var string $url */
@endphp
<x-email.layout lang="ro" title="Link de autentificare în Academia The Romanian Music Industry Awards">
    <h1 style="margin:0 0 16px;font-size:22px;">Salut{{ $name ? ', ' . $name : '' }}!</h1>

    <p style="margin:0 0 16px;font-size:15px;line-height:1.5;">
        Bun venit în Academia The Romanian Music Industry Awards!
    </p>

    <p style="margin:0 0 24px;font-size:15px;line-height:1.5;">
        Apasă butonul de mai jos pentru a te autentifica în contul tău din Academia
        The Romanian Music Industry Awards.
    </p>

    <x-email.button :href="$url">Autentifică-te</x-email.button>

    <p style="margin:0 0 16px;font-size:13px;color:#64748b;line-height:1.5;">
        Sau copiază acest link în browser:<br>
        <a href="{{ $url }}" style="color:#4f46e5;word-break:break-all;">{{ $url }}</a>
    </p>

    <p style="margin:0 0 8px;font-size:13px;color:#64748b;line-height:1.5;">
        Linkul expiră în 15 minute.
    </p>
    <p style="margin:0;font-size:13px;color:#64748b;line-height:1.5;">
        Dacă nu ai cerut acest link, poți ignora acest mesaj.
    </p>
</x-email.layout>
