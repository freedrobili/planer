<?php

namespace Tests\Unit\Support;

use App\Support\Appearance;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

class AppearanceTest extends TestCase
{
    #[TestWith(['#2e49d6', '#ffffff'])]
    #[TestWith(['#1d2333', '#ffffff'])]
    #[TestWith(['#f5d90a', '#111318'])]
    #[TestWith(['#ffffff', '#111318'])]
    public function test_button_text_color_is_the_more_readable_one(string $background, string $expected): void
    {
        $text = Appearance::readableTextOn($background);

        $this->assertSame($expected, $text);
    }

    public function test_unknown_values_fall_back_to_defaults(): void
    {
        $appearance = Appearance::fromInput(['theme' => 'sepia', 'accent' => 'blue', 'day_start' => 30]);

        $this->assertSame(Appearance::DEFAULTS, $appearance->all());
    }

    public function test_day_end_is_never_before_day_start(): void
    {
        $appearance = Appearance::fromInput(['day_start' => 20, 'day_end' => 8]);

        $this->assertSame(20, $appearance->dayEnd());
    }

    public function test_system_font_needs_no_web_fonts(): void
    {
        $appearance = Appearance::fromInput(['font' => 'system']);

        $this->assertNull($appearance->fontsUrl());
    }
}
