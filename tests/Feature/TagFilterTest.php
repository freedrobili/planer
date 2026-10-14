<?php

namespace Tests\Feature;

use App\Models\Tag;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TagFilterTest extends TestCase
{
    use RefreshDatabase;

    private Tag $work;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-07 10:00:00');

        $this->work = Tag::where('name', 'Работа')->first();

        Task::factory()->create(['title' => 'Отчёт', 'time' => '11:00'])->tags()->attach($this->work);
        Task::factory()->create(['title' => 'Созвон', 'done' => true])->tags()->attach($this->work);
        Task::factory()->create(['title' => 'Кино', 'time' => '20:00'])
            ->tags()->attach(Tag::where('name', 'Личное')->first());
        Task::factory()->create(['title' => 'Без тега']);
    }

    public function test_shows_only_tasks_with_selected_tag(): void
    {
        $this->get("/?tag={$this->work->id}")
            ->assertOk()
            ->assertSee('Отчёт')
            ->assertSee('Созвон')
            ->assertDontSee('Кино')
            ->assertDontSee('Без тега')
            ->assertSee('«Работа»: готово 1 из 2');
    }

    public function test_without_filter_shows_everything(): void
    {
        $this->get('/')
            ->assertSee('Кино')
            ->assertSee('Без тега')
            ->assertSee('Готово 1 из 4');
    }

    public function test_unknown_tag_is_ignored(): void
    {
        $this->get('/?tag=999')->assertOk()->assertSee('Кино');
    }

    public function test_navigation_links_keep_filter(): void
    {
        $this->get("/?date=2026-10-07&tag={$this->work->id}")
            ->assertSee('?date=2026-10-14&amp;tag='.$this->work->id, false) // следующая неделя
            ->assertSee('?date=2026-10-08&amp;tag='.$this->work->id, false); // соседний день
    }

    public function test_empty_filtered_day_message(): void
    {
        $this->get("/?date=2026-10-08&tag={$this->work->id}")
            ->assertSee('Нет задач с тегом «Работа»');
    }

    public function test_new_task_redirect_keeps_filter(): void
    {
        $this->post('/tasks', [
            'title' => 'Ещё отчёт',
            'date' => '2026-10-07',
            'tags' => [$this->work->id],
            'filter' => $this->work->id,
        ])->assertRedirect("/?date=2026-10-07&tag={$this->work->id}");
    }

    public function test_composer_preselects_filter_tag(): void
    {
        $this->get("/?tag={$this->work->id}")
            ->assertSee('name="tags[]" value="'.$this->work->id.'" checked', false);
    }
}
