@php
    /** @var string $url */
@endphp
<x-email.layout lang="ro" title="Link de vot The Romanian Music Industry Awards">
    <h1 style="margin:0 0 16px;font-size:22px;">Votează în cadrul the Romanian Music Industry Awards!</h1>

    <p style="margin:0 0 16px;font-size:15px;line-height:1.5;">
        Apasă butonul de mai jos pentru a-ți exprima votul. Linkul poate fi folosit o singură dată.
    </p>

    <p style="margin:0 0 24px;font-size:15px;line-height:1.5;">
        Mulțumim pentru implicarea în această ediție the Romanian Music Industry Awards. Vom reveni
        curând cu mai multe detalii despre Gala The Romanian Music Industry Awards.
    </p>

    <x-email.button :href="$url">Votează acum</x-email.button>

    <p style="margin:0 0 16px;font-size:13px;color:#64748b;line-height:1.5;">
        Sau copiază acest link în browser:<br>
        <a href="{{ $url }}" style="color:#4f46e5;word-break:break-all;">{{ $url }}</a>
    </p>

    <p style="margin:0 0 8px;font-size:13px;color:#64748b;line-height:1.5;">
        Linkul este valabil pe durata perioadei de vot și poate fi folosit o singură dată.
    </p>
    <p style="margin:0;font-size:13px;color:#64748b;line-height:1.5;">
        Dacă nu ai cerut acest link, poți ignora acest mesaj.
    </p>
</x-email.layout>
