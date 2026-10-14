<?php

namespace Tests\Feature;

use App\Models\Lesson;
use App\Models\Person;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PeopleTest extends TestCase
{
    use RefreshDatabase;

    private Person $masha;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-07 09:30:00'); // среда
        $this->masha = Person::factory()->create(['name' => 'Маша', 'color' => 'pink']);
    }

    public function test_people_page_shows_what_each_person_is_doing_now_and_next(): void
    {
        Lesson::factory()->create(['person_id' => $this->masha->id, 'weekday' => 3, 'starts_at' => '09:00', 'ends_at' => '10:30', 'subject' => 'Физика']);
        Task::factory()->create(['person_id' => $this->masha->id, 'date' => '2026-10-07', 'time' => '15:00', 'title' => 'Бассейн']);
        Task::factory()->create(['date' => '2026-10-07', 'time' => '12:00', 'title' => 'Мой созвон']);

        $this->get('/people')
            ->assertOk()
            ->assertSeeInOrder(['Мой график', 'свободное время', '12:00', 'Мой созвон'])
            ->assertSeeInOrder(['Маша', 'Пара «Физика»', 'до 10:30', '15:00', 'Бассейн']);
    }

    public function test_adds_renames_and_validates_person(): void
    {
        $this->post('/people', ['name' => '  Папа ', 'color' => 'blue'])->assertRedirect('/people');
        $papa = Person::where('name', 'Папа')->firstOrFail();

        $this->put("/people/{$papa->id}", ['name' => 'Папа Коля', 'color' => 'green'])->assertRedirect('/people');
        $this->assertSame(['Папа Коля', 'green'], [$papa->fresh()->name, $papa->fresh()->color]);

        $this->from('/people')->post('/people', ['name' => '', 'color' => 'rainbow'])
            ->assertSessionHasErrors(['name', 'color']);
    }

    public function test_planner_shows_selected_persons_schedule_and_remembers_the_choice(): void
    {
        Task::factory()->create(['date' => '2026-10-07', 'title' => 'Моя задача']);
        Task::factory()->create(['person_id' => $this->masha->id, 'date' => '2026-10-07', 'title' => 'Задача Маши']);

        $this->get("/?person={$this->masha->id}")->assertSee('Задача Маши')->assertDontSee('Моя задача');
        $this->get('/')->assertSee('Задача Маши')->assertDontSee('Моя задача');
        $this->get('/?person=me')->assertSee('Моя задача')->assertDontSee('Задача Маши');
    }

    public function test_new_task_and_lesson_go_to_the_selected_schedule(): void
    {
        $this->post('/tasks', ['title' => 'Купить подарок', 'date' => '2026-10-07', 'person' => (string) $this->masha->id]);
        $this->post('/tasks', ['title' => 'Своё дело', 'date' => '2026-10-07']);
        $this->post('/lessons', [
            'weekdays' => [1], 'starts_at' => '09:00', 'ends_at' => '10:30', 'subject' => 'Химия',
            'weeks' => 'all', 'person' => (string) $this->masha->id,
        ]);

        $this->assertDatabaseHas('tasks', ['title' => 'Купить подарок', 'person_id' => $this->masha->id]);
        $this->assertDatabaseHas('tasks', ['title' => 'Своё дело', 'person_id' => null]);
        $this->assertDatabaseHas('lessons', ['subject' => 'Химия', 'person_id' => $this->masha->id]);
        $this->get('/schedule?person=me')->assertDontSee('<span class="lesson__subject">Химия', false);
        $this->get("/schedule?person={$this->masha->id}")
            ->assertSee('<span class="lesson__subject">Химия', false)
            ->assertSee('Пары: Маша');
    }

    public function test_overlay_shows_other_schedule_read_only_with_common_free_time(): void
    {
        Lesson::factory()->create(['weekday' => 3, 'starts_at' => '06:00', 'ends_at' => '12:00', 'subject' => 'Моя пара']);
        $mashaTask = Task::factory()->create([
            'person_id' => $this->masha->id, 'date' => '2026-10-07', 'time' => '13:00', 'ends_at' => '20:00', 'title' => 'Смена Маши',
        ]);

        $this->get("/?person=me&with={$this->masha->id}&date=2026-10-07&view=day")
            ->assertOk()
            ->assertSee('Наложен график: Маша')
            ->assertSee('Смена Маши')
            ->assertSeeInOrder(['Оба свободны', '12:00–13:00', '20:00–23:00'])
            ->assertDontSee(route('tasks.toggle', $mashaTask));

        $this->get('/?with=none&date=2026-10-07')->assertDontSee('Смена Маши');
    }

    public function test_cannot_overlay_schedule_on_itself(): void
    {
        $this->get('/?person=me&with=me')->assertOk()->assertDontSee('Наложен график');
    }

    public function test_deleting_person_removes_their_schedule_and_returns_to_mine(): void
    {
        Task::factory()->create(['person_id' => $this->masha->id, 'title' => 'Задача Маши']);
        Lesson::factory()->create(['person_id' => $this->masha->id]);
        Task::factory()->create(['title' => 'Моя задача']);
        $this->get("/?person={$this->masha->id}");

        $this->delete("/people/{$this->masha->id}")->assertRedirect('/people');

        $this->assertDatabaseCount('people', 0);
        $this->assertSame(0, Task::withTrashed()->whereNotNull('person_id')->count());
        $this->assertDatabaseCount('lessons', 0);
        $this->get('/')->assertOk()->assertSee('Моя задача');
    }

    public function test_statistics_count_only_my_tasks(): void
    {
        Task::factory()->create(['date' => '2026-10-07', 'done' => true]);
        Task::factory()->count(3)->create(['person_id' => $this->masha->id, 'date' => '2026-10-07']);

        $this->get('/stats')->assertSeeInOrder(['Эта неделя', '1', 'из 1']);
    }

    public function test_export_includes_overlaid_schedule_and_common_free_time(): void
    {
        Task::factory()->create(['date' => '2026-10-07', 'time' => '06:00', 'ends_at' => '12:00', 'title' => 'Моя смена']);
        Lesson::factory()->create(['person_id' => $this->masha->id, 'weekday' => 3, 'starts_at' => '13:00', 'ends_at' => '20:00', 'subject' => 'Практикум Маши']);
        $query = "period=day&date=2026-10-07&person=me&with={$this->masha->id}";

        $html = $this->get("/export?format=html&{$query}")->streamedContent();
        $this->assertStringContainsString('Наложен график: Маша', $html);
        $this->assertStringContainsString('Практикум Маши', $html);
        $this->assertMatchesRegularExpression('/Оба свободны:.*12:00–13:00.*20:00–23:00/s', $html);

        $svg = $this->get("/export?format=svg&{$query}")->streamedContent();
        $this->assertStringContainsString('Пара «Практикум Маши»', $svg);
        $this->assertStringContainsString('Оба свободны: 12:00–13:00, 20:00–23:00', $svg);

        $withoutOverlay = $this->get('/export?format=html&period=day&date=2026-10-07&person=me')->streamedContent();
        $this->assertStringNotContainsString('Практикум Маши', $withoutOverlay);
    }

    public function test_export_uses_selected_persons_schedule(): void
    {
        Task::factory()->create(['date' => '2026-10-07', 'title' => 'Моя задача']);
        Task::factory()->create(['person_id' => $this->masha->id, 'date' => '2026-10-07', 'title' => 'Задача Маши']);

        $html = $this->get("/export?period=day&format=html&date=2026-10-07&person={$this->masha->id}")->streamedContent();

        $this->assertStringContainsString('Маша. Среда, 7 октября', $html);
        $this->assertStringContainsString('Задача Маши', $html);
        $this->assertStringNotContainsString('Моя задача', $html);
    }
}
