<?php

namespace Tests\Unit;

use App\Services\Mailing\ContactImportParser;
use PHPUnit\Framework\TestCase;

class ContactImportParserTest extends TestCase
{
    public function test_volne_riadky_s_menom_a_bez_neho(): void
    {
        [$rows, $skipped] = ContactImportParser::parse("Jana Nová <Jana@Firma.sk>\nfero@firma.sk\nnie-adresa@\n");

        $this->assertSame(['jana@firma.sk', 'fero@firma.sk'], array_keys($rows));
        $this->assertSame('Jana Nová', $rows['jana@firma.sk']['name']);
        $this->assertNull($rows['fero@firma.sk']['name']);
        $this->assertSame(1, $skipped);
    }

    public function test_csv_s_hlavickou_cita_stlpce_aj_stitky(): void
    {
        [$rows] = ContactImportParser::parse("\u{FEFF}meno;email;stitky\nJana;jana@firma.sk;VIP, Obec\n");

        $this->assertSame(['name' => 'Jana', 'labels' => ['VIP', 'Obec']], $rows['jana@firma.sk']);
    }

    public function test_duplicitna_adresa_sa_zapocita_raz(): void
    {
        [$rows] = ContactImportParser::parse('a@b.sk, A@B.sk; a@b.sk');

        $this->assertCount(1, $rows);
    }
}
