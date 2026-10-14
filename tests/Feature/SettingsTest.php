<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-08 10:00:00');
    }

    /**
     * @return array<string, string|int>
     */
    private function validSettings(array $overrides = []): array
    {
        return array_merge([
            'theme' => 'dark',
            'accent' => '#0F7B5F',
            'background' => 'sky',
            'font' => 'classic',
            'text_size' => 'l',
            'radius' => 'soft',
            'density' => 'compact',
            'width' => 'wide',
            'lesson_color' => '#2e6f8e',
            'day_start' => 8,
            'day_end' => 20,
        ], $overrides);
    }

    public function test_pages_use_default_appearance_when_nothing_is_saved(): void
    {
        $response = $this->get('/settings');

        $response->assertOk();
        $response->assertDontSee('<html lang="ru" data-theme', false);
        $response->assertSee('--accent-base:#2e49d6;', false);
        $response->assertSee('family=Unbounded', false);
    }

    public function test_saving_settings_applies_them_to_every_page(): void
    {
        $response = $this->put('/settings', $this->validSettings());

        $response->assertRedirect('/settings');
        $response->assertSessionHas('status', 'Оформление сохранено');
        $this->assertSame('#0f7b5f', Setting::get('appearance.accent'));
        $this->assertSame('dark', Setting::get('appearance.theme'));

        $page = $this->get('/schedule');

        $page->assertOk();
        $page->assertSee('<html lang="ru" data-theme="dark"', false);
        $page->assertSee('--accent-base:#0f7b5f;', false);
        $page->assertSee('--lesson-base:#2e6f8e;', false);
        $page->assertSee('--page-w:64rem;', false);
        $page->assertSee('--paper:#0e1320;', false);
        $page->assertSee('family=Lora', false);
    }

    public function test_invalid_settings_are_rejected_with_messages(): void
    {
        $response = $this->from('/settings')->put('/settings', $this->validSettings([
            'accent' => 'red',
            'day_start' => 18,
            'day_end' => 9,
        ]));

        $response->assertRedirect('/settings');
        $response->assertSessionHasErrors([
            'accent' => 'Акцентный цвет должен быть в формате #RRGGBB.',
            'day_end' => 'День не может заканчиваться раньше, чем начинается.',
        ]);
        $this->assertDatabaseCount('settings', 0);
    }

    public function test_tampered_stored_value_falls_back_to_default(): void
    {
        Setting::put('appearance.accent', '#000;}body{display:none');
        Setting::put('appearance.font', 'comic');

        $response = $this->get('/');

        $response->assertOk();
        $response->assertDontSee('display:none', false);
        $response->assertSee('--accent-base:#2e49d6;', false);
        $response->assertSee('family=Unbounded', false);
    }

    public function test_preview_returns_css_without_saving(): void
    {
        $response = $this->postJson('/settings/preview', $this->validSettings(['theme' => 'light']));

        $response->assertOk();
        $response->assertJsonPath('theme', 'light');
        $this->assertStringContainsString('--accent-base:#0f7b5f;', $response->json('css'));
        $this->assertStringContainsString('family=Lora', $response->json('fontsUrl'));
        $this->assertDatabaseCount('settings', 0);
    }

    public function test_theme_toggle_changes_only_the_theme(): void
    {
        $this->put('/settings', $this->validSettings(['theme' => 'light']));

        $response = $this->patchJson('/settings/theme', ['theme' => 'dark']);

        $response->assertOk();
        $response->assertJsonPath('theme', 'dark');
        $this->assertSame('dark', Setting::get('appearance.theme'));
        $this->assertSame('#0f7b5f', Setting::get('appearance.accent'));
    }

    public function test_theme_toggle_rejects_unknown_theme(): void
    {
        $response = $this->patchJson('/settings/theme', ['theme' => 'sepia']);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['theme' => 'Выберите тему: как в системе, светлую или тёмную.']);
        $this->assertDatabaseCount('settings', 0);
    }

    public function test_reset_restores_default_appearance(): void
    {
        $this->put('/settings', $this->validSettings());

        $response = $this->delete('/settings');

        $response->assertRedirect('/settings');
        $this->assertDatabaseCount('settings', 0);
        $this->get('/settings')->assertSee('--accent-base:#2e49d6;', false);
    }

    public function test_planner_shows_configured_day_hours(): void
    {
        $this->put('/settings', $this->validSettings(['day_start' => 9, 'day_end' => 18]));

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('data-hour="9"', false);
        $response->assertSee('data-hour="18"', false);
        $response->assertDontSee('data-hour="8"', false);
        $response->assertDontSee('data-hour="19"', false);
    }

    public function test_planner_extends_configured_hours_for_earlier_task(): void
    {
        $this->put('/settings', $this->validSettings(['day_start' => 9]));
        Task::factory()->create(['time' => '07:30']);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('data-hour="7"', false);
    }
}
