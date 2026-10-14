<?php

namespace Tests\Feature;

use App\Models\Tag;
use App\Models\Task;
use App\Support\Statistics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class StatsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-08 10:00:00'); // четверг, неделя 5–11 октября
    }

    public function test_shows_week_and_month_totals(): void
    {
        Task::factory()->create(['date' => '2026-10-05', 'done' => true]);
        Task::factory()->create(['date' => '2026-10-08', 'done' => false]);
        Task::factory()->create(['date' => '2026-10-01', 'done' => true]); // прошлая неделя, этот месяц
        Task::factory()->create(['date' => '2026-09-30', 'done' => true]); // прошлый месяц

        $response = $this->get('/stats');

        $response->assertOk();
        $response->assertSeeInOrder(['Эта неделя', '1', 'из 2', '50% выполнено', 'Октябрь', '2', 'из 3']);
    }

    public function test_current_streak_counts_yesterday_until_something_is_done_today(): void
    {
        foreach (['2026-10-05', '2026-10-06', '2026-10-07'] as $date) {
            Task::factory()->create(['date' => $date, 'done' => true]);
        }
        Task::factory()->create(['date' => '2026-10-08', 'done' => false]);

        $streaks = (new Statistics(Carbon::today()))->streaks();

        $this->assertSame(['current' => 3, 'best' => 3], $streaks);
    }

    public function test_streak_breaks_on_a_day_without_done_tasks(): void
    {
        foreach (['2026-09-20', '2026-09-21', '2026-09-22', '2026-09-23', '2026-10-07', '2026-10-08'] as $date) {
            Task::factory()->create(['date' => $date, 'done' => true]);
        }
        Task::factory()->create(['date' => '2026-10-09', 'done' => true]); // будущее не считается

        $streaks = (new Statistics(Carbon::today()))->streaks();

        $this->assertSame(['current' => 2, 'best' => 4], $streaks);
    }

    public function test_charts_cover_eight_weeks_by_tag_and_weekday(): void
    {
        $work = Tag::where('name', 'Работа')->first();
        Task::factory()->create(['date' => '2026-10-05', 'done' => true])->tags()->attach($work);
        Task::factory()->create(['date' => '2026-09-28', 'done' => true])->tags()->attach($work);
        Task::factory()->create(['date' => '2026-10-06', 'done' => true]);
        Task::factory()->create(['date' => '2026-08-01', 'done' => true]); // за пределами 8 недель

        $statistics = new Statistics(Carbon::today());

        $this->assertCount(8, $statistics->weeks());
        $this->assertSame(['done' => 1, 'total' => 1], array_intersect_key($statistics->weeks()->get(6), ['done' => 0, 'total' => 0]));
        $this->assertSame(
            [['name' => 'Работа', 'done' => 2], ['name' => 'Без тега', 'done' => 1]],
            $statistics->byTag()->map(fn (array $row) => ['name' => $row['name'], 'done' => $row['done']])->all(),
        );
        $this->assertSame([1 => 2, 2 => 1], $statistics->byWeekday()->filter(fn ($row) => $row['done'] > 0)->mapWithKeys(fn ($row) => [$row['weekday'] => $row['done']])->all());

        $this->get('/stats')->assertSee('чаще всего — понедельник');
    }

    public function test_deleted_tasks_are_not_counted(): void
    {
        Task::factory()->create(['date' => '2026-10-06', 'done' => true])->delete();

        $statistics = new Statistics(Carbon::today());

        $this->assertSame(['done' => 0, 'total' => 0], $statistics->period(Carbon::parse('2026-10-05'), Carbon::parse('2026-10-11')));
        $this->assertSame(['current' => 0, 'best' => 0], $statistics->streaks());
    }

    public function test_shows_hint_when_there_is_no_data_yet(): void
    {
        $response = $this->get('/stats');

        $response->assertOk();
        $response->assertSee('графики появятся');
    }
}
