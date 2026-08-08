@php
    /** @var \App\Models\Organization $organization */
    $email = $organization->billingEmail();
@endphp

@component('emails.layout', [
    'supplier' => $supplier,
    'title' => 'Potvrďte e-mail na faktúry',
    'preview' => 'Potvrďte, že na túto adresu môžeme posielať faktúry pre ' . $organization->name . '.',
    'accent' => '#0d9488',
])

    <p style="margin:0 0 6px; font-size:13px; color:#64748b;">Dobrý deň,</p>

    <h1 style="margin:0 0 6px; font-size:22px; line-height:1.25; font-weight:700; color:#0f172a; letter-spacing:-.4px;">
        Potvrďte e-mail na faktúry
    </h1>

    <p style="margin:0 0 22px; font-size:14px; line-height:1.6; color:#475569;">
        Táto adresa bola zadaná ako kontakt pre fakturáciu firmy
        <strong style="color:#0f172a;">{{ $organization->name }}</strong>.
        Potvrdením nám dáte vedieť, že schránka naozaj patrí vám a že
        faktúry chodia na správne miesto.
    </p>

    <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 22px;">
        <tr>
            <td style="border-radius:10px; background:#0d9488;">
                <a href="{{ $url }}"
                   style="display:inline-block; padding:13px 26px; font-size:15px; font-weight:600;
                          color:#ffffff; text-decoration:none; border-radius:10px;">
                    Potvrdiť adresu
                </a>
            </td>
        </tr>
    </table>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
           style="margin:0 0 22px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px;">
        <tr>
            <td style="padding:14px 16px; font-size:13px; line-height:1.6; color:#64748b;">
                Overuje sa adresa
                <strong style="color:#0f172a;">{{ $email }}</strong><br>
                Odkaz platí {{ \App\Services\Billing\BillingEmailVerifier::LINK_TTL_DAYS }} dní.
            </td>
        </tr>
    </table>

    {{-- Nikoho nenúťme hľadať, prečo mu prišiel e-mail, ktorý nečakal. --}}
    <p style="margin:0; font-size:13px; line-height:1.6; color:#94a3b8;">
        Ak ste o nič nežiadali, e-mail pokojne ignorujte – bez potvrdenia sa nič nestane
        a na túto adresu nič ďalšie nepošleme.
    </p>

@endcomponent
