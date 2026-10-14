{{--
    A button that survives classic Outlook: Outlook draws the VML rounded rectangle (it ignores padding on
    links), every other client gets the styled link. VML needs a fixed width, estimated from the label.
--}}
@props(['href'])
@php
    $vmlWidth = max(200, mb_strlen(trim(strip_tags((string) $slot))) * 10 + 56);
@endphp
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
    <tr>
        <td align="center" style="padding:0 0 24px;">
            <!--[if mso]>
            <v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word" href="{{ $href }}" style="height:48px;v-text-anchor:middle;width:{{ $vmlWidth }}px;" arcsize="25%" stroke="f" fillcolor="#4f46e5">
                <w:anchorlock/>
                <center style="color:#ffffff;font-family:Arial,Helvetica,sans-serif;font-size:16px;font-weight:bold;">{{ $slot }}</center>
            </v:roundrect>
            <![endif]-->
            <!--[if !mso]><!-->
            <a href="{{ $href }}" style="display:inline-block;font-size:16px;font-weight:bold;color:#ffffff;text-decoration:none;padding:14px 28px;background:#4f46e5;border-radius:12px;">{{ $slot }}</a>
            <!--<![endif]-->
        </td>
    </tr>
</table>
