<li class="task {{ $task->done ? 'is-done' : '' }}">
    <span class="check" aria-hidden="true"><svg viewBox="0 0 16 16"><path d="M3.5 8.5l3 3 6-7"/></svg></span>
    @if ($task->time)
        <time class="task__time">{{ $task->timeLabel() }}</time>
    @endif
    <span class="task__main">
        <span class="task__title">{{ $task->title }}@if ($task->done)<span class="visually-hidden"> (выполнено)</span>@endif</span>
        @foreach ($task->tags as $tag)
            <span class="tag tag--{{ $tag->color }}">{{ $tag->name }}</span>
        @endforeach
        @if ($task->items->isNotEmpty())
            <span class="task__meta">{{ $task->items->where('done', true)->count() }}/{{ $task->items->count() }}</span>
        @endif
    </span>
</li>
