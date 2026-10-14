@php
    $cap = fn (string $s) => mb_strtoupper(mb_substr($s, 0, 1)) . mb_substr($s, 1);
    $sameMonth = $weekStart->month === $weekEnd->month;
    $range = $sameMonth
        ? $weekStart->format('j') . '–' . $weekEnd->translatedFormat('j F')
        : $weekStart->translatedFormat('j F') . ' – ' . $weekEnd->translatedFormat('j F');
    $isToday = $selected->isSameDay($today);
    $isCurrentWeek = $today->betweenIncluded($weekStart, $weekEnd);
    $isWeek = $view === 'week';
    $now = now();
    $title = $cap($selected->translatedFormat('l, j F'));
    $lessonsLabel = fn (int $count) => $count ? \App\Support\Plural::ru($count, 'пара', 'пары', 'пар') : '';
@endphp
@extends('layouts.app')

@section('title', $title)

@section('header-aside')
    <nav class="week-nav" aria-label="Неделя">
        <a class="icon-btn" href="{{ $plannerUrl($selected->copy()->subWeek()) }}" aria-label="Предыдущая неделя">
            <svg viewBox="0 0 16 16" aria-hidden="true"><path d="M10 3L5 8l5 5"/></svg>
        </a>
        <span class="week-nav__range">{{ $range }}</span>
        <a class="icon-btn" href="{{ $plannerUrl($selected->copy()->addWeek()) }}" aria-label="Следующая неделя">
            <svg viewBox="0 0 16 16" aria-hidden="true"><path d="M6 3l5 5-5 5"/></svg>
        </a>
        @include('partials.today-link', ['isCurrent' => $isWeek ? $isCurrentWeek : $isToday, 'href' => $plannerUrl($today)])
    </nav>
@endsection

@section('page-class', 'page--planner')

@section('content')
@include('partials.planner-bar')
@if ($isWeek)
    @include('partials.week-view')
@else
    <ol class="week {{ $filter ? 'tag--' . $filter->color : '' }}" aria-label="Дни недели">
        @foreach ($days as $day)
            <li>
                <a href="{{ $plannerUrl($day['date']) }}"
                   class="day {{ $day['isSelected'] ? 'is-selected' : '' }} {{ $day['isToday'] ? 'is-today' : '' }}"
                   data-drop-date="{{ $day['date']->toDateString() }}"
                   @if ($day['isSelected']) aria-current="date" data-selected-day @endif>
                    <span class="day__name">{{ $cap($day['date']->isoFormat('dd')) }}</span>
                    <span class="day__num">{{ $day['date']->format('j') }}</span>
                    <span class="day__meter" aria-hidden="true">
                        <span style="width: {{ $day['total'] ? round($day['done'] / $day['total'] * 100) : 0 }}%"></span>
                    </span>
                    <span class="day__count">
                        @if ($day['total'])
                            {{ $day['done'] }}/{{ $day['total'] }}
                        @else
                            &nbsp;
                        @endif
                    </span>
                    @if ($day['lessons']->isNotEmpty())
                        <span class="day__lessons">{{ \App\Support\Plural::ru($day['lessons']->count(), 'пара', 'пары', 'пар') }}</span>
                    @endif
                </a>
            </li>
        @endforeach
    </ol>

    <main class="sheet sheet--planner">
        <div class="sheet__side">
            <div class="sheet__head">
                <h1 class="sheet__title">{{ $cap($selected->translatedFormat('l')) }}<span>{{ $selected->translatedFormat('j F') }}@if ($hasLessons), {{ $weekNumber }}-я неделя, {{ $isOddWeek ? 'нечётная' : 'чётная' }}@endif</span></h1>
                <p class="sheet__progress" data-progress data-lessons="{{ $lessonsLabel($lessonCount) }}" @if ($filter) data-filter-name="{{ $filter->name }}" @endif>
                    @if ($filter)
                        @if ($dayTotal)
                            «{{ $filter->name }}»: готово {{ $dayDone }} из {{ $dayTotal }}
                        @else
                            Нет задач с тегом «{{ $filter->name }}»
                        @endif
                    @elseif ($dayTotal)
                        Готово {{ $dayDone }} из {{ $dayTotal }}@if ($lessonCount) · {{ $lessonsLabel($lessonCount) }}@endif
                    @elseif ($lessonCount)
                        Задач нет · {{ $lessonsLabel($lessonCount) }}
                    @else
                        Пока ничего не запланировано
                    @endif
                </p>
            </div>

            @if ($overlay)
                <p class="free-together {{ $overlay->colorClass() }}">
                    <span class="free-together__title">Наложен график: {{ $overlay->isMe() ? 'мой' : $overlay->name() }}</span>
                    @if ($freeTogether)
                        Оба свободны:
                        @foreach ($freeTogether as $window)
                            <span class="free-together__slot">{{ $window['from'] }}–{{ $window['to'] }}</span>
                        @endforeach
                    @else
                        Общего свободного времени в этот день нет
                    @endif
                </p>
            @endif

            @include('partials.tag-filter')

            <form method="POST" action="{{ route('tasks.store') }}" id="composer" data-parse-url="{{ route('tasks.parse') }}">
                @csrf
                <input type="hidden" name="date" value="{{ $selected->toDateString() }}">
                @include('partials.filter-input')
                @include('partials.owner-input')
                <div class="composer">
                    <label class="visually-hidden" for="composer-title">Задача</label>
                    <input id="composer-title" class="composer__title" type="text" name="title" value="{{ old('title') }}"
                           placeholder="Что нужно сделать?" maxlength="255" autocomplete="off" required>
                    <label class="visually-hidden" for="composer-time">Время начала</label>
                    <input id="composer-time" class="composer__time" type="time" name="time" value="{{ old('time') }}" step="300">
                    <label class="composer__until" for="composer-ends">до</label>
                    <input id="composer-ends" class="composer__time" type="time" name="ends_at" value="{{ old('ends_at') }}" step="300">
                    <button class="btn btn--primary" type="submit">Добавить</button>
                </div>
                <p class="composer__preview" data-quick-preview hidden aria-live="polite"></p>
                @include('partials.tag-picker', ['selected' => array_map('intval', (array) old('tags', $filter ? [$filter->id] : [])), 'withAdd' => true])
            </form>
            <p class="composer__hint">
                Дату, время и теги можно писать прямо в тексте: «в пятницу 10:00-11:30 отчёт #работа».
                Без времени задача попадёт в «В течение дня».
                @unless ($hasLessons)
                    <a href="{{ route('schedule') }}">Добавить расписание пар</a>
                @endunless
            </p>

            @if ($errors->any())
                <div class="alert" role="alert">
                    @foreach ($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            <section class="anytime {{ $untimed->isEmpty() && $ghostUntimed->isEmpty() ? 'is-empty' : '' }}" aria-labelledby="anytime-title" data-drop-untimed>
                <h2 id="anytime-title" class="section-title">В течение дня</h2>
                @if ($untimed->isNotEmpty())
                    <ul class="tasks">
                        @foreach ($untimed as $task)
                            @include('partials.task', ['task' => $task])
                        @endforeach
                    </ul>
                @endif
                @if ($ghostUntimed->isNotEmpty())
                    <ul class="tasks">
                        @foreach ($ghostUntimed as $task)
                            @include('partials.ghost', ['entry' => ['type' => 'task', 'item' => $task]])
                        @endforeach
                    </ul>
                @endif
                <p class="anytime__drop">Перетащите сюда, чтобы убрать время</p>
            </section>
        </div>

        <div class="sheet__schedule" data-schedule>
            @if ($overlay)
                <div class="timeline-heads" aria-hidden="true">
                    <span class="{{ $owner->colorClass() }}">{{ $owner->isMe() ? 'Мой график' : $owner->name() }}</span>
                    <span class="{{ $overlay->colorClass() }}">{{ $overlay->isMe() ? 'Мой график' : $overlay->name() }}</span>
                </div>
            @endif
            <section class="timeline{{ $overlay ? ' is-split' : '' }}" aria-label="Расписание по часам" @if ($isToday) data-today @endif>
                @foreach ($hours as $hour)
                    @php $label = sprintf('%02d:00', $hour); @endphp
                    <div class="hour {{ $isToday && $hour < $now->hour ? 'is-past' : '' }} {{ in_array($hour, $busyHours) ? 'is-busy' : (in_array($hour, $plannedHours) ? 'is-planned' : '') }}" data-hour="{{ $hour }}" data-drop-hour="{{ $hour }}">
                        <span class="hour__label">{{ $label }}</span>
                        <div class="hour__body">
                            <button type="button" class="hour__add" data-add-at="{{ $label }}" aria-label="Добавить задачу на {{ $label }}">
                                <span>+ {{ $label }}</span>
                            </button>
                        </div>
                    </div>
                @endforeach

                {{-- Записи поверх сетки: начало и высота блока — по времени, пересекающиеся встают рядом. --}}
                <div class="timeline__events">
                    @foreach ($events as $event)
                        <ul class="event event--{{ $event['column'] }} {{ $event['span'] < 1 ? 'is-short' : '' }} {{ $overlay || $event['lanes'] > 1 ? 'is-narrow' : '' }}"
                            style="--top: {{ $event['top'] }}; --span: {{ $event['span'] }}; --lane: {{ $event['lane'] }}; --lanes: {{ $event['lanes'] }}; --stack: {{ $event['stack'] }}">
                            @if ($event['ghost'])
                                @include('partials.ghost', ['entry' => $event])
                            @elseif ($event['type'] === 'lesson')
                                @include('partials.lesson', ['lesson' => $event['item']])
                            @else
                                @include('partials.task', ['task' => $event['item']])
                            @endif
                        </ul>
                    @endforeach
                </div>

                @if ($isToday)
                    <div class="now" data-now aria-hidden="true"><span>{{ $now->format('H:i') }}</span></div>
                @endif
            </section>
        </div>
    </main>
@endif
@endsection

@section('dialogs')
<dialog class="modal modal--wide" id="edit-dialog" aria-labelledby="edit-title">
    <form method="POST" class="modal__form" id="edit-form">
        @csrf
        @method('PUT')
        @include('partials.filter-input')
        <input type="hidden" name="items_sent" value="1">
        <h2 id="edit-title" class="modal__title">Изменить задачу</h2>
        <label class="field">
            <span>Задача</span>
            <input type="text" name="title" maxlength="255" required>
        </label>
        <div class="field-row">
            <label class="field">
                <span>День</span>
                <input type="date" name="date" required>
            </label>
            <label class="field field--time">
                <span>Начало</span>
                <input type="time" name="time" step="300">
            </label>
            <label class="field field--time">
                <span>Окончание</span>
                <input type="time" name="ends_at" step="300">
            </label>
        </div>
        <label class="field">
            <span>Описание</span>
            <textarea name="description" rows="3" maxlength="5000" placeholder="Подробности, ссылки, адрес…"></textarea>
        </label>
        <div class="field">
            <span>Чек-лист <span class="checklist__count" data-checklist-count></span></span>
            <ul class="checklist" data-checklist></ul>
            <div class="checklist__add">
                <label class="visually-hidden" for="checklist-new">Новый пункт чек-листа</label>
                <input type="text" id="checklist-new" maxlength="255" placeholder="Новый пункт" data-checklist-input>
                <button type="button" class="btn btn--ghost" data-checklist-add>Добавить</button>
            </div>
        </div>
        @if ($tags->isNotEmpty())
            <div class="field">
                <span>Теги</span>
                @include('partials.tag-picker', ['selected' => [], 'withAdd' => false])
            </div>
        @endif
        <div class="modal__actions">
            <button type="button" class="btn btn--ghost" data-close>Отмена</button>
            <button type="submit" class="btn btn--primary">Сохранить</button>
        </div>
    </form>
</dialog>

<template id="checklist-row">
    <li class="checklist__row">
        <input type="checkbox" class="checklist__done" aria-label="Пункт выполнен">
        <input type="text" class="checklist__title" maxlength="255" required aria-label="Текст пункта">
        <button type="button" class="icon-btn" data-checklist-remove aria-label="Удалить пункт">
            <svg viewBox="0 0 16 16" aria-hidden="true"><path d="M4 4l8 8M12 4l-8 8"/></svg>
        </button>
    </li>
</template>

@include('partials.tags-dialog')

<dialog class="modal modal--wide" id="export-dialog" aria-labelledby="export-title">
    <form method="GET" action="{{ route('export') }}" class="modal__form" id="export-form">
        <div class="modal__head">
            <h2 id="export-title" class="modal__title">Экспорт</h2>
            <button type="button" class="icon-btn" data-close aria-label="Закрыть">
                <svg viewBox="0 0 16 16" aria-hidden="true"><path d="M4 4l8 8M12 4l-8 8"/></svg>
            </button>
        </div>

        <input type="hidden" name="date" value="{{ $selected->toDateString() }}">
        @include('partials.owner-input')

        <fieldset class="segmented">
            <legend>Что выгрузить</legend>
            <label class="chip chip--plain">
                <input type="radio" name="period" value="day" @checked(! $isWeek)>
                День, {{ $selected->translatedFormat('j F') }}
            </label>
            <label class="chip chip--plain">
                <input type="radio" name="period" value="week" @checked($isWeek)>
                Неделя {{ $range }}
            </label>
        </fieldset>

        <fieldset class="export-formats">
            <legend>Формат</legend>
            @foreach ([
                'html' => ['Страница HTML', 'Открывается в браузере телефона без сервера, с вашим оформлением.'],
                'svg' => ['Картинка SVG', 'Чёткая при любом увеличении. Открывается в браузере.'],
                'png' => ['Картинка PNG', 'Обычное изображение: сохранить в «Фото» или поставить на экран блокировки.'],
            ] as $format => [$label, $hint])
                <label class="export-format">
                    <input type="radio" name="format" value="{{ $format }}" @checked($format === 'html')>
                    <span class="export-format__label">{{ $label }}</span>
                    <span class="export-format__hint">{{ $hint }}</span>
                </label>
            @endforeach
        </fieldset>

        @if ($filter)
            <label class="check-row">
                <input type="checkbox" name="tag" value="{{ $filter->id }}" checked>
                Только задачи с тегом «{{ $filter->name }}»
            </label>
        @endif

        @if ($overlay)
            <label class="check-row">
                <input type="checkbox" name="with" value="{{ $overlay->key() }}" checked>
                Добавить наложенный график: {{ $overlay->isMe() ? 'мой' : $overlay->name() }}
            </label>
        @endif

        <p class="export-error" data-export-error hidden role="alert">Не получилось подготовить PNG. Попробуйте формат SVG.</p>

        <div class="modal__actions">
            <button type="button" class="btn btn--ghost" data-close>Отмена</button>
            <button type="submit" class="btn btn--primary" data-export-submit>Скачать</button>
        </div>
    </form>
</dialog>
@endsection

@push('scripts')
    <script src="{{ asset('js/planner.js') }}?v={{ filemtime(public_path('js/planner.js')) }}" defer></script>
@endpush
