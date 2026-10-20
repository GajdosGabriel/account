<!doctype html>
<html lang="{{ app()->getLocale() }}"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex"><title>{{ __('mailing.unsubscribe.title') }}</title></head>
<body style="margin:0;background:#f1f5f9;font-family:Arial,sans-serif;color:#163047;padding:40px 16px">
<main style="max-width:480px;margin:40px auto;background:#ffffff;padding:32px;border-radius:16px">
<p style="margin:0 0 8px;color:#64748b;font-size:13px;letter-spacing:1px;text-transform:uppercase">{{ $product }}</p>
<h1 style="margin:0 0 16px;font-size:24px">{{ $done ? __('mailing.unsubscribe.done_title') : __('mailing.unsubscribe.title') }}</h1>
@if($done)
<p>{{ __('mailing.unsubscribe.done_text') }}</p>
@else
<p>{{ __('mailing.unsubscribe.confirm_text') }}</p>
<form method="post"><button style="background:#117866;color:#ffffff;border:0;padding:16px 20px;border-radius:8px;cursor:pointer;font-size:15px">{{ __('mailing.unsubscribe.button') }}</button></form>
@endif
</main></body></html>
