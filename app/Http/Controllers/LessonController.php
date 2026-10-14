<?php

namespace App\Http\Controllers;

use App\Models\Lesson;
use App\Models\Setting;
use App\Support\Owner;
use App\Support\Plural;
use App\Support\Timetable;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class LessonController extends Controller
{
    public function index(Request $request)
    {
        $owner = Owner::current($request);
        $lessons = Lesson::ownedBy($owner)->ordered()->get();
        $timetable = Timetable::load();
        $today = Carbon::today();

        // Будни показываем всегда, воскресенье — только если в нём есть пары.
        $weekdays = collect(Lesson::WEEKDAYS)
            ->filter(fn ($name, $day) => $day < 7 || $lessons->contains('weekday', 7));

        // Уже использованные интервалы пар — для быстрого заполнения времени.
        $slots = $lessons
            ->map(fn (Lesson $l) => $l->starts_at.'–'.$l->ends_at)
            ->unique()
            ->sort()
            ->values();

        return view('schedule', [
            'lessons' => $lessons->groupBy('weekday'),
            'weekdays' => $weekdays,
            'slots' => $slots,
            'timetable' => $timetable,
            'today' => $today,
            'weekNumber' => $timetable->weekNumber($today),
            'isOdd' => $timetable->isOddWeek($today),
            'inSemester' => $timetable->inSemester($today),
            'owner' => $owner,
            'owners' => Owner::all(),
        ]);
    }

    /**
     * Добавляет пару сразу в несколько дней недели: по одной записи на каждый отмеченный день.
     * Поле person — в чьё расписание; без него пара попадает в своё.
     */
    public function store(Request $request)
    {
        $data = $this->validated($request, forNewLessons: true);
        $weekdays = collect($data['weekdays'])->map(fn ($day) => (int) $day)->unique()->sort()->values();
        unset($data['weekdays']);
        $data['person_id'] = (Owner::fromKey($request->input('person')) ?? Owner::me())->personId();

        DB::transaction(function () use ($weekdays, $data): void {
            foreach ($weekdays as $weekday) {
                Lesson::create(['weekday' => $weekday] + $data);
            }
        });

        $status = $weekdays->count() === 1
            ? "Пара «{$data['subject']}» добавлена"
            : "Пара «{$data['subject']}» добавлена в ".Plural::ru($weekdays->count(), 'день', 'дня', 'дней');

        return redirect()->route('schedule')
            ->with('status', $status)
            ->with('lastWeekday', $weekdays->first());
    }

    public function update(Request $request, Lesson $lesson)
    {
        $lesson->update($this->validated($request));

        return redirect()->route('schedule')->with('status', "Пара «{$lesson->subject}» сохранена");
    }

    public function destroy(Lesson $lesson)
    {
        $lesson->delete();

        return redirect()->route('schedule')->with('status', "Пара «{$lesson->subject}» удалена");
    }

    public function semester(Request $request)
    {
        $data = $request->validateWithBag('semester', [
            'semester_start' => ['nullable', 'date_format:Y-m-d'],
            'semester_end' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:semester_start'],
        ], [
            'semester_start.date_format' => 'Дата начала семестра указана неверно.',
            'semester_end.date_format' => 'Дата окончания семестра указана неверно.',
            'semester_end.after_or_equal' => 'Семестр не может закончиться раньше, чем начался.',
        ]);

        Setting::put('semester_start', $data['semester_start'] ?? null);
        Setting::put('semester_end', $data['semester_end'] ?? null);

        return redirect()->route('schedule')->with('status', 'Даты семестра сохранены');
    }

    /**
     * @param  bool  $forNewLessons  новая пара принимает несколько дней (weekdays[]), изменение — один день (weekday)
     * @return array<string, mixed>
     */
    private function validated(Request $request, bool $forNewLessons = false): array
    {
        foreach (['subject', 'room', 'group_name'] as $field) {
            $request->merge([$field => trim((string) $request->input($field)) ?: null]);
        }

        $days = $forNewLessons
            ? [
                'weekdays' => ['required', 'array', 'min:1'],
                'weekdays.*' => ['integer', 'between:1,7'],
            ]
            : ['weekday' => ['required', 'integer', 'between:1,7']];

        return $request->validate($days + [
            'starts_at' => ['required', 'date_format:H:i'],
            'ends_at' => ['required', 'date_format:H:i', 'after:starts_at'],
            'subject' => ['required', 'string', 'max:100'],
            'kind' => ['nullable', Rule::in(array_keys(Lesson::KINDS))],
            'room' => ['nullable', 'string', 'max:30'],
            'group_name' => ['nullable', 'string', 'max:100'],
            'weeks' => ['required', Rule::in(array_keys(Lesson::WEEKS))],
        ], [
            'weekday.*' => 'Выберите день недели.',
            'weekdays.required' => 'Отметьте хотя бы один день недели.',
            'weekdays.min' => 'Отметьте хотя бы один день недели.',
            'weekdays.array' => 'Отметьте хотя бы один день недели.',
            'weekdays.*.*' => 'Выберите дни недели из списка.',
            'starts_at.required' => 'Укажите, во сколько начинается пара.',
            'starts_at.date_format' => 'Время начала должно быть в формате ЧЧ:ММ.',
            'ends_at.required' => 'Укажите, во сколько пара заканчивается.',
            'ends_at.date_format' => 'Время окончания должно быть в формате ЧЧ:ММ.',
            'ends_at.after' => 'Пара должна заканчиваться позже, чем начинается.',
            'subject.required' => 'Напишите название предмета.',
            'subject.max' => 'Название предмета должно быть не длиннее 100 символов.',
            'kind.in' => 'Выберите тип занятия из списка.',
            'room.max' => 'Аудитория — не длиннее 30 символов.',
            'group_name.max' => 'Группа — не длиннее 100 символов.',
            'weeks.*' => 'Выберите, по каким неделям идёт пара.',
        ]);
    }
}
