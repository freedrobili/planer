@php
    $conflict = ($conflicts ?? [])[$task->id] ?? null;
    $itemsDone = $task->items->where('done', true)->count();
@endphp
<li class="task {{ $task->done ? 'is-done' : '' }} {{ $task->ends_at ? 'task--block' : '' }}"
    data-task="{{ $task->id }}" data-date="{{ $task->date->toDateString() }}" data-time="{{ $task->time }}"
    data-move-url="{{ route('tasks.move', $task) }}" draggable="true">
    <form class="task__toggle" method="POST" action="{{ route('tasks.toggle', $task) }}">
        @csrf
        @method('PATCH')
        @include('partials.filter-input')
        <button type="submit" class="check" role="checkbox" aria-checked="{{ $task->done ? 'true' : 'false' }}"
                aria-label="{{ $task->done ? 'Вернуть в работу' : 'Отметить выполненной' }}: {{ $task->title }}">
            <svg viewBox="0 0 16 16" aria-hidden="true"><path d="M3.5 8.5l3 3 6-7"/></svg>
        </button>
    </form>

    @if ($task->time)
        <time class="task__time">{{ $task->timeLabel() }}</time>
    @endif
    <span class="task__main">
        <span class="task__title" data-open-task>{{ $task->title }}</span>
        @foreach ($task->tags as $tag)
            <span class="tag tag--{{ $tag->color }}">{{ $tag->name }}</span>
        @endforeach
        @if ($task->items->isNotEmpty())
            <span class="task__meta {{ $itemsDone === $task->items->count() ? 'is-complete' : '' }}" title="Чек-лист: выполнено {{ $itemsDone }} из {{ $task->items->count() }}">
                <svg viewBox="0 0 16 16" aria-hidden="true"><path d="M2.5 4.5l1.5 1.5 2.5-3M2.5 11l1.5 1.5 2.5-3M9 5h4.5M9 11.5h4.5"/></svg>
                {{ $itemsDone }}/{{ $task->items->count() }}
            </span>
        @endif
        @if ($task->description)
            <span class="task__meta" title="{{ \Illuminate\Support\Str::limit($task->description, 200) }}">
                <svg viewBox="0 0 16 16" aria-hidden="true"><path d="M3 4h10M3 8h10M3 12h6"/></svg>
                <span class="visually-hidden">Есть описание</span>
            </span>
        @endif
        @if ($conflict)
            <span class="task__conflict">Во время пары «{{ $conflict->subject }}»</span>
        @endif
    </span>

    <div class="task__actions">
        <form method="POST" action="{{ route('tasks.move', $task) }}" data-move-form>
            @csrf
            @method('PATCH')
            @include('partials.filter-input')
            <input type="hidden" name="date" value="{{ $task->date->copy()->addDay()->toDateString() }}">
            <button type="submit" class="icon-btn" aria-label="Перенести на следующий день: {{ $task->title }}" title="На следующий день">
                <svg viewBox="0 0 16 16" aria-hidden="true"><path d="M3 8h9M8.5 4.5 12 8l-3.5 3.5"/></svg>
            </button>
        </form>
        <button type="button" class="icon-btn" data-edit
                data-action="{{ route('tasks.update', $task) }}"
                data-title="{{ $task->title }}"
                data-description="{{ $task->description }}"
                data-date="{{ $task->date->toDateString() }}"
                data-time="{{ $task->time }}"
                data-ends-at="{{ $task->ends_at }}"
                data-tags="{{ $task->tags->pluck('id')->join(',') }}"
                data-items="{{ $task->items->map->only(['title', 'done'])->values()->toJson() }}"
                aria-label="Изменить: {{ $task->title }}">
            <svg viewBox="0 0 16 16" aria-hidden="true"><path d="M10.5 2.5l3 3L6 13H3v-3z"/></svg>
        </button>
        <form method="POST" action="{{ route('tasks.destroy', $task) }}" data-delete>
            @csrf
            @method('DELETE')
            @include('partials.filter-input')
            <button type="submit" class="icon-btn" aria-label="Удалить: {{ $task->title }}">
                <svg viewBox="0 0 16 16" aria-hidden="true"><path d="M4 4l8 8M12 4l-8 8"/></svg>
            </button>
        </form>
    </div>
</li>
