<?php

namespace Dcplibrary\Requests\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * requests_safe_html() must neutralise untrusted HTML (ISBNdb synopsis/overview,
 * or values a patron could tamper with) while keeping basic formatting.
 */
class SafeHtmlTest extends TestCase
{
    public static function cases(): array
    {
        return [
            'event handler on allowed tag' => ['<p>Hi <b onclick="alert(1)">x</b></p>', '<p>Hi <b>x</b></p>'],
            'script tag'                   => ['a<script>alert(1)</script>b', 'aalert(1)b'],
            'img onerror'                  => ['<img src=x onerror=alert(1)>t', 't'],
            'javascript link'              => ['<a href="javascript:alert(1)">l</a>', 'l'],
            'svg onload'                   => ['<svg onload=alert(1)><i>i</i></svg>', '<i>i</i>'],
            'quoted > in attribute'        => ['<b title=">" onclick=x>z</b>', '<b>z</b>'],
            'style/class stripped'         => ['<p style="x" class=y>ok</p><br/>', '<p>ok</p><br>'],
            'plain text untouched'         => ['Just text & more', 'Just text & more'],
        ];
    }

    #[Test]
    #[DataProvider('cases')]
    public function it_sanitizes_html(string $input, string $expected): void
    {
        $this->assertSame($expected, requests_safe_html($input));
    }

    #[Test]
    public function it_passes_through_null_and_empty(): void
    {
        $this->assertNull(requests_safe_html(null));
        $this->assertSame('', requests_safe_html(''));
    }
}
