<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>DCPL Suggest for Purchase</title>
</head>
<body style="margin:0;padding:0;background-color:#f3f4f6;font-family:Arial,Helvetica,sans-serif;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0"
       style="background-color:#f3f4f6;padding:32px 16px;">
    <tr><td align="center">
        <table role="presentation" width="600" cellpadding="0" cellspacing="0"
               style="max-width:600px;width:100%;background:#ffffff;border-radius:8px;
                      overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,.12);">

            {{-- Header --}}
            <tr>
                <td style="background-color:#1d4ed8;padding:20px 28px;">
                    <p style="margin:0;color:#ffffff;font-size:18px;font-weight:bold;letter-spacing:-0.3px;">
                        Daviess County Public Library
                    </p>
                    <p style="margin:4px 0 0;color:#bfdbfe;font-size:13px;">
                        Suggest for Purchase
                    </p>
                </td>
            </tr>

            {{-- Body (HTML from settings, placeholders already replaced) --}}
            <tr>
                <td style="padding:28px;color:#374151;font-size:15px;line-height:1.7;">
                    {!! $body !!}
                </td>
            </tr>

            {{-- Footer --}}
            <tr>
                <td style="padding:16px 28px;background-color:#f9fafb;border-top:1px solid #e5e7eb;
                            font-size:12px;color:#9ca3af;">
                    Daviess County Public Library &mdash; Suggest for Purchase
                    &bull; This is an automated message, please do not reply.
                </td>
            </tr>

        </table>
    </td></tr>
</table>
</body>
</html>
