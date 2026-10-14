<main class="sheet sheet--week">
    <div class="week-toolbar">
        <p class="week-toolbar__progress" data-week-progress data-lessons="{{ $lessonsLabel($weekLessonCount) }}" @if ($filter) data-filter-name="{{ $filter->name }}" @endif>
            @if ($filter)
                {{ $weekTotal ? "«{$filter->name}»: готово {$weekDone} из {$weekTotal}" : "На этой неделе нет задач с тегом «{$filter->name}»" }}
            @elseif ($weekTotal)
                За неделю готово {{ $weekDone }} из {{ $weekTotal }}@if ($weekLessonCount) · {{ $lessonsLabel($weekLessonCount) }}@endif
            @elseif ($weekLessonCount)
                Задач на неделе нет · {{ $lessonsLabel($weekLessonCount) }}
            @else
                На этой неделе пока ничего не запланировано
            @endif
        </p>
        @include('partials.tag-filter')
    </div>

    @if ($errors->any())
        <div class="alert" role="alert">
            @foreach ($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    <div class="week-grid {{ $filter ? 'tag--' . $filter->color : '' }}">
        @foreach ($days as $day)
            @php
                $dateKey = $day['date']->toDateString();
                $isEmpty = $day['untimed']->isEmpty() && $day['ghostUntimed']->isEmpty() && $day['timeline']->isEmpty();
            @endphp
            <section class="week-col {{ $day['isToday'] ? 'is-today' : '' }}" data-day="{{ $dateKey }}"
                     aria-labelledby="col-{{ $dateKey }}">
                <a class="week-col__head" href="{{ $plannerUrl($day['date'], ['view' => 'day']) }}"
                   title="Открыть день целиком">
                    <span class="week-col__name" id="col-{{ $dateKey }}">{{ $cap($day['date']->isoFormat('dd')) }}</span>
                    <span class="week-col__num">{{ $day['date']->format('j') }}</span>
                    <span class="week-col__count" data-day-count>{{ $day['total'] ? "{$day['done']}/{$day['total']}" : '' }}</span>
                </a>
                <span class="day__meter" aria-hidden="true">
                    <span data-day-meter style="width: {{ $day['total'] ? round($day['done'] / $day['total'] * 100) : 0 }}%"></span>
                </span>

                <div class="week-col__body">
                    @if ($day['untimed']->isNotEmpty() || $day['ghostUntimed']->isNotEmpty())
                        <ul class="tasks">
                            @foreach ($day['untimed'] as $task)
                                @include('partials.task', ['task' => $task])
                            @endforeach
                            @foreach ($day['ghostUntimed'] as $task)
                                @include('partials.ghost', ['entry' => ['type' => 'task', 'item' => $task]])
                            @endforeach
                        </ul>
                    @endif

                    @if ($day['timeline']->isNotEmpty())
                        <ul class="tasks">
                            @foreach ($day['timeline'] as $entry)
                                @if ($entry['ghost'])
                                    @include('partials.ghost', ['entry' => $entry])
                                @elseif ($entry['type'] === 'lesson')
                                    @include('partials.lesson', ['lesson' => $entry['item']])
                                @else
                                    @include('partials.task', ['task' => $entry['item']])
                                @endif
                            @endforeach
                        </ul>
                    @endif

                    @if ($isEmpty)
                        <p class="week-col__empty">Свободно</p>
                    @endif
                </div>

                <details class="quick-add">
                    <summary>+ Задача</summary>
                    <form method="POST" action="{{ route('tasks.store') }}" class="quick-add__form">
                        @csrf
                        <input type="hidden" name="date" value="{{ $dateKey }}">
                        @include('partials.filter-input')
                        @include('partials.owner-input')
                        @if ($filter)
                            <input type="hidden" name="tags[]" value="{{ $filter->id }}">
                        @endif
                        <label class="visually-hidden" for="quick-title-{{ $dateKey }}">Задача на {{ $day['date']->translatedFormat('j F') }}</label>
                        <input id="quick-title-{{ $dateKey }}" type="text" name="title" maxlength="255" required
                               autocomplete="off" placeholder="Что сделать?">
                        <div class="quick-add__row">
                            <label class="visually-hidden" for="quick-time-{{ $dateKey }}">Время</label>
                            <input id="quick-time-{{ $dateKey }}" type="time" name="time" step="300">
                            <button type="submit" class="btn btn--primary">Добавить</button>
                        </div>
                    </form>
                </details>
            </section>
        @endforeach
    </div>
</main>
