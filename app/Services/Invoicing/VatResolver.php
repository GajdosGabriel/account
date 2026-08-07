<?php

namespace App\Services\Invoicing;

use App\Models\Organization;

/**
 * Rozhodne, akú DPH má faktúra a aký zákonný text sa na ňu tlačí.
 *
 * Ide o digitálne služby (SaaS) fakturované z SR. Pravidlá:
 *
 *  - Dodávateľ nie je platiteľ  -> 0 %, „Nie sme platiteľmi DPH.“
 *  - SK odberateľ               -> základná sadzba 23 %
 *  - EÚ firma s platným IČ DPH  -> 0 %, prenesenie daňovej povinnosti (§ 15 ods. 1)
 *  - EÚ nezdaniteľná osoba      -> DPH krajiny spotreby (OSS); ak nie sme v OSS,
 *                                  fakturuje sa slovenská sadzba
 *  - Mimo EÚ                    -> 0 %, miesto dodania je mimo SR (§ 15/§ 16)
 *
 * Toto nie je daňové poradenstvo – je to bežné nastavenie, ktoré si
 * treba raz odsúhlasiť s účtovníkom a potom už len funguje.
 */
class VatResolver
{
    /**
     * @return array{rate: float, reverse_charge: bool, note: ?string, reason: string}
     */
    public function resolve(Organization $organization): array
    {
        if (! (bool) config('invoicing.supplier.vat_payer')) {
            return $this->result(0, false, 'Nie sme platiteľmi DPH.', 'supplier_not_vat_payer');
        }

        $home = strtoupper((string) config('invoicing.vat.home_country', 'SK'));
        $country = strtoupper($organization->country ?: $home);
        $standard = (float) config('invoicing.vat.standard', 23);

        // Tuzemsko – bežná faktúra so slovenskou DPH.
        if ($country === $home) {
            return $this->result($standard, false, null, 'domestic');
        }

        $isEu = in_array($country, config('invoicing.vat.eu_countries', []), true);

        // Tretie krajiny – miesto dodania je mimo SR.
        if (! $isEu) {
            return $this->result(
                0,
                false,
                'Miesto dodania služby je mimo územia SR. Faktúra bez DPH podľa § 15 ods. 1 zákona č. 222/2004 Z. z.',
                'export',
            );
        }

        // EÚ B2B s overeným IČ DPH – prenesenie daňovej povinnosti.
        if ($organization->vat_mode?->hasVatNumber() && filled($organization->ic_dph)) {
            return $this->result(
                0,
                true,
                'Prenesenie daňovej povinnosti – reverse charge. Daň odvádza príjemca služby podľa § 15 ods. 1 zákona č. 222/2004 Z. z.',
                'reverse_charge',
            );
        }

        // EÚ B2C – bez OSS registrácie sa fakturuje slovenská sadzba.
        if (! $organization->oss_registered) {
            return $this->result($standard, false, null, 'eu_b2c_domestic_rate');
        }

        // EÚ B2C s OSS – sadzba krajiny spotreby.
        $rate = $this->ossRate($country) ?? $standard;

        return $this->result(
            $rate,
            false,
            "Zdanené v krajine spotreby ({$country}) v režime OSS.",
            'oss',
        );
    }

    /**
     * Základné sadzby DPH v EÚ pre režim OSS.
     * Sadzby sa menia – tabuľku treba raz ročne skontrolovať.
     *
     * @return array<string, float>
     */
    public function euRates(): array
    {
        return [
            'AT' => 20, 'BE' => 21, 'BG' => 20, 'HR' => 25, 'CY' => 19, 'CZ' => 21,
            'DK' => 25, 'EE' => 22, 'FI' => 25.5, 'FR' => 20, 'DE' => 19, 'GR' => 24,
            'HU' => 27, 'IE' => 23, 'IT' => 22, 'LV' => 21, 'LT' => 21, 'LU' => 17,
            'MT' => 18, 'NL' => 21, 'PL' => 23, 'PT' => 23, 'RO' => 21, 'SK' => 23,
            'SI' => 22, 'ES' => 21, 'SE' => 25,
        ];
    }

    protected function ossRate(string $country): ?float
    {
        return $this->euRates()[$country] ?? null;
    }

    /**
     * @return array{rate: float, reverse_charge: bool, note: ?string, reason: string}
     */
    protected function result(float $rate, bool $reverseCharge, ?string $note, string $reason): array
    {
        return [
            'rate' => $rate,
            'reverse_charge' => $reverseCharge,
            'note' => $note,
            'reason' => $reason,
        ];
    }
}
