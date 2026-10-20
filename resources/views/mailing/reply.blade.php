<!doctype html>
<html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
@if($quoteStyle)<style>{!! $quoteStyle !!}</style>@endif
</head>
{{-- Odpoveď na systémový e-mail veci: text a pod ním citovaná pôvodná správa, ako pri ručnej
     odpovedi z poštového klienta. Triedu gmail_quote klienti poznajú ako citát a vedia ho zbaliť. --}}
<body style="margin:0;padding:0;font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#222">
<div dir="ltr">
{!! $body !!}
</div>
@if($quoteHtml)
<br>
<div class="gmail_quote">
<div dir="ltr" class="gmail_attr">{{ __('mailing.render.quote_header', [
    'date' => $quotedAt->locale(app()->getLocale())->isoFormat('dd D. M. YYYY, H:mm'),
    'name' => $quotedName ?: $quotedFrom,
]) }} &lt;<a href="mailto:{{ $quotedFrom }}">{{ $quotedFrom }}</a>&gt;<br></div>
<blockquote class="gmail_quote" style="margin:0 0 0 .8ex;border-left:1px solid #ccc;padding-left:1ex">
{!! $quoteHtml !!}
</blockquote>
</div>
@elseif($showPlaceholder)
<p style="margin:24px 0 0;font-size:13px;color:#666">{{ __('mailing.render.quote_placeholder') }}</p>
@endif
</body></html>
