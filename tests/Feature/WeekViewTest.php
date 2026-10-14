<?php

namespace Tests\Feature;

use App\Models\Lesson;
use App\Models\Tag;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class WeekViewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-08 10:00:00'); // четверг, неделя 5–11 октября
    }

    public function test_week_view_shows_tasks_and_lessons_of_every_day(): void
    {
        Task::factory()->create(['title' => 'Понедельничная задача', 'date' => '2026-10-05', 'time' => '09:00']);
        Task::factory()->create(['title' => 'Воскресная задача', 'date' => '2026-10-11', 'done' => true]);
        Task::factory()->create(['title' => 'Задача следующей недели', 'date' => '2026-10-12']);
        Lesson::factory()->create(['weekday' => 3, 'subject' => 'Матанализ']);

        $response = $this->get('/?view=week');

        $response->assertOk();
        $response->assertSeeInOrder(['Пн', 'Понедельничная задача', 'Ср', 'Матанализ', 'Вс', 'Воскресная задача']);
        $response->assertDontSee('Задача следующей недели');
        $response->assertSee('За неделю готово 1 из 2');
    }

    public function test_untimed_tasks_come_before_timed_ones_within_a_day(): void
    {
        Task::factory()->create(['title' => 'В восемь утра', 'date' => '2026-10-06', 'time' => '08:00']);
        Task::factory()->create(['title' => 'Когда-нибудь во вторник', 'date' => '2026-10-06']);

        $response = $this->get('/?view=week');

        $response->assertOk();
        $response->assertSeeInOrder(['Когда-нибудь во вторник', 'В восемь утра']);
    }

    public function test_chosen_view_is_remembered_until_switched(): void
    {
        $this->get('/?view=week')->assertSee('class="week-grid', false);

        $this->get('/')->assertSee('class="week-grid', false);

        $this->get('/?view=day')->assertDontSee('class="week-grid', false);
        $this->get('/')->assertDontSee('class="week-grid', false);
    }

    public function test_unknown_view_falls_back_to_day(): void
    {
        $response = $this->get('/?view=month');

        $response->assertOk();
        $response->assertDontSee('class="week-grid', false);
        $response->assertSee('class="timeline"', false);
    }

    public function test_adding_task_from_week_view_returns_to_week_view(): void
    {
        $this->get('/?view=week');

        $response = $this->followingRedirects()->post('/tasks', ['title' => 'Из колонки', 'date' => '2026-10-09']);

        $response->assertOk();
        $response->assertSee('class="week-grid', false);
        $response->assertSee('Из колонки');
        $this->assertDatabaseHas('tasks', ['title' => 'Из колонки', 'date' => '2026-10-09 00:00:00']);
    }

    public function test_tag_filter_applies_to_week_view(): void
    {
        $work = Tag::where('name', 'Работа')->first();
        Task::factory()->create(['title' => 'Отчёт', 'date' => '2026-10-06'])->tags()->attach($work);
        Task::factory()->create(['title' => 'Кино', 'date' => '2026-10-07']);

        $response = $this->get("/?view=week&tag={$work->id}");

        $response->assertOk();
        $response->assertSee('Отчёт');
        $response->assertDontSee('Кино');
        $response->assertSee('«Работа»: готово 0 из 1');
    }

    public function test_day_heading_in_week_view_opens_that_day(): void
    {
        $response = $this->get('/?view=week');

        $response->assertSee('?date=2026-10-09&amp;view=day', false);
    }
}
