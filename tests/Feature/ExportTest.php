<?php

namespace Tests\Feature;

use App\Models\Lesson;
use App\Models\Tag;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-08 10:00:00'); // четверг, неделя 5–11 октября
    }

    public function test_week_exports_as_standalone_html_file(): void
    {
        Task::factory()->create(['title' => 'Понедельничная задача', 'date' => '2026-10-05']);
        Task::factory()->create(['title' => 'Воскресная задача', 'date' => '2026-10-11', 'done' => true]);
        Task::factory()->create(['title' => 'Чужая неделя', 'date' => '2026-10-12']);
        Lesson::factory()->create(['weekday' => 3, 'subject' => 'Матанализ']);

        $response = $this->get('/export?period=week&format=html&date=2026-10-08');
        $html = $response->streamedContent();

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/html; charset=UTF-8');
        $response->assertDownload('planner-week-2026-10-05.html');
        $this->assertStringContainsString('Неделя 5–11 октября', $html);
        $this->assertStringContainsString('Готово 1 из 2', $html);
        $this->assertMatchesRegularExpression('/Понедельник, 5 октября.*Понедельничная задача.*Матанализ.*Воскресная задача/s', $html);
        $this->assertStringNotContainsString('Чужая неделя', $html);
        $this->assertStringContainsString('--accent-base:', $html);
        $this->assertStringNotContainsString('<script', $html);
    }

    public function test_day_exports_only_that_day(): void
    {
        Task::factory()->create(['title' => 'Задача четверга', 'date' => '2026-10-08']);
        Task::factory()->create(['title' => 'Задача пятницы', 'date' => '2026-10-09']);

        $response = $this->get('/export?period=day&format=html&date=2026-10-08');
        $html = $response->streamedContent();

        $response->assertOk();
        $response->assertDownload('planner-2026-10-08.html');
        $this->assertStringContainsString('Четверг, 8 октября', $html);
        $this->assertStringContainsString('Задача четверга', $html);
        $this->assertStringNotContainsString('Задача пятницы', $html);
    }

    public function test_week_exports_as_svg_image(): void
    {
        Task::factory()->create(['title' => 'Сдать лабораторную', 'date' => '2026-10-09', 'time' => '16:00']);
        Lesson::factory()->create(['weekday' => 1, 'subject' => 'История', 'starts_at' => '08:30', 'ends_at' => '10:00']);

        $response = $this->get('/export?period=week&format=svg&date=2026-10-08');
        $svg = $response->streamedContent();

        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/svg+xml');
        $response->assertDownload('planner-week-2026-10-05.svg');
        $this->assertStringStartsWith('<svg xmlns="http://www.w3.org/2000/svg"', $svg);
        $this->assertStringContainsString('Сдать лабораторную', $svg);
        $this->assertStringContainsString('08:30–10:00', $svg);
    }

    public function test_task_titles_are_escaped_in_every_format(): void
    {
        Task::factory()->create(['title' => '<script>alert(1)</script> & co', 'date' => '2026-10-08']);
        $escaped = '&lt;script&gt;alert(1)&lt;/script&gt; &amp; co';

        $html = $this->get('/export?period=day&format=html&date=2026-10-08')->streamedContent();
        $svg = $this->get('/export?period=day&format=svg&date=2026-10-08')->streamedContent();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString($escaped, $html);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $svg);
        $this->assertStringContainsString($escaped, $svg);
    }

    public function test_long_title_is_shortened_in_image(): void
    {
        Task::factory()->create(['title' => str_repeat('очень длинное название задачи ', 12), 'date' => '2026-10-08']);

        $svg = $this->get('/export?period=day&format=svg&date=2026-10-08')->streamedContent();

        $this->assertStringContainsString('…', $svg);
    }

    public function test_tag_filter_limits_exported_tasks(): void
    {
        $work = Tag::where('name', 'Работа')->first();
        Task::factory()->create(['title' => 'Отчёт', 'date' => '2026-10-08'])->tags()->attach($work);
        Task::factory()->create(['title' => 'Кино', 'date' => '2026-10-08']);

        $html = $this->get("/export?period=day&format=html&date=2026-10-08&tag={$work->id}")->streamedContent();

        $this->assertStringContainsString('Отчёт', $html);
        $this->assertStringNotContainsString('Кино', $html);
        $this->assertStringContainsString('Тег «Работа». Готово 0 из 1', $html);
    }

    public function test_unknown_format_is_rejected(): void
    {
        $response = $this->from('/')->get('/export?period=week&format=docx');

        $response->assertRedirect('/');
        $response->assertSessionHasErrors(['format' => 'Выберите формат: HTML, SVG или PNG.']);
    }
}
