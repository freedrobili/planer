<?php

namespace App\Support;

class Plural
{
    /** Plural::ru(3, 'пара', 'пары', 'пар') → «3 пары». */
    public static function ru(int $n, string $one, string $few, string $many): string
    {
        $mod10 = $n % 10;
        $mod100 = $n % 100;

        $word = match (true) {
            $mod10 === 1 && $mod100 !== 11 => $one,
            $mod10 >= 2 && $mod10 <= 4 && ($mod100 < 12 || $mod100 > 14) => $few,
            default => $many,
        };

        return "{$n} {$word}";
    }
}
