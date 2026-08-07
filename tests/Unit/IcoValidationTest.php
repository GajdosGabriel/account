<?php

namespace Tests\Unit;

use App\Services\Registry\IcoLookupService;
use PHPUnit\Framework\TestCase;

class IcoValidationTest extends TestCase
{
    public function test_kontrolna_cislica_ico(): void
    {
        $service = new IcoLookupService;

        $this->assertTrue($service->isValidChecksum('31333532'));
        $this->assertFalse($service->isValidChecksum('31333533'));
        $this->assertFalse($service->isValidChecksum('123'));
    }

    public function test_normalizacia_doplni_nuly_zlava(): void
    {
        $service = new IcoLookupService;

        $this->assertSame('00123456', $service->normalize('123456'));
        $this->assertSame('12345678', $service->normalize('12 345 678'));
    }
}
