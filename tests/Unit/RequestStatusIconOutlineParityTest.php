<?php

namespace Dcplibrary\Requests\Tests\Unit;

use Dcplibrary\Requests\Support\HeroiconsOutlinePaths;
use Dcplibrary\Requests\Support\RequestStatusIconCatalog;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Every solid icon in the status picker must have outline SVG paths so *-outline names render.
 */
class RequestStatusIconOutlineParityTest extends TestCase
{
    #[Test]
    public function every_catalog_solid_has_outline_paths_and_non_empty_paths(): void
    {
        $outlineLib = HeroiconsOutlinePaths::all();

        foreach (array_keys(RequestStatusIconCatalog::solidLabels()) as $key) {
            $this->assertArrayHasKey(
                $key,
                $outlineLib,
                "Add outline paths for [{$key}] in HeroiconsOutlinePaths (Heroicons v2 outline 24)."
            );
            $this->assertNotSame(
                [],
                $outlineLib[$key],
                "Outline paths for [{$key}] must not be empty."
            );
        }
    }
}
