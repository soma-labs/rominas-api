@php
    /** @var string $title */
    /** @var string $day */
    /** @var list<array{metric: string, day: int|null, total: int}> $rows */
    /** @var string $url */
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }}</title>
</head>
<body style="margin:0;padding:0;background:#0f0f1a;font-family:Arial,Helvetica,sans-serif;color:#1e1b4b;">
    <div style="max-width:480px;margin:0 auto;padding:32px 24px;">
        <div style="background:#ffffff;border-radius:16px;padding:32px;">
            <h1 style="margin:0 0 16px;font-size:22px;">{{ $title }}</h1>

            <table style="width:100%;border-collapse:collapse;margin:0 0 24px;font-size:14px;">
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
                </tbody>
            </table>

            <div style="text-align:center;margin:0 0 24px;">
                <a href="{{ $url }}" style="display:inline-block;font-size:16px;font-weight:bold;color:#ffffff;text-decoration:none;padding:14px 28px;background:#4f46e5;border-radius:12px;">
                    Open the reports
                </a>
            </div>

            <p style="margin:0;font-size:13px;color:#64748b;line-height:1.5;">
                You receive this message because your Romias Admin account can see the reports.
            </p>
        </div>
    </div>
</body>
</html>
