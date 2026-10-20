{{-- Štandardný blok kampane, ktorý layout vkladá cez {{{content}}}. --}}
@if($contact_name)<p style="margin:0 0 16px">{{ __('mailing.render.greeting', ['name' => $contact_name]) }}</p>@endif
<div style="font-size:16px;line-height:1.7">{!! $body !!}</div>
@if($coupon_code)
<div style="margin:24px 0;padding:22px;background:#ecf8f1;border:1px dashed #57a389;text-align:center">{{ __('mailing.render.coupon') }}<br><strong style="font-size:26px;letter-spacing:3px">{{ $coupon_code }}</strong>@if($coupon_valid_to)<br><small>{{ __('mailing.render.coupon_valid_to', ['date' => $coupon_valid_to]) }}</small>@endif</div>
@endif
@if($button_url)
<p style="margin-top:30px"><a href="{{ $button_url }}" style="display:inline-block;padding:15px 24px;background:#117866;color:#ffffff;border-radius:8px;text-decoration:none">{{ $button_label }}</a></p>
@endif
