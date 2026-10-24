<?php

namespace App\Http\Controllers;

use App\Models\MailingContact;
use App\Models\MailingDelivery;
use App\Models\MailingLink;
use App\Services\Mailing\MailingMeasurement;
use App\Services\Mailing\MailingWebhooks;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

/**
 * Odkazy v e-mailoch kampaní: odhlásenie, meranie kliknutia a otvorenia.
 *
 * Klikne na ne adresát, ktorý sa sem neprihlasuje – autentizáciu robí
 * náhodný token v adrese. Nič z toho neukladá IP, prehliadač ani cookies.
 */
class MailingPublicController extends Controller
{
    private const HEADERS = [
        'Cache-Control' => 'no-store, private',
        'Referrer-Policy' => 'no-referrer',
        'X-Robots-Tag' => 'noindex, nofollow',
    ];

    /** GET zobrazí potvrdenie, POST odhlási (aj „List-Unsubscribe-Post“ jedným klikom). */
    public function unsubscribe(Request $request, string $token, MailingWebhooks $webhooks): Response
    {
        // Odkaz zo staršieho mailu funguje aj pre kontakt, ktorý projekt medzitým odstránil.
        $contact = MailingContact::withTrashed()->where('token', $token)->with('product')->first();

        abort_unless($contact, 404);

        if ($request->isMethod('post') && ! $contact->unsubscribed_at) {
            $contact->forceFill(['unsubscribed_at' => now()])->save();

            MailingDelivery::query()->where('contact_id', $contact->id)->where('status', MailingDelivery::PENDING)
                ->update(['status' => MailingDelivery::SKIPPED, 'error' => __('mailing.errors.unsubscribed'), 'updated_at' => now()]);

            $webhooks->unsubscribed($contact);
        }

        return response()->view('mailing.unsubscribe', [
            'done' => (bool) $contact->unsubscribed_at,
            'product' => $contact->product?->name,
        ])->withHeaders(self::HEADERS);
    }

    public function click(Request $request, string $token): RedirectResponse
    {
        $link = MailingLink::query()->where('token', $token)->first();

        abort_unless($link && MailingMeasurement::safeDestination($link->url), 404);

        // Cieľ je výhradne z uloženej kampane; zlyhané meranie nesmie pokaziť odkaz.
        try {
            $delivery = $link->delivery;

            if ($delivery && $this->measurable($request, $delivery)) {
                DB::transaction(function () use ($link, $delivery) {
                    MailingLink::query()->whereKey($link->id)->whereNull('clicked_at')->update(['clicked_at' => now()]);
                    MailingLink::query()->whereKey($link->id)->increment('click_count');
                    MailingDelivery::query()->whereKey($delivery->id)->whereNull('clicked_at')->update(['clicked_at' => now()]);
                });
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()->away($link->url, 302)->withHeaders(self::HEADERS);
    }

    public function open(Request $request, string $token): Response
    {
        try {
            $delivery = MailingDelivery::query()->where('open_token', $token)->first();

            if ($delivery && $this->measurable($request, $delivery)) {
                DB::transaction(function () use ($delivery) {
                    MailingDelivery::query()->whereKey($delivery->id)->whereNull('opened_at')->update(['opened_at' => now()]);
                    MailingDelivery::query()->whereKey($delivery->id)->increment('open_count');
                });
            }
        } catch (\Throwable $e) {
            report($e);
        }

        // Rovnaký obrázok aj pre neznámy token – neprezradí, či adresa existuje.
        return response((string) base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'), 200, [
            ...self::HEADERS,
            'Content-Type' => 'image/gif',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Opatrný filter, nie dôkaz návštevy človeka: skenery odkazov
     * a prednačítanie sa nepočítajú, staré správy tiež nie.
     */
    private function measurable(Request $request, MailingDelivery $delivery): bool
    {
        return $request->isMethod('get')
            && in_array($delivery->status, [MailingDelivery::SENDING, MailingDelivery::SENT], true)
            && $delivery->attempted_at?->gte(now()->subDays((int) config('accounts.mailing.measure_days')))
            && ! preg_match('/bot|crawler|spider|scanner|safelinks|proofpoint|mimecast|barracuda|headless/i', $request->userAgent() ?? '')
            && ! str_contains(strtolower($request->header('Purpose', '').' '.$request->header('Sec-Purpose', '')), 'prefetch')
            && ! $delivery->contact?->unsubscribed_at;
    }
}
