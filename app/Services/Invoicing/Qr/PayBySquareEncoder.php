<?php

namespace App\Services\Invoicing\Qr;

/**
 * Kódovanie platobného príkazu do reťazca PAY by square.
 *
 * PAY by square je slovenský štandard (STN 01 8888) – ten štvorec, ktorý
 * naskenuješ v mobilnej appke banky a máš predvyplnený prevodný príkaz.
 * Číta ho VÚB, Tatra banka, SLSP, ČSOB, mBank, 365.bank aj Revolut.
 *
 * Formát:
 *   1. dáta oddelené tabulátorom
 *   2. pred ne CRC32 (4 B, little endian)
 *   3. celé to skomprimované surovým LZMA1
 *   4. hlavička: 2 B typ/verzia + 2 B dĺžka nekomprimovaných dát
 *   5. base32 s abecedou 0-9A-V
 */
class PayBySquareEncoder
{
    private const ALPHABET = '0123456789ABCDEFGHIJKLMNOPQRSTUV';

    public function __construct(private readonly LzmaEncoder $lzma) {}

    /**
     * @param  array{
     *     iban: string, amount: float, currency?: string, due_date?: ?string,
     *     variable_symbol?: ?string, constant_symbol?: ?string, specific_symbol?: ?string,
     *     note?: ?string, invoice_id?: ?string, swift?: ?string,
     *     beneficiary_name?: ?string, beneficiary_street?: ?string, beneficiary_city?: ?string
     * }  $payment
     */
    public function encode(array $payment): string
    {
        $data = $this->serialize($payment);

        // CRC32 v little endian pred dáta.
        $checksum = pack('V', crc32($data));
        $payload = $checksum.$data;

        $compressed = $this->lzma->compress($payload);

        // 0x00 0x00 = BySquareType 0 (pay), verzia 0, DocumentType 0.
        $binary = "\x00\x00".pack('v', strlen($payload)).$compressed;

        return $this->base32($binary);
    }

    /**
     * @param  array<string, mixed>  $payment
     */
    protected function serialize(array $payment): string
    {
        $amount = (float) $payment['amount'];

        $fields = [
            (string) ($payment['invoice_id'] ?? ''),          // InvoiceID
            '1',                                              // počet platieb
            '1',                                              // paymentorder
            $amount > 0 ? number_format($amount, 2, '.', '') : '',
            strtoupper((string) ($payment['currency'] ?? 'EUR')),
            $this->date($payment['due_date'] ?? null),        // splatnosť YYYYMMDD
            $this->digits($payment['variable_symbol'] ?? null, 10),
            $this->digits($payment['constant_symbol'] ?? null, 4),
            $this->digits($payment['specific_symbol'] ?? null, 10),
            '',                                               // referencia platiteľa
            $this->text($payment['note'] ?? null, 140),       // správa pre prijímateľa
            '1',                                              // počet bankových účtov
            strtoupper(preg_replace('/\s+/', '', (string) $payment['iban']) ?: ''),
            strtoupper((string) ($payment['swift'] ?? '')),
            '0',                                              // trvalý príkaz
            '0',                                              // inkaso
            $this->text($payment['beneficiary_name'] ?? null, 70),
            $this->text($payment['beneficiary_street'] ?? null, 70),
            $this->text($payment['beneficiary_city'] ?? null, 70),
        ];

        return implode("\t", $fields);
    }

    /** Base32 podľa by square: 5-bitové skupiny, abeceda 0-9A-V, bez výplne. */
    protected function base32(string $binary): string
    {
        $bits = '';

        foreach (str_split($binary) as $char) {
            $bits .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        }

        // Doplnenie na násobok piatich bitov.
        if ($remainder = strlen($bits) % 5) {
            $bits .= str_repeat('0', 5 - $remainder);
        }

        $output = '';

        foreach (str_split($bits, 5) as $chunk) {
            $output .= self::ALPHABET[bindec($chunk)];
        }

        return $output;
    }

    protected function date(?string $date): string
    {
        if (blank($date)) {
            return '';
        }

        return date('Ymd', strtotime($date));
    }

    protected function digits(?string $value, int $max): string
    {
        return substr(preg_replace('/\D+/', '', (string) $value) ?: '', 0, $max);
    }

    /**
     * Diakritika sa v QR kóde neprenáša spoľahlivo – banky ju často
     * zobrazia ako otázniky. Prevedieme na ASCII.
     */
    protected function text(?string $value, int $max): string
    {
        if (blank($value)) {
            return '';
        }

        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
        $ascii = preg_replace('/[^\x20-\x7E]/', '', $ascii) ?? '';

        return substr(trim(preg_replace('/\s+/', ' ', $ascii) ?? ''), 0, $max);
    }
}
