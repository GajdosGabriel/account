@php
    /** @var \App\Models\ConversationMessage $inbound */
    $sender = $inbound->from_name ? $inbound->from_name . ' <' . $inbound->from_email . '>' : $inbound->from_email;
@endphp

@component('emails.layout', [
    'supplier' => $supplier,
    'title' => 'Nová správa v inboxe',
    'preview' => \Illuminate\Support\Str::limit($excerpt, 120),
])

    <p style="margin:0 0 6px; font-size:13px; color:#64748b;">
        Nová správa v inboxe{{ $product ? ' – ' . $product : '' }}
    </p>

    <h1 style="margin:0 0 16px; font-size:20px; line-height:1.3; font-weight:700; color:#0f172a; letter-spacing:-.3px;">
        {{ $inbound->subject ?: '(bez predmetu)' }}
    </h1>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
           style="margin:0 0 18px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px;">
        <tr>
            <td style="padding:12px 16px; font-size:13px; line-height:1.6; color:#64748b;">
                Od: <strong style="color:#0f172a;">{{ $sender }}</strong>
                @if ($label)
                    <br>Vec: <strong style="color:#0f172a;">{{ $label }}</strong>
                @endif
            </td>
        </tr>
    </table>

    <div style="margin:0 0 22px; font-size:14px; line-height:1.6; color:#334155; white-space:pre-line;">{{ $excerpt }}</div>

    <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:0;">
        <tr>
            <td style="border-radius:10px; background:#4f46e5;">
                <a href="{{ $url }}"
                   style="display:inline-block; padding:12px 24px; font-size:15px; font-weight:600;
                          color:#ffffff; text-decoration:none; border-radius:10px;">
                    Otvoriť v inboxe
                </a>
            </td>
        </tr>
    </table>

@endcomponent
