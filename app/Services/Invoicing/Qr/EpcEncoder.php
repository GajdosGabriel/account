<?php

namespace App\Services\Invoicing\Qr;

/**
 * EPC QR (EPC069-12) – európsky štandard pre SEPA prevody.
 *
 * Používajú ho rakúske, nemecké a holandské banky. Na Slovensku je
 * primárny PAY by square, ale ak fakturuješ do zahraničia, je slušné
 * ponúknuť aj tento. Formát je obyčajný text – žiadna kompresia.
 */
class EpcEncoder
{
    /**
     * @param  array{
     *     iban: string, amount: float, currency?: string, swift?: ?string,
     *     beneficiary_name?: ?string, variable_symbol?: ?string, note?: ?string
     * }  $payment
     */
    public function encode(array $payment): string
    {
        $amount = (float) $payment['amount'];
        $currency = strtoupper((string) ($payment['currency'] ?? 'EUR'));

        $lines = [
            'BCD',                                                   // service tag
            '002',                                                   // verzia
            '1',                                                     // UTF-8
            'SCT',                                                   // SEPA Credit Transfer
            strtoupper((string) ($payment['swift'] ?? '')),
            mb_substr((string) ($payment['beneficiary_name'] ?? ''), 0, 70),
            strtoupper(preg_replace('/\s+/', '', (string) $payment['iban']) ?: ''),
            $amount > 0 ? $currency.number_format($amount, 2, '.', '') : '',
            '',                                                      // účel platby (kód)
            mb_substr((string) ($payment['variable_symbol'] ?? ''), 0, 35),
            mb_substr((string) ($payment['note'] ?? ''), 0, 140),
        ];

        return rtrim(implode("\n", $lines), "\n");
    }
}
