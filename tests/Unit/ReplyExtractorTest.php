<?php

namespace Tests\Unit;

use App\Services\Messaging\ReplyExtractor;
use PHPUnit\Framework\TestCase;

class ReplyExtractorTest extends TestCase
{
    public function test_gmail_anglicky(): void
    {
        $text = "Thanks, approved.\n\nOn Tue, Oct 15, 2026 at 10:00 AM Obchod <obchod@x.sk> wrote:\n> Grafika v prílohe";

        $this->assertSame('Thanks, approved.', (new ReplyExtractor)->extract($text));
    }

    public function test_gmail_zalomena_hlavicka(): void
    {
        $text = "Súhlasím.\n\nOn Tue, Oct 15, 2026 at 10:00 AM Obchod Zástavy\n<obchod@x.sk> wrote:\n> Grafika";

        $this->assertSame('Súhlasím.', (new ReplyExtractor)->extract($text));
    }

    public function test_slovensky_klient(): void
    {
        $text = "Dobrý deň,\nposielam logo.\n\nDňa 15. 10. 2026 o 10:00 Obchod napísal:\n> pôvodný text";

        $this->assertSame("Dobrý deň,\nposielam logo.", (new ReplyExtractor)->extract($text));
    }

    public function test_outlook(): void
    {
        $text = "Ok, beriem.\n\nFrom: Obchod <obchod@x.sk>\nSent: Tuesday, October 15, 2026 10:00\nTo: Ján\nSubject: Objednávka";

        $this->assertSame('Ok, beriem.', (new ReplyExtractor)->extract($text));
    }

    public function test_podpis_sa_odreze(): void
    {
        $this->assertSame('Áno.', (new ReplyExtractor)->extract("Áno.\n-- \nJán Novák\nriaditeľ"));
    }

    public function test_bez_citacie_vrati_cely_text(): void
    {
        $this->assertSame("Prvý riadok\nDruhý riadok", (new ReplyExtractor)->extract("Prvý riadok\nDruhý riadok\n"));
    }

    public function test_iba_citacia_vrati_cely_text(): void
    {
        $this->assertSame('> iba citácia', (new ReplyExtractor)->extract('> iba citácia'));
    }

    public function test_html_blockquote_sa_oznaci_ako_citacia(): void
    {
        $text = ReplyExtractor::htmlToText('<div>Vyhovuje.</div><blockquote><p>Pôvodná správa</p></blockquote>');

        $this->assertSame('Vyhovuje.', (new ReplyExtractor)->extract($text));
    }
}
