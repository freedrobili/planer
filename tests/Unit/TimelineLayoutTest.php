<?php

namespace Tests\Unit;

use App\Models\Lesson;
use App\Models\Task;
use App\Support\TimelineLayout;
use PHPUnit\Framework\TestCase;

class TimelineLayoutTest extends TestCase
{
    private function task(string $time, ?string $endsAt = null, bool $ghost = false): array
    {
        return ['type' => 'task', 'time' => $time, 'item' => new Task(['time' => $time, 'ends_at' => $endsAt]), 'ghost' => $ghost];
    }

    private function lesson(string $start, string $end, bool $ghost = false): array
    {
        return ['type' => 'lesson', 'time' => $start, 'item' => new Lesson(['starts_at' => $start, 'ends_at' => $end]), 'ghost' => $ghost];
    }

    public function test_block_starts_at_its_time_and_is_as_tall_as_its_duration(): void
    {
        [$lesson] = TimelineLayout::place(collect([$this->lesson('10:15', '11:45')]), firstHour: 8);

        $this->assertSame([2.25, 1.5], [$lesson['top'], $lesson['span']]);
    }

    public function test_task_without_end_takes_an_hour_and_very_short_ones_stay_readable(): void
    {
        [$point, $short] = TimelineLayout::place(collect([$this->task('09:00'), $this->task('12:00', '12:10')]), firstHour: 9);

        $this->assertSame(1.0, $point['span']);
        $this->assertSame(0.75, $short['span']);
    }

    public function test_overlapping_entries_share_the_width_and_later_ones_lie_on_top(): void
    {
        $events = TimelineLayout::place(collect([
            $this->lesson('12:05', '13:35'),
            $this->task('13:00', '14:30'),
            $this->lesson('13:50', '15:20'),
            $this->task('18:00'),
        ]), firstHour: 6);

        $this->assertSame([[0, 2], [1, 2], [0, 2], [0, 1]], $events->map(fn (array $e) => [$e['lane'], $e['lanes']])->all());
        $this->assertSame([0, 1, 2, 3], $events->pluck('stack')->all());
    }

    public function test_overlay_puts_other_schedule_in_its_own_column(): void
    {
        $events = TimelineLayout::place(collect([
            $this->lesson('10:00', '11:30', ghost: true),
            $this->lesson('10:00', '11:30'),
        ]), firstHour: 6, split: true);

        $this->assertSame(['own', 'ghost'], $events->pluck('column')->all());
        $this->assertSame([1, 1], $events->pluck('lanes')->all());
    }

    public function test_hour_bounds_cover_the_whole_block(): void
    {
        $this->assertSame([7, 10], TimelineLayout::hourBounds(collect([$this->task('07:30'), $this->lesson('09:00', '10:40')])));
        $this->assertNull(TimelineLayout::hourBounds(collect()));
    }
}
