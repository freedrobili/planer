<?php

namespace Tests\Unit\Support;

use App\Support\QuickInput;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class QuickInputTest extends TestCase
{
    private const TAGS = [1 => 'Работа', 2 => 'Личное', 3 => 'Домашние дела'];

    /** Пятница, 9 октября 2026. */
    private function today(): Carbon
    {
        return Carbon::create(2026, 10, 9);
    }

    /**
     * @return array<string, array{0: string, 1: array<string, mixed>}>
     */
    public static function phrases(): array
    {
        return [
            'всё сразу' => ['завтра 15:00-16:30 созвон #работа', ['title' => 'созвон', 'date' => '2026-10-10', 'time' => '15:00', 'ends_at' => '16:30', 'tags' => [1]]],
            'только текст' => ['Купить 2-3 яблока', ['title' => 'Купить 2-3 яблока', 'date' => null, 'time' => null, 'ends_at' => null, 'tags' => []]],
            'послезавтра и «в 9»' => ['пробежка послезавтра в 9', ['title' => 'пробежка', 'date' => '2026-10-11', 'time' => '09:00']],
            'сегодня с минутами' => ['Сегодня в 18:45 спортзал', ['title' => 'спортзал', 'date' => '2026-10-09', 'time' => '18:45']],
            'день недели впереди' => ['отчёт в понедельник', ['title' => 'отчёт', 'date' => '2026-10-12']],
            'сегодняшний день недели' => ['пт уборка', ['title' => 'уборка', 'date' => '2026-10-09']],
            'дата числами' => ['стоматолог 14.10 10:30', ['title' => 'стоматолог', 'date' => '2026-10-14', 'time' => '10:30']],
            'прошедшая дата — следующий год' => ['подать документы 05.03', ['date' => '2027-03-05']],
            'дата словами' => ['экзамен 20 декабря', ['title' => 'экзамен', 'date' => '2026-12-20']],
            'интервал «с … до …»' => ['встреча с 15 до 17', ['title' => 'встреча', 'time' => '15:00', 'ends_at' => '17:00']],
            'тег с пробелом в названии' => ['пылесос #домашниедела', ['title' => 'пылесос', 'tags' => [3]]],
            'неизвестный тег остаётся в тексте' => ['идея #проект', ['title' => 'идея #проект', 'tags' => []]],
            'несуществующая дата не распознаётся' => ['что-то 31.02', ['title' => 'что-то 31.02', 'date' => null]],
            'неверное время не распознаётся' => ['в 25 ничего', ['title' => 'в 25 ничего', 'time' => null]],
            'обратный интервал не распознаётся как интервал' => ['созвон 16:00-15:00', ['time' => '16:00', 'ends_at' => null]],
        ];
    }

    /**
     * @param  array<string, mixed>  $expected
     */
    #[DataProvider('phrases')]
    public function test_recognizes_date_time_and_tags(string $text, array $expected): void
    {
        $result = QuickInput::parse($text, $this->today(), self::TAGS);

        $this->assertSame($expected, array_intersect_key($result, $expected));
    }

    public function test_keeps_original_text_when_nothing_else_remains(): void
    {
        $result = QuickInput::parse('завтра', $this->today(), self::TAGS);

        $this->assertSame('завтра', $result['title']);
        $this->assertSame('2026-10-10', $result['date']);
    }
}
