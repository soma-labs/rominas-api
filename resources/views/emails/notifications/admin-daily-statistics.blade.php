@php
    /** @var string $title */
    /** @var string $day */
    /** @var list<array{metric: string, day: int|null, total: int}> $rows */
    /** @var string $url */
@endphp
<x-email.layout lang="en" :title="$title">
    <h1 style="margin:0 0 16px;font-size:22px;">{{ $title }}</h1>

    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;border-collapse:collapse;font-size:14px;">
        <thead>
            <tr>
                <th style="text-align:left;padding:8px 0;border-bottom:1px solid #e2e8f0;"></th>
                <th style="text-align:right;padding:8px 0;border-bottom:1px solid #e2e8f0;">{{ $day }}</th>
                <th style="text-align:right;padding:8px 0 8px 12px;border-bottom:1px solid #e2e8f0;">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                <tr>
                    <td style="padding:8px 0;border-bottom:1px solid #f1f5f9;">{{ $row['metric'] }}</td>
                    <td style="text-align:right;padding:8px 0;border-bottom:1px solid #f1f5f9;">{{ $row['day'] ?? '–' }}</td>
                    <td style="text-align:right;padding:8px 0 8px 12px;border-bottom:1px solid #f1f5f9;font-weight:bold;">{{ $row['total'] }}</td>
                </tr>
            @endforeach
            <tr>
                <td colspan="3" style="height:24px;line-height:24px;font-size:0;">&nbsp;</td>
            </tr>
        </tbody>
    </table>

    <x-email.button :href="$url">Open the reports</x-email.button>

    <p style="margin:0;font-size:13px;color:#64748b;line-height:1.5;">
        You receive this message because your Admin account can see the reports.
    </p>
</x-email.layout>
