@php
    /** @var string|null $name */
    /** @var string $url */
    /** @var string|null $proposerName */
@endphp
<x-email.layout lang="en" title="Your invitation to The Romanian Music Industry Awards Academy is still open">
    <p style="margin:0 0 16px;font-size:15px;line-height:1.5;">
        Dear{{ $name ? ' ' . $name : '' }},
    </p>

    <p style="margin:0 0 16px;font-size:15px;line-height:1.5;">
        We would like to remind you that your invitation to become a Founding Member of the ROMIAS Academy - The Romanian Music Industry Awards is still open.
    </p>

    <p style="margin:0 0 24px;font-size:15px;line-height:1.5;">
        If you would like to join this initiative dedicated to recognising excellence across Romania’s music industry, you can log in to the platform to confirm your participation and complete the registration process.
    </p>

    <x-email.button :href="$url">Log in to the Platform</x-email.button>

    <p style="margin:0 0 16px;font-size:15px;line-height:1.5;">
        Your participation can help build a representative community of music industry professionals committed to recognising and celebrating the most outstanding achievements in the Romanian music industry.
        Thank you for your time and consideration. We sincerely hope to welcome you to the ROMIAS Academy.
    </p>

    <p style="margin:0 0 24px;font-size:15px;line-height:1.5;">
        Kind regards,<br>
        The ROMIAS Team<br>
        The Romanian Music Industry Awards
    </p>

    <p style="margin:0 0 16px;font-size:13px;color:#64748b;line-height:1.5;">
        Or copy this link into your browser:<br>
        <a href="{{ $url }}" style="color:#4f46e5;word-break:break-all;">{{ $url }}</a>
    </p>

    <p style="margin:0;font-size:13px;color:#64748b;line-height:1.5;">
        The link expires in 48 hours.
    </p>
</x-email.layout>
