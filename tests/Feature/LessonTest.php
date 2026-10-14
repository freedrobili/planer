<?php

namespace Tests\Feature;

use App\Models\Lesson;
use App\Models\Setting;
use App\Models\Task;
use App\Support\Timetable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class LessonTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-07 09:00:00'); // среда, 41-я неделя года
    }

    private function lessonData(array $overrides = []): array
    {
        return array_merge([
            'weekday' => 3,
            'starts_at' => '10:10',
            'ends_at' => '11:40',
            'subject' => 'Матанализ',
            'kind' => 'lecture',
            'room' => '305',
            'group_name' => 'ИС-21 (1 подгруппа)',
            'weeks' => 'all',
        ], $overrides);
    }

    public function test_schedule_page_lists_lessons_by_weekday(): void
    {
        Lesson::factory()->create(['weekday' => 1, 'subject' => 'Физика']);
        Lesson::factory()->create(['weekday' => 3, 'subject' => 'Философия', 'weeks' => 'odd']);

        $this->get('/schedule')
            ->assertOk()
            ->assertSeeInOrder(['Понедельник', 'Физика', 'Вторник', 'Среда', 'Философия', 'по нечётным'])
            ->assertDontSee('id="weekday-7"', false);
    }

    public function test_creates_lesson(): void
    {
        $this->post('/lessons', $this->lessonData(['weekdays' => [3], 'room' => '  ', 'group_name' => '']))
            ->assertRedirect('/schedule')
            ->assertSessionHas('lastWeekday', 3);

        $this->assertDatabaseHas('lessons', ['subject' => 'Матанализ', 'room' => null, 'group_name' => null]);
    }

    public function test_saves_and_shows_lesson_group(): void
    {
        $this->post('/lessons', $this->lessonData(['weekdays' => [3]]))->assertRedirect('/schedule');

        $this->assertDatabaseHas('lessons', ['subject' => 'Матанализ', 'group_name' => 'ИС-21 (1 подгруппа)']);
        $this->get('/schedule')->assertSee('Группа (подгруппа)')->assertSee('ИС-21 (1 подгруппа)');
        $this->get('/?date=2026-10-07')->assertSee('ИС-21 (1 подгруппа)');
    }

    public function test_validates_lesson(): void
    {
        $this->from('/schedule')
            ->post('/lessons', $this->lessonData(['weekdays' => [3], 'subject' => '', 'ends_at' => '09:00', 'weeks' => 'sometimes']))
            ->assertRedirect('/schedule')
            ->assertSessionHasErrors(['subject', 'ends_at', 'weeks']);

        $this->assertDatabaseCount('lessons', 0);
    }

    public function test_adds_the_same_lesson_to_several_days_at_once(): void
    {
        $response = $this->post('/lessons', $this->lessonData(['weekdays' => [4, 1, 4]]));

        $response->assertRedirect('/schedule');
        $response->assertSessionHas('status', 'Пара «Матанализ» добавлена в 2 дня');
        $response->assertSessionHas('lastWeekday', 1);
        $this->assertDatabaseCount('lessons', 2);
        $this->assertDatabaseHas('lessons', ['weekday' => 1, 'subject' => 'Матанализ', 'starts_at' => '10:10', 'room' => '305']);
        $this->assertDatabaseHas('lessons', ['weekday' => 4, 'subject' => 'Матанализ', 'starts_at' => '10:10', 'room' => '305']);
    }

    public function test_new_lesson_requires_at_least_one_day(): void
    {
        $response = $this->from('/schedule')->post('/lessons', $this->lessonData(['weekdays' => []]));

        $response->assertRedirect('/schedule');
        $response->assertSessionHasErrors(['weekdays' => 'Отметьте хотя бы один день недели.']);
        $this->assertDatabaseCount('lessons', 0);
    }

    public function test_new_lesson_rejects_unknown_day(): void
    {
        $response = $this->from('/schedule')->post('/lessons', $this->lessonData(['weekdays' => [8]]));

        $response->assertSessionHasErrors(['weekdays.0' => 'Выберите дни недели из списка.']);
        $this->assertDatabaseCount('lessons', 0);
    }

    public function test_updates_and_deletes_lesson(): void
    {
        $lesson = Lesson::factory()->create();

        $this->put("/lessons/{$lesson->id}", $this->lessonData(['subject' => 'Алгебра', 'weekday' => 5]))
            ->assertRedirect('/schedule');
        $this->assertDatabaseHas('lessons', ['id' => $lesson->id, 'subject' => 'Алгебра', 'weekday' => 5]);

        $this->delete("/lessons/{$lesson->id}")->assertRedirect('/schedule');
        $this->assertModelMissing($lesson);
    }

    public function test_planner_shows_lessons_between_tasks_in_time_order(): void
    {
        Lesson::factory()->create(['weekday' => 3, 'starts_at' => '10:10', 'ends_at' => '11:40', 'subject' => 'Матанализ']);
        Lesson::factory()->create(['weekday' => 4, 'subject' => 'Четверговая пара']);
        Task::factory()->create(['title' => 'Кофе', 'time' => '10:00']);
        Task::factory()->create(['title' => 'Обед', 'time' => '10:30']);

        $this->get('/')
            ->assertOk()
            ->assertSeeInOrder(['Кофе', 'Матанализ', 'Обед'])
            ->assertDontSee('Четверговая пара')
            ->assertSee('1 пара')
            ->assertSee('Готово 0 из 2'); // пары не считаются задачами
    }

    public function test_marks_hours_covered_by_lesson(): void
    {
        Lesson::factory()->create(['weekday' => 3, 'starts_at' => '10:10', 'ends_at' => '12:00']);

        $html = $this->get('/')->getContent();

        $this->assertMatchesRegularExpression('/class="hour\s+is-busy" data-hour="10"/', $html);
        $this->assertMatchesRegularExpression('/class="hour\s+is-busy" data-hour="11"/', $html);
        $this->assertMatchesRegularExpression('/class="hour\s+" data-hour="12"/', $html);
    }

    public function test_week_parity_without_semester_uses_iso_week(): void
    {
        Lesson::factory()->create(['weekday' => 3, 'weeks' => 'odd', 'subject' => 'Нечётная пара']);
        Lesson::factory()->create(['weekday' => 3, 'weeks' => 'even', 'subject' => 'Чётная пара']);

        // 7 октября 2026 — 41-я неделя, нечётная.
        $this->get('/')->assertSee('Нечётная пара')->assertDontSee('Чётная пара');
        $this->get('/?date=2026-10-14')->assertSee('Чётная пара')->assertDontSee('Нечётная пара');
    }

    public function test_week_parity_counts_from_semester_start(): void
    {
        Setting::put('semester_start', '2026-09-01'); // вторник 36-й недели — это 1-я учебная

        $timetable = Timetable::load();

        $this->assertSame(1, $timetable->weekNumber(Carbon::parse('2026-08-31')));
        $this->assertSame(6, $timetable->weekNumber(Carbon::parse('2026-10-07')));
        $this->assertFalse($timetable->isOddWeek(Carbon::parse('2026-10-07')));
    }

    public function test_lessons_hidden_outside_semester(): void
    {
        Lesson::factory()->create(['weekday' => 3, 'subject' => 'Матанализ']);

        $this->put('/schedule/semester', ['semester_start' => '2026-09-01', 'semester_end' => '2026-12-31'])
            ->assertRedirect('/schedule');

        $this->get('/')->assertSee('Матанализ');
        $this->get('/?date=2027-01-13')->assertDontSee('Матанализ');
    }

    public function test_validates_semester_dates(): void
    {
        $this->from('/schedule')
            ->put('/schedule/semester', ['semester_start' => '2026-12-01', 'semester_end' => '2026-09-01'])
            ->assertSessionHasErrorsIn('semester', ['semester_end']);
    }

    public function test_used_time_slots_are_offered(): void
    {
        Lesson::factory()->create(['starts_at' => '08:30', 'ends_at' => '10:00']);
        Lesson::factory()->create(['weekday' => 2, 'starts_at' => '08:30', 'ends_at' => '10:00']);

        $this->get('/schedule')->assertSee('data-slot="08:30–10:00"', false);
    }
}
