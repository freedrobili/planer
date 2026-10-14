<?php

namespace App\Http\Controllers;

use App\Models\Habit;
use App\Models\Tag;
use App\Support\HabitProgress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class HabitController extends Controller
{
    public function index(Request $request): View
    {
        $today = Carbon::today();
        $selected = $this->parseDate($request->query('date')) ?? $today;
        $weekStart = $selected->copy()->startOfWeek(Carbon::MONDAY);
        $days = collect(range(0, 6))->map(fn (int $offset) => $weekStart->copy()->addDays($offset));

        $habits = Habit::with('checks')->orderBy('position')->orderBy('id')->get();

        $rows = $habits->map(function (Habit $habit) use ($today): array {
            $counts = $habit->checks->pluck('count', 'date')->all();

            return [
                'habit' => $habit,
                'counts' => $counts,
                'progress' => HabitProgress::for($habit, $counts, $today),
            ];
        });

        return view('habits', [
            'rows' => $rows,
            'days' => $days,
            'today' => $today,
            'weekStart' => $weekStart,
            'weekEnd' => $weekStart->copy()->addDays(6),
            'colors' => Tag::COLORS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $habit = Habit::create($this->validated($request) + [
            'position' => (int) Habit::max('position') + 1,
        ]);

        return redirect()->route('habits', $this->weekQuery($request))
            ->with('status', "Привычка «{$habit->name}» добавлена");
    }

    public function update(Request $request, Habit $habit): RedirectResponse
    {
        $habit->update($this->validated($request));

        return redirect()->route('habits', $this->weekQuery($request))
            ->with('status', "Привычка «{$habit->name}» сохранена");
    }

    public function destroy(Request $request, Habit $habit): RedirectResponse
    {
        $habit->delete();

        return redirect()->route('habits', $this->weekQuery($request))
            ->with('status', "Привычка «{$habit->name}» удалена");
    }

    /**
     * Нажатие на клетку дня: +1 к счёту, а после достижения цели — сброс на 0.
     * Для простой привычки это переключение «отмечено / не отмечено».
     */
    public function check(Request $request, Habit $habit, string $date): JsonResponse|RedirectResponse
    {
        $day = $this->parseDate($date);
        $today = Carbon::today();

        abort_if($day === null, 404);
        abort_if($day->gt($today), 422, 'Будущие дни отмечать нельзя.');
        abort_unless($habit->isScheduledOn($day), 422, 'В этот день привычка не запланирована.');

        $check = $habit->checks()->firstOrNew(['date' => $day->toDateString()]);
        $check->count = $check->count >= $habit->target ? 0 : $check->count + 1;
        $check->save();

        if (! $request->expectsJson()) {
            return redirect()->route('habits', ['date' => $day->toDateString()]);
        }

        $counts = $habit->checks()->pluck('count', 'date')->all();

        return response()->json([
            'count' => $check->count,
            'target' => $habit->target,
            'done' => $check->count >= $habit->target,
            'progress' => HabitProgress::for($habit, $counts, $today),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $request->merge(['name' => trim((string) $request->input('name'))]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'color' => ['required', Rule::in(array_keys(Tag::COLORS))],
            'weekdays' => ['required', 'array', 'min:1'],
            'weekdays.*' => ['integer', 'between:1,7'],
            'target' => ['required', 'integer', 'between:1,'.Habit::MAX_TARGET],
        ], [
            'name.required' => 'Напишите, какую привычку отслеживать.',
            'name.max' => 'Название привычки должно быть не длиннее 60 символов.',
            'color.*' => 'Выберите цвет из предложенных.',
            'weekdays.required' => 'Отметьте хотя бы один день недели.',
            'weekdays.min' => 'Отметьте хотя бы один день недели.',
            'weekdays.array' => 'Отметьте хотя бы один день недели.',
            'weekdays.*.*' => 'Выберите дни недели из списка.',
            'target.*' => 'Цель на день — число от 1 до '.Habit::MAX_TARGET.'.',
        ]);

        $data['weekdays'] = collect($data['weekdays'])->map(fn ($day) => (int) $day)->unique()->sort()->values()->all();

        return $data;
    }

    /**
     * @return array<string, string>
     */
    private function weekQuery(Request $request): array
    {
        $week = $this->parseDate($request->input('week'));

        return $week ? ['date' => $week->toDateString()] : [];
    }

    private function parseDate(?string $value): ?Carbon
    {
        if (! $value || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        try {
            $date = Carbon::createFromFormat('!Y-m-d', $value);
        } catch (\Throwable) {
            return null;
        }

        return $date->format('Y-m-d') === $value ? $date : null;
    }
}
