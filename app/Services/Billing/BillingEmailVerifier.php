<?php

namespace App\Services\Billing;

use App\Mail\BillingEmailVerificationMail;
use App\Models\AuditLog;
use App\Models\Organization;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

/**
 * Overenie fakturačného e-mailu.
 *
 * Bez neho stačí preklep v adrese na to, aby faktúra s IČO, sídlom a sumami
 * skončila v cudzej schránke – a nikto sa to nedozvie, lebo odoslanie
 * prebehne úspešne.
 *
 * Overovací odkaz je podpísaná URL s platnosťou. Netreba naň tabuľku tokenov
 * a nedá sa použiť po zmene adresy, lebo adresa je súčasťou podpisu.
 */
class BillingEmailVerifier
{
    /** Ako dlho platí odkaz v e-maile. */
    public const LINK_TTL_DAYS = 7;

    /** Najkratší odstup medzi dvoma odoslaniami tej istej firme. */
    public const RESEND_AFTER_MINUTES = 15;

    /**
     * Pošle overovací e-mail, ak treba.
     *
     * Volá sa po každom uložení firmy, preto sama rozhoduje, či je čo posielať –
     * volajúci nemusí riešiť, či sa adresa zmenila.
     *
     * @param  bool  $force  Ručné „poslať znova“ – preskočí čakaciu lehotu.
     * @return bool Či sa e-mail naozaj odoslal.
     */
    public function sendIfNeeded(Organization $organization, bool $force = false): bool
    {
        $email = $organization->billingEmail();

        if (blank($email) || $organization->hasVerifiedBillingEmail()) {
            return false;
        }

        if (! $force && $this->sentRecently($organization)) {
            return false;
        }

        try {
            Mail::to($email)->send(new BillingEmailVerificationMail($organization, $this->link($organization, $email)));
        } catch (\Throwable $e) {
            // Firma je uložená, len sa nepodarilo odoslať overenie. Zhodiť
            // celú požiadavku by znamenalo, že sa údaje neuložia kvôli
            // e-mailu, ktorý sa dá poslať znova jedným klikom.
            Log::warning('Overovaci e-mail sa nepodarilo odoslat', [
                'organization' => $organization->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }

        $organization->forceFill(['billing_email_verification_sent_at' => now()])->save();

        AuditLog::record(
            'organization.billing_email.verification_sent',
            $organization,
            ['email' => $email],
            $organization->id,
        );

        return true;
    }

    /**
     * Prijatie kliknutia z e-mailu.
     *
     * Podpis URL overil middleware `signed`; tu sa kontroluje už len to,
     * či medzitým adresa nezmenila majiteľa.
     */
    public function confirm(Organization $organization, string $hash): bool
    {
        $email = $organization->billingEmail();

        if (blank($email) || ! hash_equals($this->hash($email), $hash)) {
            return false;
        }

        $organization->markBillingEmailVerified($email);

        AuditLog::record(
            'organization.billing_email.verified',
            $organization,
            ['email' => $email],
            $organization->id,
        );

        return true;
    }

    public function sentRecently(Organization $organization): bool
    {
        return $organization->billing_email_verification_sent_at !== null
            && $organization->billing_email_verification_sent_at->gt(now()->subMinutes(self::RESEND_AFTER_MINUTES));
    }

    protected function link(Organization $organization, string $email): string
    {
        return URL::temporarySignedRoute(
            'organizations.billing-email.verify',
            now()->addDays(self::LINK_TTL_DAYS),
            ['organization' => $organization->uuid, 'hash' => $this->hash($email)],
        );
    }

    /**
     * Adresa vstupuje do podpisu, nie do URL. Odkaz tak prestane platiť,
     * keď sa e-mail zmení – a zároveň sa adresa nikde neukazuje, takže sa
     * nedostane do logov servera ani do histórie prehliadača.
     */
    protected function hash(string $email): string
    {
        return sha1(mb_strtolower(trim($email)));
    }
}
