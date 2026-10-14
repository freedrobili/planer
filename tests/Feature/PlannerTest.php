<?php

namespace Tests\Feature;

use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PlannerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-06 10:30:00'); // вторник
    }

    public function test_shows_selected_week_with_tasks_grouped_by_hour(): void
    {
        Task::factory()->create(['title' => 'Пробежка', 'date' => '2026-10-06', 'time' => '07:15']);
        Task::factory()->create(['title' => 'Купить хлеб', 'date' => '2026-10-06']);
        Task::factory()->create(['title' => 'Чужая неделя', 'date' => '2026-10-20']);

        $this->get('/')
            ->assertOk()
            ->assertSee('Вторник')
            ->assertSee('5–11 октября')
            ->assertSeeInOrder(['В течение дня', 'Купить хлеб', '07:00', 'Пробежка'])
            ->assertDontSee('Чужая неделя');
    }

    public function test_shows_tasks_on_the_last_day_of_the_week(): void
    {
        Task::factory()->create(['title' => 'Воскресная прогулка', 'date' => '2026-10-11']);

        $response = $this->get('/?date=2026-10-11');

        $response->assertOk();
        $response->assertSee('Воскресная прогулка');
    }

    public function test_extends_timeline_for_early_tasks(): void
    {
        Task::factory()->create(['date' => '2026-10-06', 'time' => '04:30']);

        $this->get('/')->assertSee('04:00');
    }

    public function test_navigates_to_another_date(): void
    {
        $this->get('/?date=2026-10-15')
            ->assertOk()
            ->assertSee('Четверг')
            ->assertSee('12–18 октября');
    }

    public function test_ignores_invalid_date(): void
    {
        $this->get('/?date=nonsense')->assertOk()->assertSee('Вторник');
    }

    public function test_creates_task(): void
    {
        $this->post('/tasks', ['title' => '  Звонок  ', 'date' => '2026-10-07', 'time' => '14:00'])
            ->assertRedirect('/?date=2026-10-07');

        $this->assertDatabaseHas('tasks', ['title' => 'Звонок', 'time' => '14:00', 'done' => false]);
    }

    public function test_creates_task_without_time(): void
    {
        $this->post('/tasks', ['title' => 'Что-нибудь', 'date' => '2026-10-07', 'time' => ''])
            ->assertRedirect();

        $this->assertDatabaseHas('tasks', ['title' => 'Что-нибудь', 'time' => null]);
    }

    public function test_validates_task(): void
    {
        $this->from('/')
            ->post('/tasks', ['title' => '', 'date' => '2026-10-07', 'time' => '25:00'])
            ->assertRedirect('/')
            ->assertSessionHasErrors(['title', 'time']);

        $this->assertDatabaseCount('tasks', 0);
    }

    public function test_toggles_task_via_json(): void
    {
        $task = Task::factory()->create();

        $this->patchJson("/tasks/{$task->id}/toggle")
            ->assertOk()
            ->assertJsonPath('task.done', true);

        $this->patchJson("/tasks/{$task->id}/toggle")->assertJsonPath('task.done', false);
    }

    public function test_updates_task(): void
    {
        $task = Task::factory()->create(['title' => 'Старое']);

        $this->put("/tasks/{$task->id}", ['title' => 'Новое', 'date' => '2026-10-09', 'time' => '09:30'])
            ->assertRedirect('/?date=2026-10-09');

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'title' => 'Новое', 'time' => '09:30']);
    }

    public function test_deleting_task_can_be_undone(): void
    {
        $task = Task::factory()->create(['title' => 'Случайно удалённая']);

        $response = $this->deleteJson("/tasks/{$task->id}");

        $response->assertOk();
        $response->assertJsonPath('restore_url', route('tasks.restore', $task));
        $this->assertSoftDeleted($task);
        $this->get('/')->assertDontSee('Случайно удалённая');

        $this->postJson("/tasks/{$task->id}/restore")->assertOk();

        $this->assertNotSoftDeleted($task);
        $this->get('/')->assertSee('Случайно удалённая');
    }

    public function test_deleting_without_javascript_offers_undo_in_flash(): void
    {
        $task = Task::factory()->create();

        $response = $this->delete("/tasks/{$task->id}");

        $response->assertRedirect('/?date=2026-10-06');
        $response->assertSessionHas('undo', route('tasks.restore', $task));
        $this->followRedirects($response)->assertSee('Вернуть');
    }
}
