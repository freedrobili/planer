<?php

namespace Tests\Feature;

use App\Models\Tag;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TagTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-07 10:00:00');
    }

    public function test_default_tags_exist(): void
    {
        $this->assertEqualsCanonicalizing(['Работа', 'Личное', 'Здоровье'], Tag::pluck('name')->all());
    }

    public function test_creates_tag_and_returns_to_same_page(): void
    {
        $this->from('/?date=2026-10-09')
            ->post('/tags', ['name' => '  Учёба ', 'color' => 'violet'])
            ->assertRedirect('/?date=2026-10-09')
            ->assertSessionHas('openTags', true);

        $this->assertDatabaseHas('tags', ['name' => 'Учёба', 'color' => 'violet']);
    }

    public function test_validates_tag(): void
    {
        $this->from('/')
            ->post('/tags', ['name' => 'Работа', 'color' => 'gold'])
            ->assertRedirect('/')
            ->assertSessionHasErrorsIn('tags', ['name', 'color']);
    }

    public function test_updates_tag_keeping_its_own_name(): void
    {
        $tag = Tag::where('name', 'Работа')->first();

        $this->from('/')->put("/tags/{$tag->id}", ['name' => 'Работа', 'color' => 'orange'])
            ->assertSessionHasNoErrors();

        $this->assertSame('orange', $tag->fresh()->color);
    }

    public function test_deleting_tag_keeps_tasks(): void
    {
        $tag = Tag::factory()->create();
        $task = Task::factory()->create();
        $task->tags()->attach($tag);

        $this->from('/')->delete("/tags/{$tag->id}")->assertRedirect('/');

        $this->assertModelMissing($tag);
        $this->assertModelExists($task);
        $this->assertDatabaseCount('tag_task', 0);
    }

    public function test_creates_task_with_tags(): void
    {
        $work = Tag::where('name', 'Работа')->first();
        $health = Tag::where('name', 'Здоровье')->first();

        $this->post('/tasks', ['title' => 'Отчёт', 'date' => '2026-10-07', 'tags' => [$work->id, $health->id]]);

        $this->assertEqualsCanonicalizing([$work->id, $health->id], Task::first()->tags->pluck('id')->all());
    }

    public function test_updating_task_replaces_tags(): void
    {
        $work = Tag::where('name', 'Работа')->first();
        $task = Task::factory()->create();
        $task->tags()->attach(Tag::where('name', 'Личное')->first());

        $this->put("/tasks/{$task->id}", ['title' => 'X', 'date' => '2026-10-07', 'tags' => [$work->id]]);
        $this->assertSame([$work->id], $task->fresh()->tags->pluck('id')->all());

        $this->put("/tasks/{$task->id}", ['title' => 'X', 'date' => '2026-10-07']);
        $this->assertCount(0, $task->fresh()->tags);
    }

    public function test_rejects_unknown_tag(): void
    {
        $this->from('/')
            ->post('/tasks', ['title' => 'X', 'date' => '2026-10-07', 'tags' => [999]])
            ->assertSessionHasErrors('tags.0');
    }

    public function test_planner_shows_task_tags(): void
    {
        $task = Task::factory()->create(['title' => 'Отчёт', 'time' => '11:00']);
        $task->tags()->attach(Tag::where('name', 'Работа')->first());

        $this->get('/')
            ->assertOk()
            ->assertSeeInOrder(['Отчёт', 'tag tag--blue', 'Работа'], false)
            ->assertSee('1 задача');
    }
}
