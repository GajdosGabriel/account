<?php

namespace Tests\Unit;

use App\Services\Invoicing\Qr\LzmaEncoder;
use App\Services\Invoicing\Qr\PayBySquareEncoder;
use PHPUnit\Framework\TestCase;

/**
 * PAY by square je jediné miesto v projekte, kde si sami píšeme binárny
 * formát. Preto sa testuje aj to, čo by inak bola "vnútornosť" –
 * ak sa LZMA stream pokazí, QR kód sa naskenuje a banka ho odmietne,
 * čo sa dozvieme až od naštvaného zákazníka.
 */
class PayBySquareTest extends TestCase
{
    /**
     * Ak je v systéme `xz`, overíme výstup skutočným dekodérom –
     * to je jediný test, ktorý naozaj dokazuje, že stream je platný.
     * Bez neho kontrolujeme aspoň štruktúru.
     */
    public function test_lzma_stream_sa_da_spatne_rozbalit(): void
    {
        $data = str_repeat("SK3112000000198742637541\t29.00\tEUR\t", 3);
        $compressed = (new LzmaEncoder)->compress($data);

        // Range coder vždy začína nulovým bajtom (init dekodéra)
        // a končí päťbajtovým flushom.
        $this->assertGreaterThan(5, strlen($compressed));
        $this->assertSame("\x00", $compressed[0]);

        $xz = trim((string) @shell_exec('command -v xz 2>/dev/null'));

        if ($xz === '') {
            $this->markTestSkipped('Na overenie skutočným dekodérom chýba nástroj xz.');
        }

        // Klasická LZMA1 hlavička: props + veľkosť slovníka + veľkosť výstupu.
        $header = chr(LzmaEncoder::propertiesByte())
            .pack('V', 1 << 17)
            .pack('P', strlen($data));

        $file = tempnam(sys_get_temp_dir(), 'lzma');
        file_put_contents($file, $header.$compressed);

        $decoded = (string) shell_exec(escapeshellarg($xz).' --format=lzma --decompress --stdout '.escapeshellarg($file).' 2>/dev/null');

        @unlink($file);

        $this->assertSame($data, $decoded);
    }

    public function test_properties_byte_zodpoveda_lc3_lp0_pb2(): void
    {
        // (pb * 5 + lp) * 9 + lc = (2*5 + 0) * 9 + 3 = 93 = 0x5D,
        // čo je štandardný LZMA props byte.
        $this->assertSame(0x5D, LzmaEncoder::propertiesByte());
    }

    public function test_kod_obsahuje_len_povolene_znaky(): void
    {
        $code = $this->encode();

        $this->assertMatchesRegularExpression('/^[0-9A-V]+$/', $code);
    }

    public function test_kod_sa_zmesti_do_qr_kodu(): void
    {
        $code = $this->encode();

        // QR verzia 15 v alfanumerickom režime s ECC M pojme cez 500 znakov.
        // Bežná platba sa vojde s veľkou rezervou.
        $this->assertLessThan(500, strlen($code));
    }

    public function test_rozdielne_platby_daju_rozdielne_kody(): void
    {
        $this->assertNotSame(
            $this->encode(['amount' => 29.0]),
            $this->encode(['amount' => 39.0]),
        );
    }

    public function test_diakritika_v_poznamke_nerozbije_kodovanie(): void
    {
        $code = $this->encode(['note' => 'Faktúra za predplatné – júl 2026']);

        $this->assertMatchesRegularExpression('/^[0-9A-V]+$/', $code);
    }

    /** @param array<string, mixed> $overrides */
    protected function encode(array $overrides = []): string
    {
        $encoder = new PayBySquareEncoder(new LzmaEncoder);

        return $encoder->encode(array_merge([
            'iban' => 'SK3112000000198742637541',
            'swift' => 'TATRSKBX',
            'amount' => 29.0,
            'currency' => 'EUR',
            'due_date' => '2026-08-21',
            'variable_symbol' => '20260001',
            'constant_symbol' => '0308',
            'invoice_id' => '20260001',
            'note' => 'Faktura 20260001',
            'beneficiary_name' => 'Moja firma, s. r. o.',
        ], $overrides));
    }
}
