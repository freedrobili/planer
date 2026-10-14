<?php

namespace Tests\Feature;

use App\Models\Lesson;
use App\Models\Tag;
use App\Models\Task;
use App\Models\TaskItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TaskDetailsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-07 10:00:00'); // среда
    }

    /**
     * @return array<string, mixed>
     */
    private function editPayload(array $overrides = []): array
    {
        return array_merge(['title' => 'Отчёт', 'date' => '2026-10-07', 'items_sent' => 1], $overrides);
    }

    public function test_saves_duration_description_and_checklist(): void
    {
        $task = Task::factory()->create();

        $response = $this->put("/tasks/{$task->id}", $this->editPayload([
            'time' => '14:00',
            'ends_at' => '16:00',
            'description' => '  Взять ноутбук  ',
            'items' => [
                ['title' => 'Собрать цифры', 'done' => '1'],
                ['title' => 'Отправить'],
            ],
        ]));

        $response->assertRedirect('/?date=2026-10-07');
        $task->refresh();
        $this->assertSame('16:00', $task->ends_at);
        $this->assertSame('Взять ноутбук', $task->description);
        $this->assertSame(
            [['title' => 'Собрать цифры', 'done' => true], ['title' => 'Отправить', 'done' => false]],
            $task->items->map->only(['title', 'done'])->all(),
        );
    }

    public function test_saving_replaces_checklist_and_empty_list_clears_it(): void
    {
        $task = Task::factory()->create();
        TaskItem::factory()->for($task)->create(['title' => 'Старый пункт']);

        $this->put("/tasks/{$task->id}", $this->editPayload(['items' => [['title' => 'Новый пункт']]]));
        $this->assertSame(['Новый пункт'], $task->items()->pluck('title')->all());

        $this->put("/tasks/{$task->id}", $this->editPayload());
        $this->assertSame(0, $task->items()->count());
    }

    public function test_rejects_end_before_start_and_end_without_start(): void
    {
        $task = Task::factory()->create();

        $this->from('/')->put("/tasks/{$task->id}", $this->editPayload(['time' => '16:00', 'ends_at' => '15:00']))
            ->assertSessionHasErrors(['ends_at' => 'Задача должна заканчиваться позже, чем начинается.']);

        $this->from('/')->put("/tasks/{$task->id}", $this->editPayload(['time' => '', 'ends_at' => '15:00']))
            ->assertSessionHasErrors(['time' => 'Чтобы указать окончание, укажите и время начала.']);

        $this->assertNull($task->refresh()->ends_at);
    }

    public function test_rejects_empty_checklist_item(): void
    {
        $task = Task::factory()->create();

        $response = $this->from('/')->put("/tasks/{$task->id}", $this->editPayload(['items' => [['title' => '']]]));

        $response->assertSessionHasErrors(['items.0.title' => 'У пункта чек-листа должен быть текст.']);
        $this->assertSame(0, $task->items()->count());
    }

    public function test_planner_shows_time_range_checklist_counter_and_lesson_conflict(): void
    {
        Lesson::factory()->create(['weekday' => 3, 'starts_at' => '10:10', 'ends_at' => '11:40', 'subject' => 'Матанализ']);
        $task = Task::factory()->create(['title' => 'Созвон', 'time' => '11:00', 'ends_at' => '12:30']);
        TaskItem::factory()->for($task)->done()->create();
        TaskItem::factory()->for($task)->create();
        Task::factory()->create(['title' => 'После пар', 'time' => '12:00']);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('11:00–12:30');
        $response->assertSee('1/2');
        $response->assertSeeInOrder(['Созвон', 'Во время пары «Матанализ»', 'После пар']);
        $response->assertSee('Во время пары', false);
        $this->assertSame(1, substr_count($response->getContent(), 'Во время пары'));
        $this->assertMatchesRegularExpression('/class="hour\s+is-busy" data-hour="11"/', $response->getContent());
        $this->assertMatchesRegularExpression('/class="hour\s+is-planned" data-hour="12"/', $response->getContent());
    }

    public function test_moves_task_to_another_day_keeping_time(): void
    {
        $task = Task::factory()->create(['time' => '09:00', 'ends_at' => '10:00']);

        $response = $this->patchJson("/tasks/{$task->id}/move", ['date' => '2026-10-08']);

        $response->assertOk();
        $response->assertJsonPath('message', 'Задача перенесена на 8 октября');
        $task->refresh();
        $this->assertSame('2026-10-08', $task->date->toDateString());
        $this->assertSame(['09:00', '10:00'], [$task->time, $task->ends_at]);
    }

    public function test_moving_to_another_hour_keeps_duration(): void
    {
        $task = Task::factory()->create(['time' => '09:30', 'ends_at' => '11:00']);

        $this->patchJson("/tasks/{$task->id}/move", ['date' => '2026-10-07', 'time' => '14:30'])
            ->assertOk()
            ->assertJsonPath('message', 'Задача перенесена на 14:30–16:00');

        $task->refresh();
        $this->assertSame(['14:30', '16:00'], [$task->time, $task->ends_at]);
    }

    public function test_moving_late_task_never_ends_after_midnight(): void
    {
        $task = Task::factory()->create(['time' => '20:00', 'ends_at' => '22:00']);

        $this->patchJson("/tasks/{$task->id}/move", ['date' => '2026-10-07', 'time' => '23:00'])->assertOk();

        $this->assertSame('23:59', $task->refresh()->ends_at);
    }

    public function test_moving_to_whole_day_clears_time_and_duration(): void
    {
        $task = Task::factory()->create(['time' => '09:00', 'ends_at' => '10:00']);

        $this->patchJson("/tasks/{$task->id}/move", ['date' => '2026-10-07', 'time' => null])
            ->assertOk()
            ->assertJsonPath('message', 'Время задачи убрано');

        $task->refresh();
        $this->assertNull($task->time);
        $this->assertNull($task->ends_at);
    }

    public function test_move_rejects_bad_date(): void
    {
        $task = Task::factory()->create();

        $response = $this->patchJson("/tasks/{$task->id}/move", ['date' => 'завтра']);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['date' => 'Дата должна быть в формате ГГГГ-ММ-ДД.']);
        $this->assertSame('2026-10-07', $task->refresh()->date->toDateString());
    }

    public function test_quick_input_sets_date_time_and_tag_and_stays_on_page(): void
    {
        $work = Tag::where('name', 'Работа')->first();

        $response = $this->post('/tasks', ['title' => 'завтра 15:00-16:00 созвон #работа', 'date' => '2026-10-07']);

        $response->assertRedirect('/?date=2026-10-07');
        $response->assertSessionHas('status', 'Задача добавлена на 8 октября');
        $task = Task::sole();
        $this->assertSame('созвон', $task->title);
        $this->assertSame('2026-10-08', $task->date->toDateString());
        $this->assertSame(['15:00', '16:00'], [$task->time, $task->ends_at]);
        $this->assertSame([$work->id], $task->tags->pluck('id')->all());
    }

    public function test_time_field_wins_over_time_in_text(): void
    {
        $this->post('/tasks', ['title' => 'встреча в 9', 'date' => '2026-10-07', 'time' => '18:00']);

        $task = Task::sole();
        $this->assertSame('18:00', $task->time);
        $this->assertSame('встреча', $task->title);
    }

    public function test_parse_preview_describes_recognized_parts(): void
    {
        $response = $this->getJson('/tasks/parse?text='.urlencode('в пятницу 10:00-11:30 отчёт #работа'));

        $response->assertOk();
        $response->assertExactJson(['title' => 'отчёт', 'summary' => 'Пт, 9 октября, 10:00–11:30, #Работа']);
    }

    public function test_parse_preview_is_empty_for_plain_text(): void
    {
        $this->getJson('/tasks/parse?text=купить+хлеб')->assertExactJson(['title' => 'купить хлеб', 'summary' => null]);
    }
}
