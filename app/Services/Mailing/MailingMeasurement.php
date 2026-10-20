<?php

namespace App\Services\Mailing;

use App\Models\MailingCampaign;
use App\Models\MailingDelivery;
use App\Models\MailingLink;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Meranie kliknutí a otvorení.
 *
 * Odkazy sa prepíšu na krátky token, cieľ je uložený u nás – presmerovanie
 * nikdy neberie adresu z požiadavky. Otvorenie je len odhad (načítaný
 * obrázok), bez cookies a bez ukladania IP.
 */
class MailingMeasurement
{
    public static function safeDestination(string $url): bool
    {
        $parts = parse_url($url);

        return filter_var($url, FILTER_VALIDATE_URL) !== false
            && ($parts['scheme'] ?? '') === 'https' && ! isset($parts['user']) && ! isset($parts['pass'])
            && ! preg_match('/[\x00-\x20\x7f]/', $url);
    }

    public function decorate(string $html, MailingCampaign $campaign, MailingDelivery $delivery, ?string $unsubscribe): string
    {
        if ($campaign->track_clicks) {
            $position = 0;

            // Len odkazy v úvodzovkách; pôvodné značky (aj tabuľky pre Outlook) ostanú.
            $html = preg_replace_callback('~<a\b([^>]*?)\bhref=(["\x27])(.*?)\2([^>]*)>(.*?)</a>~is', function ($m) use ($delivery, $unsubscribe, &$position) {
                $url = html_entity_decode($m[3], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $label = trim(html_entity_decode(strip_tags($m[5]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

                // Odhlásenie, tel:/mailto: a viditeľnú adresu nebalíme – text má sedieť s cieľom.
                if ($url === $unsubscribe || ! self::safeDestination($url)
                    || preg_match('~^(?:https?://|www\.)~i', $label)
                    || preg_match('~^[a-z0-9.-]+\.[a-z]{2,}(?:/\S*)?$~i', $label)) {
                    return $m[0];
                }

                if ($label === '' && preg_match('~\balt=(["\x27])(.*?)\1~is', $m[5], $alt)) {
                    $label = html_entity_decode($alt[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                }

                $link = MailingLink::firstOrCreate(
                    ['delivery_id' => $delivery->id, 'position' => ++$position],
                    ['token' => Str::random(64), 'url' => $url, 'label' => mb_substr($label ?: __('mailing.render.image_link'), 0, 255)],
                );

                $href = self::publicUrl('mailing.click', $link->token);

                return '<a'.$m[1].'href='.$m[2].e($href).$m[2].$m[4].'>'.$m[5].'</a>';
            }, $html) ?? $html;
        }

        $footer = '';

        if ($campaign->track_clicks || $campaign->track_opens) {
            $what = match (true) {
                $campaign->track_clicks && $campaign->track_opens => __('mailing.render.tracking_both'),
                $campaign->track_clicks => __('mailing.render.tracking_clicks'),
                default => __('mailing.render.tracking_opens'),
            };
            $footer = '<p style="text-align:center;font:11px Arial,sans-serif;color:#64748b">'.e($what).'</p>';
        }

        if ($campaign->track_opens) {
            $delivery->open_token ??= Str::random(64);
            $delivery->save();
            $footer .= '<img src="'.e(self::publicUrl('mailing.open', $delivery->open_token)).'" width="1" height="1" alt="" style="border:0" />';
        }

        return str_contains($html, '</body>') ? str_replace('</body>', $footer.'</body>', $html) : $html.$footer;
    }

    /** Odhlásenie jedným klikom vyžaduje HTTPS, aj za proxy s HTTP. */
    public static function publicUrl(string $route, string $token): string
    {
        return URL::secure(URL::route($route, ['token' => $token], false));
    }
}
