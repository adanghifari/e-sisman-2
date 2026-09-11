<?php

namespace Tests\Unit;

use App\Models\Document;
use PHPUnit\Framework\TestCase;

class DocumentRevisionFormatTest extends TestCase
{
    public function test_revision_number_rolls_over_every_one_hundred_versions(): void
    {
        $this->assertSame('00.00', Document::formatRevisionNumber(0));
        $this->assertSame('00.99', Document::formatRevisionNumber(99));
        $this->assertSame('01.00', Document::formatRevisionNumber(100));
        $this->assertSame('09.99', Document::formatRevisionNumber(999));
        $this->assertSame('10.00', Document::formatRevisionNumber(1000));
    }
}
