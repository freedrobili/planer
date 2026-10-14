<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Разбор быстрого ввода задачи одной строкой:
 * «завтра 15:00-16:30 созвон #работа» → дата, время, теги и название.
 *
 * Понимает: сегодня / завтра / послезавтра, дни недели («в пятницу», «пт»),
 * даты «12.10», «12.10.2026», «12 октября»; время «15:00», «в 9», «в 9:30»;
 * интервалы «15:00-16:30», «с 15 до 17»; теги «#работа» (только существующие).
 */
class QuickInput
{
    private const LETTER_BEFORE = '(?<![\p{L}\p{N}])';

    private const LETTER_AFTER = '(?![\p{L}\p{N}])';

    private const WEEKDAYS = [
        'понедельник' => 1, 'пн' => 1,
        'вторник' => 2, 'вт' => 2,
        'среду' => 3, 'среда' => 3, 'ср' => 3,
        'четверг' => 4, 'чт' => 4,
        'пятницу' => 5, 'пятница' => 5, 'пт' => 5,
        'субботу' => 6, 'суббота' => 6, 'сб' => 6,
        'воскресенье' => 7, 'вс' => 7,
    ];

    private const MONTHS = [
        'января' => 1, 'февраля' => 2, 'марта' => 3, 'апреля' => 4, 'мая' => 5, 'июня' => 6,
        'июля' => 7, 'августа' => 8, 'сентября' => 9, 'октября' => 10, 'ноября' => 11, 'декабря' => 12,
    ];

    /**
     * @param  array<int, string>  $tags  существующие теги: id => название
     * @return array{title: string, date: ?string, time: ?string, ends_at: ?string, tags: list<int>}
     */
    public static function parse(string $text, Carbon $today, array $tags = []): array
    {
        $rest = ' '.trim($text).' ';
        $result = ['date' => null, 'time' => null, 'ends_at' => null, 'tags' => []];

        $rest = self::extractTags($rest, $tags, $result);
        $rest = self::extractTime($rest, $result);
        $rest = self::extractDate($rest, $today->copy()->startOfDay(), $result);

        $title = trim(preg_replace('/\s+/u', ' ', $rest) ?? '');

        return ['title' => $title !== '' ? $title : trim($text)] + $result;
    }

    /**
     * @param  array<int, string>  $tags
     * @param  array<string, mixed>  $result
     */
    private static function extractTags(string $rest, array $tags, array &$result): string
    {
        $known = [];
        foreach ($tags as $id => $name) {
            $known[self::normalizeTag($name)] = $id;
        }

        return preg_replace_callback('/(?<=\s)#([\p{L}\p{N}_-]+)(?=\s)/u', function (array $match) use ($known, &$result): string {
            $id = $known[self::normalizeTag($match[1])] ?? null;
            if ($id === null) {
                return $match[0];
            }
            $result['tags'][] = $id;

            return ' ';
        }, $rest) ?? $rest;
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private static function extractTime(string $rest, array &$result): string
    {
        $time = '(\d{1,2})(?::(\d{2}))?';
        $range = '/'.self::LETTER_BEFORE.'(с\s+)?'.$time.'\s*(?:-|–|—|до\s)\s*'.$time.self::LETTER_AFTER.'/u';

        if (preg_match($range, $rest, $match, PREG_OFFSET_CAPTURE)) {
            $hasPrefix = $match[1][0] !== '';
            $hasMinutes = ($match[3][0] ?? '') !== '' || ($match[5][0] ?? '') !== '';
            $start = self::clock($match[2][0], $match[3][0] ?? '');
            $end = self::clock($match[4][0], $match[5][0] ?? '');

            if (($hasPrefix || $hasMinutes) && $start !== null && $end !== null && $end > $start) {
                $result['time'] = $start;
                $result['ends_at'] = $end;

                return self::cut($rest, $match[0]);
            }
        }

        $single = '/'.self::LETTER_BEFORE.'(?:(в)\s+'.$time.'|(\d{1,2}):(\d{2}))'.self::LETTER_AFTER.'/u';
        if (preg_match($single, $rest, $match, PREG_OFFSET_CAPTURE)) {
            $clock = ($match[1][0] ?? '') !== ''
                ? self::clock($match[2][0], $match[3][0] ?? '')
                : self::clock($match[4][0], $match[5][0]);

            if ($clock !== null) {
                $result['time'] = $clock;

                return self::cut($rest, $match[0]);
            }
        }

        return $rest;
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private static function extractDate(string $rest, Carbon $today, array &$result): string
    {
        $relative = ['послезавтра' => 2, 'завтра' => 1, 'сегодня' => 0];
        foreach ($relative as $word => $days) {
            if (preg_match('/'.self::LETTER_BEFORE.$word.self::LETTER_AFTER.'/iu', $rest, $match, PREG_OFFSET_CAPTURE)) {
                $result['date'] = $today->copy()->addDays($days)->toDateString();

                return self::cut($rest, $match[0]);
            }
        }

        $months = implode('|', array_keys(self::MONTHS));
        if (preg_match('/'.self::LETTER_BEFORE.'(\d{1,2})\s+('.$months.')(?:\s+(\d{4}))?'.self::LETTER_AFTER.'/iu', $rest, $match, PREG_OFFSET_CAPTURE)) {
            $date = self::calendarDate((int) $match[1][0], self::MONTHS[mb_strtolower($match[2][0])], $match[3][0] ?? '', $today);
            if ($date !== null) {
                $result['date'] = $date;

                return self::cut($rest, $match[0]);
            }
        }

        if (preg_match('/(?<![\d.:])(\d{1,2})\.(\d{1,2})(?:\.(\d{2}|\d{4}))?(?![\d.:])/u', $rest, $match, PREG_OFFSET_CAPTURE)) {
            $date = self::calendarDate((int) $match[1][0], (int) $match[2][0], $match[3][0] ?? '', $today);
            if ($date !== null) {
                $result['date'] = $date;

                return self::cut($rest, $match[0]);
            }
        }

        $weekdays = implode('|', array_keys(self::WEEKDAYS));
        if (preg_match('/'.self::LETTER_BEFORE.'(?:во?\s+)?('.$weekdays.')'.self::LETTER_AFTER.'/iu', $rest, $match, PREG_OFFSET_CAPTURE)) {
            $target = self::WEEKDAYS[mb_strtolower($match[1][0])];
            $result['date'] = $today->copy()->addDays(($target - $today->dayOfWeekIso + 7) % 7)->toDateString();

            return self::cut($rest, $match[0]);
        }

        return $rest;
    }

    /**
     * Дата без года: ближайшая в будущем (сегодня или позже).
     */
    private static function calendarDate(int $day, int $month, string $year, Carbon $today): ?string
    {
        $fullYear = match (strlen($year)) {
            0 => $today->year,
            2 => 2000 + (int) $year,
            default => (int) $year,
        };

        if (! checkdate($month, $day, $fullYear)) {
            return null;
        }

        $date = Carbon::create($fullYear, $month, $day)->startOfDay();
        if ($year === '' && $date->lt($today)) {
            $date->addYear();
        }

        return $date->toDateString();
    }

    private static function clock(string $hours, string $minutes): ?string
    {
        $h = (int) $hours;
        $m = $minutes === '' ? 0 : (int) $minutes;

        return $h <= 23 && $m <= 59 ? sprintf('%02d:%02d', $h, $m) : null;
    }

    /**
     * Убирает найденный фрагмент из текста.
     *
     * @param  array{0: string, 1: int}  $match
     */
    private static function cut(string $text, array $match): string
    {
        return substr($text, 0, $match[1]).' '.substr($text, $match[1] + strlen($match[0]));
    }

    private static function normalizeTag(string $name): string
    {
        return mb_strtolower(preg_replace('/[\s_-]+/u', '', $name) ?? $name);
    }
}
