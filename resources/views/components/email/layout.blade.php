{{--
    The shell every e-mail shares: a dark page with a white 480px card. Built from tables with `bgcolor`
    and a fixed `width`, because classic Outlook (Word's engine) ignores `max-width`, auto margins and
    backgrounds/padding on divs; other clients use the inline styles and stay fluid on phones.
--}}
@props(['lang', 'title'])
<!DOCTYPE html>
<html lang="{{ $lang }}" xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="color-scheme" content="light">
    <meta name="supported-color-schemes" content="light">
    <title>{{ $title }}</title>
    <!--[if mso]>
    <noscript>
        <xml>
            <o:OfficeDocumentSettings>
                <o:PixelsPerInch>96</o:PixelsPerInch>
            </o:OfficeDocumentSettings>
        </xml>
    </noscript>
    <style>
        table, td, h1, p, a, th { font-family: Arial, Helvetica, sans-serif !important; }
    </style>
    <![endif]-->
    <style>
        table { border-collapse: collapse; mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
    </style>
</head>
<body style="margin:0;padding:0;background:#0f0f1a;font-family:Arial,Helvetica,sans-serif;color:#1e1b4b;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#0f0f1a" style="width:100%;background:#0f0f1a;">
        <tr>
            <td align="center" style="padding:32px 24px;">
                <table role="presentation" width="480" align="center" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:480px;">
                    <tr>
                        <td bgcolor="#ffffff" style="background:#ffffff;border-radius:16px;padding:32px;font-family:Arial,Helvetica,sans-serif;color:#1e1b4b;">
                            {{ $slot }}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
