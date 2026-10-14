@php
    /** @var string $editionName */
    /** @var int $daysBefore */
    /** @var string $deadline */
    /** @var string $url */
@endphp
<x-email.layout lang="ro" :title="'Nominalizările ' . $editionName . ' se încheie în curând'">
    <h1 style="margin:0 0 16px;font-size:22px;">
        {{ $daysBefore === 1 ? 'Mâine se încheie nominalizările!' : "Mai sunt {$daysBefore} zile pentru nominalizări!" }}
    </h1>

    <p style="margin:0 0 24px;font-size:15px;line-height:1.5;">
        Încă nu ai trimis nominalizările tale pentru {{ $editionName }}. Termenul-limită este
        <strong>{{ $deadline }}</strong> (ora României). Nominalizările salvate, dar netrimise,
        nu sunt luate în calcul.
    </p>

    <x-email.button :href="$url">Trimite nominalizările</x-email.button>

    <p style="margin:0;font-size:13px;color:#64748b;line-height:1.5;">
        Primești acest mesaj pentru că ești membru al Academiei The Romanian Music Industry Awards.
    </p>
</x-email.layout>
