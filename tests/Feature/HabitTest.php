<?php

namespace Tests\Feature;

use App\Models\Habit;
use App\Models\HabitCheck;
use App\Support\HabitProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class HabitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-08 10:00:00'); // четверг, неделя 5–11 октября
    }

    /**
     * @param  list<string>  $dates
     */
    private function checked(Habit $habit, array $dates, ?int $count = null): void
    {
        foreach ($dates as $date) {
            HabitCheck::factory()->for($habit)->create(['date' => $date, 'count' => $count ?? $habit->target]);
        }
    }

    public function test_creates_habit_for_chosen_days(): void
    {
        $response = $this->post('/habits', [
            'name' => '  Вода  ', 'color' => 'blue', 'weekdays' => ['5', '1', '1'], 'target' => 8, 'week' => '2026-10-05',
        ]);

        $response->assertRedirect('/habits?date=2026-10-05');
        $response->assertSessionHas('status', 'Привычка «Вода» добавлена');
        $habit = Habit::sole();
        $this->assertSame(['Вода', 'blue', [1, 5], 8], [$habit->name, $habit->color, $habit->weekdays, $habit->target]);
    }

    public function test_validates_habit(): void
    {
        $response = $this->from('/habits')->post('/habits', ['name' => '', 'color' => 'gold', 'weekdays' => [], 'target' => 0]);

        $response->assertRedirect('/habits');
        $response->assertSessionHasErrors([
            'name' => 'Напишите, какую привычку отслеживать.',
            'color' => 'Выберите цвет из предложенных.',
            'weekdays' => 'Отметьте хотя бы один день недели.',
            'target' => 'Цель на день — число от 1 до 50.',
        ]);
        $this->assertDatabaseCount('habits', 0);
    }

    public function test_updates_and_deletes_habit_with_its_checks(): void
    {
        $habit = Habit::factory()->create();
        $this->checked($habit, ['2026-10-07']);

        $this->put("/habits/{$habit->id}", ['name' => 'Чтение', 'color' => 'violet', 'weekdays' => [1, 2, 3, 4, 5], 'target' => 1])
            ->assertRedirect('/habits');
        $this->assertSame(['Чтение', [1, 2, 3, 4, 5]], [$habit->refresh()->name, $habit->weekdays]);

        $this->delete("/habits/{$habit->id}")->assertRedirect('/habits');
        $this->assertModelMissing($habit);
        $this->assertDatabaseCount('habit_checks', 0);
    }

    public function test_simple_habit_toggles_day(): void
    {
        $habit = Habit::factory()->create();

        $this->postJson("/habits/{$habit->id}/days/2026-10-08")
            ->assertOk()
            ->assertJson(['count' => 1, 'done' => true, 'progress' => ['current' => 1]]);

        $this->postJson("/habits/{$habit->id}/days/2026-10-08")
            ->assertOk()
            ->assertJson(['count' => 0, 'done' => false, 'progress' => ['current' => 0]]);
    }

    public function test_counted_habit_adds_up_to_target_then_resets(): void
    {
        $habit = Habit::factory()->counted(3)->create();

        $counts = collect(range(1, 4))->map(fn () => $this->postJson("/habits/{$habit->id}/days/2026-10-08")->json('count'))->all();

        $this->assertSame([1, 2, 3, 0], $counts);
    }

    public function test_cannot_mark_future_or_unscheduled_day(): void
    {
        $weekdaysOnly = Habit::factory()->on([1, 2, 3, 4, 5])->create();

        $this->postJson("/habits/{$weekdaysOnly->id}/days/2026-10-09")->assertUnprocessable();
        $this->postJson("/habits/{$weekdaysOnly->id}/days/2026-10-04")->assertUnprocessable(); // воскресенье
        $this->postJson("/habits/{$weekdaysOnly->id}/days/2026-02-30")->assertNotFound();

        $this->assertDatabaseCount('habit_checks', 0);
    }

    public function test_streak_skips_unscheduled_days_and_waits_for_today(): void
    {
        $habit = Habit::factory()->on([1, 2, 3, 4, 5])->create(['created_at' => '2026-09-28']);
        $this->checked($habit, ['2026-10-01', '2026-10-02', '2026-10-05', '2026-10-06', '2026-10-07']);

        $progress = HabitProgress::for($habit, $habit->checks()->pluck('count', 'date')->all(), Carbon::today());

        $this->assertSame(5, $progress['current']); // выходные 3–4 октября не прерывают серию, сегодня ещё не отмечено
        $this->assertSame(5, $progress['best']);
        $this->assertSame(63, $progress['rate']); // 5 из 8 будних дней с 28 сентября (без сегодняшнего)
    }

    public function test_missed_day_breaks_current_streak_but_keeps_best(): void
    {
        $habit = Habit::factory()->create(['created_at' => '2026-09-30']);
        $this->checked($habit, ['2026-09-30', '2026-10-01', '2026-10-02', '2026-10-03', '2026-10-06', '2026-10-07', '2026-10-08']);

        $progress = HabitProgress::for($habit, $habit->checks()->pluck('count', 'date')->all(), Carbon::today());

        $this->assertSame(['current' => 3, 'best' => 4, 'rate' => 78], $progress);
    }

    public function test_partial_count_is_not_a_done_day(): void
    {
        $habit = Habit::factory()->counted(8)->create(['created_at' => '2026-10-07']);
        $this->checked($habit, ['2026-10-07'], count: 5);

        $progress = HabitProgress::for($habit, $habit->checks()->pluck('count', 'date')->all(), Carbon::today());

        $this->assertSame(['current' => 0, 'best' => 0, 'rate' => 0], $progress);
    }

    public function test_page_shows_week_grid_with_progress(): void
    {
        $habit = Habit::factory()->counted(8)->on([1, 2, 3, 4, 5])->create(['name' => 'Вода', 'created_at' => '2026-10-05']);
        $this->checked($habit, ['2026-10-05', '2026-10-06']);
        $this->checked($habit, ['2026-10-07'], count: 3);

        $response = $this->get('/habits');

        $response->assertOk();
        $response->assertSeeInOrder(['Пн', '5', 'Вс', '11', 'Вода', 'по будням, цель 8 в день']);
        $response->assertSee('Вода, Ср, 7 октября: 3 из 8');
        $response->assertSee('Пт, 9 октября: ещё впереди');
        $response->assertSee('Сб, 10 октября: не запланировано');
        $response->assertSeeInOrder(['0 дней', 'лучшая 2', '67%']); // среда выполнена частично — серия прервана
    }

    public function test_page_invites_to_add_first_habit(): void
    {
        $this->get('/habits')->assertOk()->assertSee('Привычек пока нет');
    }
}
