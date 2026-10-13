@php
    /** @var string $editionName */
    /** @var int $daysBefore */
    /** @var string $deadline */
    /** @var string $url */
@endphp
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nominalizările {{ $editionName }} se încheie în curând</title>
</head>
<body style="margin:0;padding:0;background:#0f0f1a;font-family:Arial,Helvetica,sans-serif;color:#1e1b4b;">
    <div style="max-width:480px;margin:0 auto;padding:32px 24px;">
        <div style="background:#ffffff;border-radius:16px;padding:32px;">
            <h1 style="margin:0 0 16px;font-size:22px;">
                {{ $daysBefore === 1 ? 'Mâine se încheie nominalizările!' : "Mai sunt {$daysBefore} zile pentru nominalizări!" }}
            </h1>

            <p style="margin:0 0 24px;font-size:15px;line-height:1.5;">
                Încă nu ai trimis nominalizările tale pentru {{ $editionName }}. Termenul-limită este
                <strong>{{ $deadline }}</strong> (ora României). Nominalizările salvate, dar netrimise,
                nu sunt luate în calcul.
            </p>

            <div style="text-align:center;margin:0 0 24px;">
                <a href="{{ $url }}" style="display:inline-block;font-size:16px;font-weight:bold;color:#ffffff;text-decoration:none;padding:14px 28px;background:#4f46e5;border-radius:12px;">
                    Trimite nominalizările
                </a>
            </div>

            <p style="margin:0;font-size:13px;color:#64748b;line-height:1.5;">
                Primești acest mesaj pentru că ești membru al Academiei The Romanian Music Industry Awards.
            </p>
        </div>
    </div>
</body>
</html>
