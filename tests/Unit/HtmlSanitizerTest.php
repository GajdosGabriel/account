<?php

namespace Tests\Unit;

use App\Services\Mailing\HtmlSanitizer;
use PHPUnit\Framework\TestCase;

class HtmlSanitizerTest extends TestCase
{
    public function test_keeps_colors_alignment_and_size_from_editor(): void
    {
        $html = '<p style="text-align: center"><span style="color: rgb(220, 38, 38); background-color: #fef08a; font-size: 18px">Akcia</span></p>';

        $this->assertSame(
            '<p style="text-align: center"><span style="color: rgb(220, 38, 38); background-color: #fef08a; font-size: 18px">Akcia</span></p>',
            HtmlSanitizer::sanitize($html)
        );
    }

    public function test_drops_dangerous_styles_and_attributes(): void
    {
        $html = '<span style="background-image: url(https://x.test/a.png); color: red; background-color: url(x); position: fixed" onclick="x()">Text</span><img src="x">';

        $this->assertSame('<span style="color: red">Text</span>', HtmlSanitizer::sanitize($html));
    }

    public function test_removes_style_with_nothing_allowed(): void
    {
        $this->assertSame('<p>Text</p>', HtmlSanitizer::sanitize('<p style="position: absolute; behavior: expression(alert(1))">Text</p>'));
    }
}
