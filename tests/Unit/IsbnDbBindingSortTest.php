<?php

namespace Dcplibrary\Requests\Tests\Unit;

use Dcplibrary\Requests\Services\IsbnDbService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Verifies ISBNdb rows are ordered print-first so patrons see physical books before audiobooks.
 */
class IsbnDbBindingSortTest extends TestCase
{
    #[Test]
    public function it_sorts_audiobook_after_hardcover(): void
    {
        $ref = new ReflectionClass(IsbnDbService::class);
        $svc = $ref->newInstanceWithoutConstructor();
        $m   = $ref->getMethod('prioritizePrintOverDigitalAudio');
        $m->setAccessible(true);

        $books = [
            ['binding' => 'Audiobook', 'title' => 'Same Work'],
            ['binding' => 'Hardcover', 'title' => 'Same Work'],
        ];

        $sorted = $m->invoke($svc, $books);

        $this->assertSame('Hardcover', $sorted[0]['binding']);
        $this->assertSame('Audiobook', $sorted[1]['binding']);
    }

    #[Test]
    public function it_leaves_single_row_unchanged(): void
    {
        $ref = new ReflectionClass(IsbnDbService::class);
        $svc = $ref->newInstanceWithoutConstructor();
        $m   = $ref->getMethod('prioritizePrintOverDigitalAudio');
        $m->setAccessible(true);

        $one = [['binding' => 'Audiobook', 'title' => 'X']];
        $this->assertSame($one, $m->invoke($svc, $one));
    }
}
