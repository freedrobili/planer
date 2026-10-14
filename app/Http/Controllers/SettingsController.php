<?php

namespace App\Http\Controllers;

use App\Support\Appearance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function index(Appearance $appearance): View
    {
        return view('settings', ['current' => $appearance->all()]);
    }

    public function update(Request $request): RedirectResponse
    {
        Appearance::fromInput($request->validate(Appearance::rules(), $this->messages()))->save();

        return redirect()->route('settings')->with('status', 'Оформление сохранено');
    }

    /**
     * CSS для живого предпросмотра: страница подставляет его, пока настройки не сохранены.
     */
    public function preview(Request $request): JsonResponse
    {
        $appearance = Appearance::fromInput($request->all());

        return response()->json([
            'css' => $appearance->css(),
            'fontsUrl' => $appearance->fontsUrl(),
            'theme' => $appearance->forcedTheme(),
        ]);
    }

    /**
     * Быстрое переключение темы из шапки сайта.
     */
    public function theme(Request $request, Appearance $appearance): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'theme' => ['required', Rule::in(array_keys(Appearance::THEMES))],
        ], $this->messages());

        Appearance::fromInput(['theme' => $data['theme']] + $appearance->all())->save();

        if ($request->expectsJson()) {
            return response()->json(['theme' => $data['theme']]);
        }

        return redirect()->back();
    }

    public function reset(): RedirectResponse
    {
        Appearance::reset();

        return redirect()->route('settings')->with('status', 'Оформление сброшено к стандартному');
    }

    /**
     * @return array<string, string>
     */
    private function messages(): array
    {
        return [
            'theme.*' => 'Выберите тему: как в системе, светлую или тёмную.',
            'accent.required' => 'Выберите акцентный цвет.',
            'accent.regex' => 'Акцентный цвет должен быть в формате #RRGGBB.',
            'lesson_color.required' => 'Выберите цвет пар.',
            'lesson_color.regex' => 'Цвет пар должен быть в формате #RRGGBB.',
            'background.*' => 'Выберите фон из предложенных.',
            'font.*' => 'Выберите шрифт из предложенных.',
            'text_size.*' => 'Выберите размер текста из предложенных.',
            'radius.*' => 'Выберите скругление из предложенных.',
            'density.*' => 'Выберите плотность расписания из предложенных.',
            'width.*' => 'Выберите ширину страницы из предложенных.',
            'day_start.*' => 'Начало дня — час от 0 до 23.',
            'day_end.gte' => 'День не может заканчиваться раньше, чем начинается.',
            'day_end.*' => 'Конец дня — час от 0 до 23.',
        ];
    }
}
