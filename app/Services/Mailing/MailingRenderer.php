<?php

namespace App\Services\Mailing;

use App\Models\ConversationMessage;
use App\Models\Mailbox;
use App\Models\MailingCampaign;
use App\Models\MailingContact;
use Illuminate\Support\Carbon;

/**
 * Vykreslenie e-mailu kampane.
 *
 * Layout je HTML uložené v databáze so značkami v štýle Mustache:
 *
 *   {{heading}}          text, escapuje sa (aj subject, unsubscribe_url,
 *                        unsubscribe_label, product_name, from_name, from_address,
 *                        contact_name, contact_email, coupon_code, button_url, button_label)
 *   {{{content}}}        HTML (štandardný blok: oslovenie, text, kupón, tlačidlo)
 *   {{{body}}}           len text kampane, ak si layout skladá blok sám
 *   {{#coupon_code}}…{{/coupon_code}}   sekcia len ak hodnota nie je prázdna
 *   {{^unsubscribe_url}}…{{/unsubscribe_url}}  sekcia len ak hodnota chýba
 *
 * V texte kampane sa nahrádzajú premenné udalosti v jednoduchých zátvorkách,
 * napr. {cislo_objednavky}. Neznáma premenná zostane viditeľná – v náhľade
 * je hneď jasné, čo projekt neposiela.
 */
class MailingRenderer
{
    /**
     * @param  array<string, mixed>  $variables  premenné udalosti z projektu
     */
    public function render(
        MailingCampaign $campaign,
        ?MailingContact $contact = null,
        array $variables = [],
        ?string $unsubscribeUrl = null,
    ): string {
        $mailbox = $campaign->mailbox ?? Mailbox::defaultFor($campaign->product);
        $body = self::fill(self::bodyHtml($campaign->body, 'margin:0 0 16px'), $variables);

        $values = [
            ...$this->scalars($variables),
            'subject' => self::fillText((string) $campaign->subject, $variables),
            'heading' => self::fillText((string) $campaign->heading, $variables),
            'body' => $body,
            'contact_name' => $contact?->name,
            'contact_email' => $contact?->email,
            'coupon_code' => $campaign->coupon_code,
            'coupon_valid_to' => $campaign->coupon_valid_to?->format('j. n. Y'),
            'button_url' => $campaign->button_url,
            'button_label' => $campaign->button_label ?: __('mailing.render.button'),
            'unsubscribe_url' => $unsubscribeUrl,
            'unsubscribe_label' => __('mailing.render.unsubscribe'),
            'product_name' => $campaign->product?->name,
            'from_name' => $mailbox?->from_name ?? $campaign->product?->name,
            'from_address' => $mailbox?->from_address,
        ];

        $values['content'] = view('mailing.content', $values)->render();

        return self::template($campaign->layout?->html ?? self::defaultLayout(), $values);
    }

    /**
     * Servisná odpoveď vo vlákne veci: text a pod ním citovaný systémový
     * mail, ako pri ručnej odpovedi z poštového klienta.
     *
     * @param  array<string, mixed>  $variables
     */
    public function renderReply(MailingCampaign $campaign, array $variables = [], ?ConversationMessage $quoted = null, bool $preview = false): string
    {
        $style = '';
        $quote = null;

        if ($quoted?->body_html) {
            preg_match('~<style[^>]*>(.*?)</style>~is', $quoted->body_html, $styleMatch);
            preg_match('~<body[^>]*>(.*)</body>~is', $quoted->body_html, $bodyMatch);
            // Pravidlo pre body by prefarbilo aj samotnú odpoveď nad citátom.
            $style = preg_replace('~(?<![.#\w-])body\s*\{[^}]*\}~i', '', $styleMatch[1] ?? '') ?? '';
            $quote = $bodyMatch[1] ?? $quoted->body_html;
        }

        return view('mailing.reply', [
            'body' => self::autolink(self::fill(self::bodyHtml($campaign->body, 'margin:0 0 12px'), $variables)),
            'quoteStyle' => $style,
            'quoteHtml' => $quote,
            'quotedAt' => $quoted ? Carbon::parse($quoted->sent_at ?? $quoted->created_at) : null,
            'quotedFrom' => $quoted?->from_email,
            'quotedName' => $quoted?->from_name,
            'showPlaceholder' => $preview && $campaign->quote_event && ! $quote,
        ])->render();
    }

    /**
     * Telo kampane do e-mailu. Text z HTML editora prejde bielou listinou;
     * staršie kampane sú čistý text s odsekmi oddelenými prázdnym riadkom.
     */
    public static function bodyHtml(?string $body, string $paragraphStyle): string
    {
        $body = trim((string) $body);
        $html = preg_match('/<(p|br|h2|h3|ul|ol|li|strong|b|em|i|u|a)\b/i', $body)
            ? HtmlSanitizer::sanitize($body)
            : collect(preg_split("/\r?\n\s*\r?\n/", $body))->filter(fn ($p) => trim($p) !== '')
                ->map(fn ($p) => '<p>'.nl2br(e(trim($p))).'</p>')->implode("\n");

        // E-mailoví klienti potrebujú štýl priamo v značke.
        return str_replace('<p>', '<p style="'.$paragraphStyle.'">', $html);
    }

    /**
     * Premenné udalosti v HTML: {cislo_objednavky} → escapovaná hodnota.
     *
     * @param  array<string, mixed>  $variables
     */
    public static function fill(string $html, array $variables): string
    {
        $map = [];

        foreach ($variables as $key => $value) {
            if (is_scalar($value) && preg_match('/^[a-z0-9_]+$/i', (string) $key)) {
                $map['{'.$key.'}'] = e((string) $value);
            }
        }

        return strtr($html, $map);
    }

    /** @param  array<string, mixed>  $variables */
    public static function fillText(string $text, array $variables): string
    {
        return html_entity_decode(self::fill($text, $variables), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Mustache bez logiky: sekcie podľa prázdnosti, {{x}} escapované, {{{x}}} surové.
     *
     * @param  array<string, mixed>  $values
     */
    public static function template(string $html, array $values): string
    {
        $html = preg_replace_callback('/\{\{([#^])\s*([a-z0-9_]+)\s*\}\}(.*?)\{\{\/\s*\2\s*\}\}/is', function ($m) use ($values) {
            $filled = filled($values[$m[2]] ?? null);

            return ($m[1] === '#') === $filled ? $m[3] : '';
        }, $html) ?? $html;

        $html = preg_replace_callback('/\{\{\{\s*([a-z0-9_]+)\s*\}\}\}/i', fn ($m) => (string) ($values[$m[1]] ?? ''), $html) ?? $html;

        return preg_replace_callback('/\{\{\s*([a-z0-9_]+)\s*\}\}/i', fn ($m) => e((string) ($values[$m[1]] ?? '')), $html) ?? $html;
    }

    public static function defaultLayout(): string
    {
        return (string) file_get_contents(resource_path('views/mailing/default-layout.html'));
    }

    /** Webové adresy napísané ako obyčajný text zmení na odkazy; existujúce odkazy nechá tak. */
    public static function autolink(string $html): string
    {
        $inLink = false;

        return implode('', array_map(function ($part) use (&$inLink) {
            if (str_starts_with($part, '<')) {
                $inLink = preg_match('~^<a\b~i', $part) ? true : (preg_match('~^</a\b~i', $part) ? false : $inLink);

                return $part;
            }

            // Interpunkcia na konci vety do adresy nepatrí.
            return $inLink ? $part : (preg_replace('~\bhttps?://[^\s<>"\']*[^\s<>"\'.,;:!?)]~iu', '<a href="$0" style="color:#1155cc">$0</a>', $part) ?? $part);
        }, preg_split('~(<[^>]+>)~', $html, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$html]));
    }

    /**
     * @param  array<string, mixed>  $variables
     * @return array<string, scalar>
     */
    private function scalars(array $variables): array
    {
        return array_filter($variables, fn ($v, $k) => is_scalar($v) && preg_match('/^[a-z0-9_]+$/i', (string) $k), ARRAY_FILTER_USE_BOTH);
    }
}
