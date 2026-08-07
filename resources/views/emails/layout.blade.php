@php
    /** @var array<string, mixed> $supplier */
    $accent = $accent ?? '#4f46e5';
@endphp
<!DOCTYPE html>
<html lang="sk">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>{{ $title ?? '' }}</title>
</head>
<body style="margin:0; padding:0; background:#f1f5f9; -webkit-font-smoothing:antialiased;">

{{-- Náhľadový text v zozname správ – nezobrazí sa v tele e-mailu. --}}
<div style="display:none; max-height:0; overflow:hidden; opacity:0;">
    {{ $preview ?? '' }}
    &#8199;&#65279;&#847;&#8199;&#65279;&#847;&#8199;&#65279;&#847;&#8199;&#65279;&#847;&#8199;&#65279;&#847;
</div>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f1f5f9;">
    <tr>
        <td align="center" style="padding:32px 16px;">

            <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0"
                   style="width:100%; max-width:600px; font-family:-apple-system,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif;">

                {{-- Hlavička --}}
                <tr>
                    <td style="padding:0 0 18px 4px;">
                        <span style="font-size:17px; font-weight:700; color:#0f172a; letter-spacing:-.3px;">
                            {{ $supplier['name'] }}
                        </span>
                    </td>
                </tr>

                {{-- Karta --}}
                <tr>
                    <td style="background:#ffffff; border-radius:14px; border:1px solid #e2e8f0; overflow:hidden;">

                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                            <tr>
                                <td style="height:4px; background:{{ $accent }}; font-size:0; line-height:0;">&nbsp;</td>
                            </tr>
                            <tr>
                                <td style="padding:30px 32px 32px;">
                                    {{ $slot }}
                                </td>
                            </tr>
                        </table>

                    </td>
                </tr>

                {{-- Pätka --}}
                <tr>
                    <td style="padding:22px 8px 0; color:#94a3b8; font-size:12px; line-height:1.6;">
                        <strong style="color:#64748b;">{{ $supplier['name'] }}</strong><br>
                        {{ $supplier['street'] }}, {{ $supplier['postal_code'] }} {{ $supplier['city'] }}<br>
                        IČO {{ $supplier['ico'] }}
                        @if ($supplier['ic_dph'] ?? null) · IČ DPH {{ $supplier['ic_dph'] }} @endif
                        @if ($supplier['email'] ?? null)
                            <br><a href="mailto:{{ $supplier['email'] }}" style="color:#94a3b8;">{{ $supplier['email'] }}</a>
                        @endif
                        @if ($supplier['phone'] ?? null) · {{ $supplier['phone'] }} @endif
                    </td>
                </tr>

            </table>

        </td>
    </tr>
</table>

</body>
</html>
