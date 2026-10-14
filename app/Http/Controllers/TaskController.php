<?php

namespace App\Http\Controllers;

use App\Models\Tag;
use App\Models\Task;
use App\Support\Owner;
use App\Support\QuickInput;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TaskController extends Controller
{
    /**
     * Новая задача. Текст разбирается как быстрый ввод («завтра 15:00 созвон #работа»);
     * явно заполненные поля формы важнее найденного в тексте.
     * Поле person — чей это график; без него задача попадает в свой.
     */
    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $data = $this->validated($request);
        $pageDate = $data['date'];
        $parsed = QuickInput::parse($data['title'], Carbon::today(), Tag::pluck('name', 'id')->all());

        $data['title'] = $parsed['title'];
        $data['date'] = $parsed['date'] ?? $data['date'];
        if (empty($data['time']) && $parsed['time'] !== null) {
            $data['time'] = $parsed['time'];
            $data['ends_at'] = $data['ends_at'] ?? $parsed['ends_at'];
        }
        $data['tags'] = array_values(array_unique([...($data['tags'] ?? []), ...$parsed['tags']]));
        $data['person_id'] = (Owner::fromKey($request->input('person')) ?? Owner::me())->personId();

        $task = DB::transaction(function () use ($data): Task {
            $task = Task::create($data);
            $task->tags()->sync($data['tags']);

            return $task;
        });

        $status = $task->date->toDateString() === $pageDate
            ? 'Задача добавлена'
            : 'Задача добавлена на '.$task->date->translatedFormat('j F');

        return $this->respond($request, $task, $status, Carbon::createFromFormat('!Y-m-d', $pageDate));
    }

    /**
     * Изменение задачи из окна: поля, теги, описание и чек-лист целиком.
     */
    public function update(Request $request, Task $task): JsonResponse|RedirectResponse
    {
        $data = $this->validated($request, withDetails: true);

        DB::transaction(function () use ($task, $data, $request): void {
            $task->update($data);
            $task->tags()->sync($data['tags'] ?? []);

            if ($request->boolean('items_sent')) {
                $task->items()->delete();
                foreach (array_values($data['items'] ?? []) as $position => $item) {
                    $task->items()->create([
                        'title' => trim($item['title']),
                        'done' => (bool) ($item['done'] ?? false),
                        'position' => $position,
                    ]);
                }
            }
        });

        return $this->respond($request, $task, 'Задача сохранена');
    }

    public function toggle(Request $request, Task $task): JsonResponse|RedirectResponse
    {
        $task->update(['done' => ! $task->done]);

        return $this->respond($request, $task);
    }

    /**
     * Перенос на другой день и/или час (перетаскивание, «на завтра»).
     * Длительность задачи при смене времени сохраняется.
     */
    public function move(Request $request, Task $task): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'time' => ['sometimes', 'nullable', 'date_format:H:i'],
        ], [
            'date.*' => 'Дата должна быть в формате ГГГГ-ММ-ДД.',
            'time.*' => 'Время должно быть в формате ЧЧ:ММ.',
        ]);

        $changes = ['date' => $data['date']];

        if (array_key_exists('time', $data)) {
            $changes['time'] = $data['time'];
            $changes['ends_at'] = $this->shiftedEnd($task, $data['time']);
        }

        $sameDay = $task->date->toDateString() === $data['date'];
        $task->update($changes);

        $status = match (true) {
            ! $sameDay => 'Задача перенесена на '.$task->date->translatedFormat('j F'),
            $task->time === null => 'Время задачи убрано',
            default => 'Задача перенесена на '.$task->timeLabel(),
        };

        return $this->respond($request, $task, $status);
    }

    /**
     * Удаление «мягкое»: задачу можно вернуть кнопкой «Вернуть».
     */
    public function destroy(Request $request, Task $task): JsonResponse|RedirectResponse
    {
        $task->delete();

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Задача удалена',
                'restore_url' => route('tasks.restore', $task),
            ]);
        }

        return $this->respond($request, $task, 'Задача удалена')
            ->with('undo', route('tasks.restore', $task));
    }

    public function restore(Request $request, Task $task): JsonResponse|RedirectResponse
    {
        $task->restore();

        return $this->respond($request, $task, 'Задача возвращена');
    }

    /**
     * Подсказка под строкой ввода: как будет понят быстрый ввод.
     */
    public function parse(Request $request): JsonResponse
    {
        $tags = Tag::pluck('name', 'id');
        $parsed = QuickInput::parse((string) $request->query('text', ''), Carbon::today(), $tags->all());

        $parts = [];
        if ($parsed['date'] !== null) {
            $date = Carbon::createFromFormat('!Y-m-d', $parsed['date']);
            $parts[] = Str::ucfirst($date->isoFormat('dd')).', '.$date->translatedFormat('j F');
        }
        if ($parsed['time'] !== null) {
            $parts[] = $parsed['ends_at'] ? $parsed['time'].'–'.$parsed['ends_at'] : $parsed['time'];
        }
        foreach ($parsed['tags'] as $id) {
            $parts[] = '#'.$tags[$id];
        }

        return response()->json([
            'title' => $parsed['title'],
            'summary' => $parts === [] ? null : implode(', ', $parts),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, bool $withDetails = false): array
    {
        $rules = [
            'title' => ['required', 'string', 'max:255'],
            'date' => ['required', 'date_format:Y-m-d'],
            'time' => ['nullable', 'date_format:H:i', 'required_with:ends_at'],
            'ends_at' => ['nullable', 'date_format:H:i', 'after:time'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['integer', 'exists:tags,id'],
        ];

        if ($withDetails) {
            $rules += [
                'description' => ['nullable', 'string', 'max:5000'],
                'items' => ['nullable', 'array', 'max:50'],
                'items.*.title' => ['required', 'string', 'max:255'],
                'items.*.done' => ['nullable', 'boolean'],
            ];
        }

        $data = $request->validate($rules, [
            'title.required' => 'Напишите, что нужно сделать.',
            'title.max' => 'Название должно быть короче 255 символов.',
            'date.required' => 'Выберите день.',
            'date.date_format' => 'Дата должна быть в формате ГГГГ-ММ-ДД.',
            'time.date_format' => 'Время должно быть в формате ЧЧ:ММ.',
            'time.required_with' => 'Чтобы указать окончание, укажите и время начала.',
            'ends_at.date_format' => 'Время окончания должно быть в формате ЧЧ:ММ.',
            'ends_at.after' => 'Задача должна заканчиваться позже, чем начинается.',
            'tags.*.exists' => 'Такого тега больше нет — обновите страницу.',
            'description.max' => 'Описание должно быть короче 5000 символов.',
            'items.max' => 'В чек-листе может быть не больше 50 пунктов.',
            'items.*.title.required' => 'У пункта чек-листа должен быть текст.',
            'items.*.title.max' => 'Пункт чек-листа должен быть короче 255 символов.',
        ]);

        $data['title'] = trim($data['title']);
        $data['ends_at'] = $data['ends_at'] ?? null;

        if ($withDetails) {
            $data['description'] = trim((string) ($data['description'] ?? '')) ?: null;
        }

        return $data;
    }

    /**
     * Новое время окончания при сдвиге начала: длительность прежняя, но не позже 23:59.
     */
    private function shiftedEnd(Task $task, ?string $newTime): ?string
    {
        if ($newTime === null || $task->time === null || $task->ends_at === null) {
            return null;
        }

        $minutes = fn (string $clock): int => (int) substr($clock, 0, 2) * 60 + (int) substr($clock, 3, 2);
        $end = min($minutes($newTime) + $minutes($task->ends_at) - $minutes($task->time), 23 * 60 + 59);

        return sprintf('%02d:%02d', intdiv($end, 60), $end % 60);
    }

    private function respond(Request $request, Task $task, ?string $status = null, ?Carbon $returnTo = null): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['task' => $task->fresh(['tags', 'items']) ?? $task, 'message' => $status]);
        }

        return redirect()
            ->route('planner', array_filter([
                'date' => ($returnTo ?? $task->date)->toDateString(),
                'tag' => $request->integer('filter') ?: null,
            ]))
            ->with('status', $status);
    }
}
