<?php
declare(strict_types=1);

namespace Projet\Fc25\Tests;

use PHPUnit\Framework\TestCase;
use MongoDB\BSON\UTCDateTime;

final class HelpersTest extends TestCase
{
    public function testYmdToMsReturnsNullOnEmpty(): void
    {
        $this->assertNull(ymdToMs(''));
        $this->assertNull(ymdToMs('   '));
    }

    public function testYmdToMsConvertsCorrectly(): void
    {
        $ms = ymdToMs('2025-02-15');
        $this->assertIsInt($ms);
        $this->assertGreaterThan(0, $ms);

        $this->assertSame('2025-02-15', msToYmd($ms));
    }

    public function testMsToYmdWorksWithUtcDateTime(): void
    {
        $utc = new UTCDateTime(1739577600000); // 2025-02-15T00:00:00Z
        $this->assertSame('2025-02-15', msToYmd($utc));
    }

    public function testMoneyToInt(): void
    {
        $this->assertSame(0, moneyToInt(''));
        $this->assertSame(123, moneyToInt('123'));
        $this->assertSame(1234567, moneyToInt('1 234 567'));
        $this->assertSame(999999, moneyToInt('999.999 €'));
    }

    public function testMakeSlug(): void
    {
        // ✅ makeSlug() garde les accents actuellement
        $this->assertSame('kylian-mbappé-91', makeSlug('Kylian Mbappé', 91));

        $this->assertSame('player-50', makeSlug('   ', 50));
        $this->assertSame('messi', makeSlug('Messi', null));
    }
}
