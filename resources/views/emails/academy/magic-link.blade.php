@php
    /** @var string $name */
    /** @var string $url */
@endphp
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Link de autentificare Romias</title>
</head>
<body style="margin:0;padding:0;background:#0f0f1a;font-family:Arial,Helvetica,sans-serif;color:#1e1b4b;">
    <div style="max-width:480px;margin:0 auto;padding:32px 24px;">
        <div style="background:#ffffff;border-radius:16px;padding:32px;">
            <h1 style="margin:0 0 16px;font-size:22px;">Salut{{ $name ? ', ' . $name : '' }}!</h1>

            <p style="margin:0 0 24px;font-size:15px;line-height:1.5;">
                Apasă butonul de mai jos pentru a te autentifica în contul tău din Academia Romias.
            </p>

            <div style="text-align:center;margin:0 0 24px;">
                <a href="{{ $url }}" style="display:inline-block;font-size:16px;font-weight:bold;color:#ffffff;text-decoration:none;padding:14px 28px;background:#4f46e5;border-radius:12px;">
                    Autentifică-te
                </a>
            </div>

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
        </div>
    </div>
</body>
</html>
