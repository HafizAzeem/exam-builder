<?php

namespace Tests\Unit\Support;

use App\Support\Phone;
use PHPUnit\Framework\TestCase;

class PhoneTest extends TestCase
{
    public function test_normalizes_pakistani_whatsapp_variants(): void
    {
        $this->assertSame('03211414695', Phone::normalize('03211414695'));
        $this->assertSame('03211414695', Phone::normalize('+92 321 1414695'));
        $this->assertSame('03211414695', Phone::normalize('923211414695'));
        $this->assertSame('03211414695', Phone::normalize('3211414695'));
        $this->assertNull(Phone::normalize('   '));
        $this->assertNull(Phone::normalize(null));
    }
}
