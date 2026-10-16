@php
    /** @var string|null $name */
    /** @var string $url */
    /** @var string|null $proposerName */
@endphp
<x-email.layout lang="en" title="Invitation to The Romanian Music Industry Awards Academy">
    <h1 style="margin:0 0 16px;font-size:22px;">Hello{{ $name ? ', ' . $name : '' }}!</h1>

    <p style="margin:0 0 16px;font-size:15px;line-height:1.5;">
        You have been invited to join The Romanian Music Industry Awards Academy{{ $proposerName ? ' by ' . $proposerName : '' }}.
        Press the button below to activate your account and open the nominations dashboard.
    </p>

    <p style="margin:0 0 24px;font-size:15px;line-height:1.5;">
        We are glad to have you with us!
    </p>

    <x-email.button :href="$url">Activate account</x-email.button>

    <p style="margin:0 0 16px;font-size:13px;color:#64748b;line-height:1.5;">
        Or copy this link into your browser:<br>
        <a href="{{ $url }}" style="color:#4f46e5;word-break:break-all;">{{ $url }}</a>
    </p>

    <p style="margin:0 0 8px;font-size:13px;color:#64748b;line-height:1.5;">
        The link expires in 48 hours.
    </p>
    <p style="margin:0;font-size:13px;color:#64748b;line-height:1.5;">
        If you were not expecting this invitation, you can ignore this message.
    </p>
</x-email.layout>
