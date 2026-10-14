<?php

namespace App\Support;

use App\Models\Lesson;
use App\Models\Tag;
use App\Models\Task;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Рисует день или неделю планера в SVG.
 *
 * Картинка самодостаточна: только системные шрифты и готовые цвета,
 * поэтому её можно открыть на телефоне или перевести в PNG в браузере.
 */
class PlannerImage
{
    private const WIDTH = 640;

    private const PAD = 32;

    private const FONT = '-apple-system, BlinkMacSystemFont, &quot;Segoe UI&quot;, Roboto, &quot;Helvetica Neue&quot;, Arial, sans-serif';

    /** Средняя ширина символа относительно кегля — для переноса строк. */
    private const CHAR_WIDTH = 0.56;

    /** @var list<string> */
    private array $parts = [];

    /** Наложенный график: кто и каким цветом рисуется. */
    private ?Owner $overlay = null;

    private string $overlayColor = '';

    /**
     * @param  array{dark: bool, paper: string, sheet: string, ink: string, graphite: string, rule: string, accent: string, accentText: string, accentOn: string, lesson: string}  $palette
     */
    public function __construct(private readonly array $palette) {}

    /**
     * @param  Collection<int, array{date: Carbon, untimed: Collection, ghostUntimed: Collection, timeline: Collection, freeTogether: ?array, total: int, done: int}>  $days
     */
    public function render(string $title, string $summary, Collection $days, bool $showDayNames, ?Owner $overlay = null): string
    {
        $this->parts = [];
        $this->overlay = $overlay;
        $this->overlayColor = match (true) {
            $overlay === null, $overlay->isMe() => $this->palette['accentText'],
            default => Tag::SWATCHES[$overlay->person->color][$this->palette['dark'] ? 1 : 0] ?? $this->palette['graphite'],
        };

        $y = self::PAD + 26;
        $this->text(self::PAD, $y, $title, 26, $this->palette['ink'], 700);
        $y += 26;
        $this->text(self::PAD, $y, $summary, 14, $this->palette['graphite']);
        $y += 22;

        foreach ($days as $day) {
            $y = $this->day($day, $y, $showDayNames);
        }

        $height = (int) ceil($y + self::PAD - 8);
        $width = self::WIDTH;

        return '<svg xmlns="http://www.w3.org/2000/svg" width="'.$width.'" height="'.$height.'" viewBox="0 0 '.$width.' '.$height.'" font-family="'.self::FONT.'">'
            .'<rect width="100%" height="100%" fill="'.$this->palette['sheet'].'"/>'
            .implode('', $this->parts)
            .'</svg>';
    }

    /**
     * @param  array{date: Carbon, untimed: Collection, ghostUntimed: Collection, timeline: Collection, freeTogether: ?array, total: int, done: int}  $day
     */
    private function day(array $day, float $y, bool $showName): float
    {
        $contentWidth = self::WIDTH - 2 * self::PAD;

        if ($showName) {
            $this->rect(self::PAD, $y, $contentWidth, 1, $this->palette['rule']);
            $y += 30;
            $this->text(self::PAD, $y, Str::ucfirst($day['date']->translatedFormat('l, j F')), 17, $this->palette['ink'], 700);
            if ($day['total'] > 0) {
                $this->text(self::WIDTH - self::PAD, $y, "{$day['done']}/{$day['total']}", 13, $this->palette['graphite'], anchor: 'end');
            }
            $y += 12;
        }

        $this->rect(self::PAD, $y, $contentWidth, 4, $this->palette['rule'], radius: 2);
        if ($day['total'] > 0 && $day['done'] > 0) {
            $this->rect(self::PAD, $y, $contentWidth * $day['done'] / $day['total'], 4, $this->palette['accent'], radius: 2);
        }
        $y += 16;

        if ($day['freeTogether'] !== null) {
            $free = $day['freeTogether'] === []
                ? 'Общего свободного времени нет'
                : 'Оба свободны: '.implode(', ', array_map(fn (array $window) => $window['from'].'–'.$window['to'], $day['freeTogether']));
            foreach ($this->wrap($free, $contentWidth, 13, 3) as $line) {
                $this->text(self::PAD, $y + 12, $line, 13, $this->overlayColor, 600);
                $y += 18;
            }
            $y += 8;
        }

        if ($day['untimed']->isEmpty() && $day['ghostUntimed']->isEmpty() && $day['timeline']->isEmpty()) {
            $this->text(self::PAD, $y + 14, 'Свободно', 14, $this->palette['graphite']);

            return $y + 34;
        }

        foreach ($day['untimed'] as $task) {
            $y = $this->task($task, $y);
        }

        foreach ($day['ghostUntimed'] as $task) {
            $y = $this->ghost(null, $task->title, $task->done, $y);
        }

        foreach ($day['timeline'] as $entry) {
            $item = $entry['item'];
            $y = match (true) {
                $entry['ghost'] && $item instanceof Lesson => $this->ghost($item->starts_at.'–'.$item->ends_at, 'Пара «'.$item->subject.'»', false, $y),
                $entry['ghost'] => $this->ghost($item->timeLabel(), $item->title, $item->done, $y),
                $item instanceof Lesson => $this->lesson($item, $y),
                default => $this->task($item, $y),
            };
        }

        return $y + 14;
    }

    /**
     * Запись наложенного графика: полоса цвета человека, время, название и имя.
     */
    private function ghost(?string $time, string $title, bool $done, float $y): float
    {
        $width = self::WIDTH - 2 * self::PAD;
        $label = ($time ? $time.'  ' : '').$title;
        $lines = $this->wrap($label, $width - 24, 14, 2);
        $height = 10 + count($lines) * 19 + 16 + 6;

        $this->rect(self::PAD, $y + 2, $width, $height, $this->overlayColor, radius: 6, opacity: 0.08);
        $this->rect(self::PAD, $y + 2, 3, $height, $this->overlayColor, radius: 1.5);

        $textY = $y + 2 + 6;
        foreach ($lines as $line) {
            $textY += 19;
            $this->text(self::PAD + 14, $textY, $line, 14, $done ? $this->palette['graphite'] : $this->palette['ink'],
                decoration: $done ? 'line-through' : null);
        }
        $this->text(self::PAD + 14, $textY + 16, $this->overlay?->name() ?? '', 11, $this->overlayColor, 600);

        return $y + $height + 8;
    }

    private function task(Task $task, float $y): float
    {
        $accent = $this->palette['accent'];
        $cx = self::PAD + 9;
        $cy = $y + 11;

        if ($task->done) {
            $this->parts[] = '<circle cx="'.$cx.'" cy="'.$cy.'" r="9" fill="'.$accent.'"/>';
            $this->parts[] = '<path d="M'.($cx - 4).' '.$cy.' l3 3 l5 -6" fill="none" stroke="'.$this->palette['accentOn'].'" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>';
        } else {
            $this->parts[] = '<circle cx="'.$cx.'" cy="'.$cy.'" r="8.25" fill="none" stroke="'.$this->palette['graphite'].'" stroke-width="1.5"/>';
        }

        $titleX = self::PAD + 28;
        if ($task->time) {
            $label = $task->timeLabel();
            $this->text($titleX, $y + 16, $label, 14, $task->done ? $this->palette['graphite'] : $this->palette['accentText'], 600);
            $titleX += (int) ceil(mb_strlen($label) * 14 * 0.6) + 10;
        }

        $lines = $this->wrap($task->title, self::WIDTH - self::PAD - $titleX, 15, 3);
        foreach ($lines as $index => $line) {
            $this->text($titleX, $y + 16 + $index * 20, $line, 15,
                $task->done ? $this->palette['graphite'] : $this->palette['ink'],
                decoration: $task->done ? 'line-through' : null);
        }
        $y += 16 + (count($lines) - 1) * 20 + 8;

        if ($task->tags->isNotEmpty()) {
            $y = $this->tags($task->tags, $titleX, $y + 2) + 2;
        }

        return $y + 6;
    }

    /**
     * @param  Collection<int, Tag>  $tags
     */
    private function tags(Collection $tags, float $startX, float $y): float
    {
        $x = $startX;

        foreach ($tags as $tag) {
            $color = Tag::SWATCHES[$tag->color][$this->palette['dark'] ? 1 : 0] ?? $this->palette['graphite'];
            $width = mb_strlen($tag->name) * 11 * self::CHAR_WIDTH + 24;

            if ($x > $startX && $x + $width > self::WIDTH - self::PAD) {
                $x = $startX;
                $y += 24;
            }

            $this->rect($x, $y, $width, 20, $color, radius: 10, opacity: 0.14);
            $this->parts[] = '<circle cx="'.round($x + 10, 1).'" cy="'.round($y + 10, 1).'" r="3" fill="'.$color.'"/>';
            $this->text($x + 17, $y + 14, $tag->name, 11, $color, 600);
            $x += $width + 6;
        }

        return $y + 20;
    }

    private function lesson(Lesson $lesson, float $y): float
    {
        $width = self::WIDTH - 2 * self::PAD;
        $lines = $this->wrap($lesson->subject, $width - 28, 15, 2);
        $meta = implode('   ', array_filter([
            $lesson->kindLabel(),
            $lesson->room ? 'ауд. '.$lesson->room : null,
            $lesson->group_name,
        ]));

        $height = 12 + 16 + count($lines) * 20 + ($meta !== '' ? 18 : 0) + 8;

        $this->rect(self::PAD, $y + 2, $width, $height, $this->palette['lesson'], radius: 10, opacity: 0.12);
        $this->rect(self::PAD, $y + 2, 3, $height, $this->palette['lesson'], radius: 1.5);

        $textY = $y + 2 + 22;
        $this->text(self::PAD + 16, $textY, $lesson->starts_at.'–'.$lesson->ends_at, 13, $this->palette['lesson'], 600);
        foreach ($lines as $line) {
            $textY += 20;
            $this->text(self::PAD + 16, $textY, $line, 15, $this->palette['ink'], 700);
        }
        if ($meta !== '') {
            $textY += 18;
            $this->text(self::PAD + 16, $textY, $meta, 12, $this->palette['graphite']);
        }

        return $y + $height + 10;
    }

    /**
     * Делит текст на строки по ширине; лишнее после $maxLines обрезает многоточием.
     *
     * @return list<string>
     */
    private function wrap(string $text, float $width, int $fontSize, int $maxLines): array
    {
        $maxChars = max(8, (int) floor($width / ($fontSize * self::CHAR_WIDTH)));
        $lines = [];
        $current = '';

        foreach (preg_split('/\s+/u', trim($text)) ?: [] as $word) {
            while (mb_strlen($word) > $maxChars) {
                if ($current !== '') {
                    $lines[] = $current;
                    $current = '';
                }
                $lines[] = mb_substr($word, 0, $maxChars);
                $word = mb_substr($word, $maxChars);
            }

            $candidate = $current === '' ? $word : $current.' '.$word;
            if (mb_strlen($candidate) <= $maxChars) {
                $current = $candidate;
            } else {
                $lines[] = $current;
                $current = $word;
            }
        }

        if ($current !== '') {
            $lines[] = $current;
        }

        if (count($lines) > $maxLines) {
            $lines = array_slice($lines, 0, $maxLines);
            $lines[$maxLines - 1] = rtrim(mb_substr($lines[$maxLines - 1], 0, $maxChars - 1)).'…';
        }

        return $lines === [] ? [''] : $lines;
    }

    private function text(float $x, float $y, string $content, int $size, string $color, int $weight = 400, string $anchor = 'start', ?string $decoration = null): void
    {
        $this->parts[] = '<text x="'.round($x, 1).'" y="'.round($y, 1).'" font-size="'.$size.'" font-weight="'.$weight.'" fill="'.$color.'"'
            .($anchor !== 'start' ? ' text-anchor="'.$anchor.'"' : '')
            .($decoration ? ' text-decoration="'.$decoration.'"' : '')
            .'>'.htmlspecialchars($content, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</text>';
    }

    private function rect(float $x, float $y, float $width, float $height, string $color, float $radius = 0, float $opacity = 1): void
    {
        $this->parts[] = '<rect x="'.round($x, 1).'" y="'.round($y, 1).'" width="'.round($width, 1).'" height="'.round($height, 1).'"'
            .($radius > 0 ? ' rx="'.$radius.'"' : '')
            .' fill="'.$color.'"'
            .($opacity < 1 ? ' fill-opacity="'.$opacity.'"' : '')
            .'/>';
    }
}
