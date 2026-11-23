<?php

namespace Tests\Unit;

use App\Support\Rut;
use PHPUnit\Framework\TestCase;

class RutTest extends TestCase
{
    public function test_valida_ruts_correctos(): void
    {
        $this->assertTrue(Rut::valido('12.345.678-5'));
        $this->assertTrue(Rut::valido('11111111-1'));
        $this->assertTrue(Rut::valido('7.775.735-k'));
    }

    public function test_rechaza_ruts_incorrectos(): void
    {
        $this->assertFalse(Rut::valido('12.345.678-9'));
        $this->assertFalse(Rut::valido(''));
        $this->assertFalse(Rut::valido('abc'));
    }

    public function test_formatea_y_enmascara(): void
    {
        $this->assertSame('12.345.678-5', Rut::formatear('123456785'));
        $this->assertSame('12.345.***-*', Rut::enmascarar('12.345.678-5'));
    }
}
