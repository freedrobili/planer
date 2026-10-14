@use('App\Models\Lesson')
@php
    $editingId = old('lesson_id');
    $hasLessonErrors = $errors->any();
    $semesterErrors = $errors->getBag('semester');
    $v = [
        'weekday' => (int) old('weekday', session('lastWeekday', 1)),
        'weekdays' => array_map('intval', (array) old('weekdays', [session('lastWeekday', 1)])),
        'starts_at' => old('starts_at', ''),
        'ends_at' => old('ends_at', ''),
        'subject' => old('subject', ''),
        'kind' => old('kind', ''),
        'room' => old('room', ''),
        'group_name' => old('group_name', ''),
        'weeks' => old('weeks', 'all'),
    ];
@endphp
@extends('layouts.app')

@section('title', 'Расписание пар')

@section('page-class', 'page--wide')

@section('header-aside')
    <p class="week-badge">
        @if ($inSemester)
            Сейчас {{ $weekNumber }}-я неделя, {{ $isOdd ? 'нечётная' : 'чётная' }}
        @else
            Семестр сейчас не идёт
        @endif
    </p>
@endsection

@section('content')
    <main class="sheet">
        <div class="sheet__head">
            @if ($owner->isMe())
                <h1 class="sheet__title">Расписание пар<span>Пары появляются в планере рядом с задачами — в тот же день и час.</span></h1>
            @else
                <h1 class="sheet__title">Пары: {{ $owner->name() }}<span>Это расписание видно в планере этого человека и при наложении графиков.</span></h1>
            @endif
        </div>

        @if ($owners->count() > 1)
            <nav class="people-switch people-switch--page" aria-label="Чьё расписание">
                @foreach ($owners as $item)
                    <a class="chip person-chip {{ $item->colorClass() }}" href="{{ route('schedule', ['person' => $item->key()]) }}"
                       @if ($item->is($owner)) aria-current="page" @endif>{{ $item->name() }}</a>
                @endforeach
            </nav>
        @endif

        <div class="weekdays">
            @foreach ($weekdays as $day => $name)
                <section class="weekday" aria-labelledby="weekday-{{ $day }}">
                    <h2 class="weekday__name" id="weekday-{{ $day }}">{{ $name }}</h2>

                    @if ($lessons->has($day))
                        <ul class="lesson-list">
                            @foreach ($lessons[$day] as $lesson)
                                <li class="lesson-row">
                                    <span class="lesson-row__time">{{ $lesson->starts_at }}–{{ $lesson->ends_at }}</span>
                                    <span class="lesson-row__main">
                                        <span class="lesson__subject">{{ $lesson->subject }}</span>
                                        <span class="lesson__meta">
                                            @if ($lesson->kindLabel())<span>{{ $lesson->kindLabel() }}</span>@endif
                                            @if ($lesson->room)<span>ауд. {{ $lesson->room }}</span>@endif
                                            @if ($lesson->group_name)<span>{{ $lesson->group_name }}</span>@endif
                                            @if ($lesson->weeks !== 'all')<span class="lesson__weeks">{{ mb_strtolower(Lesson::WEEKS[$lesson->weeks]) }}</span>@endif
                                        </span>
                                    </span>
                                    <span class="lesson-row__actions">
                                        <button type="button" class="icon-btn" data-duplicate-lesson
                                                data-weekday="{{ $lesson->weekday }}"
                                                data-starts-at="{{ $lesson->starts_at }}"
                                                data-ends-at="{{ $lesson->ends_at }}"
                                                data-subject="{{ $lesson->subject }}"
                                                data-kind="{{ $lesson->kind }}"
                                                data-room="{{ $lesson->room }}"
                                                data-group-name="{{ $lesson->group_name }}"
                                                data-weeks="{{ $lesson->weeks }}"
                                                aria-label="Дублировать пару: {{ $lesson->subject }}" title="Дублировать в другие дни">
                                            <svg viewBox="0 0 16 16" aria-hidden="true"><rect x="5.5" y="5.5" width="8" height="8" rx="1.5"/><path d="M10.5 3.5v-.5a1 1 0 0 0-1-1h-6a1 1 0 0 0-1 1v6a1 1 0 0 0 1 1h.5"/></svg>
                                        </button>
                                        <button type="button" class="icon-btn" data-edit-lesson
                                                data-id="{{ $lesson->id }}"
                                                data-action="{{ route('lessons.update', $lesson) }}"
                                                data-weekday="{{ $lesson->weekday }}"
                                                data-starts-at="{{ $lesson->starts_at }}"
                                                data-ends-at="{{ $lesson->ends_at }}"
                                                data-subject="{{ $lesson->subject }}"
                                                data-kind="{{ $lesson->kind }}"
                                                data-room="{{ $lesson->room }}"
                                                data-group-name="{{ $lesson->group_name }}"
                                                data-weeks="{{ $lesson->weeks }}"
                                                aria-label="Изменить пару: {{ $lesson->subject }}">
                                            <svg viewBox="0 0 16 16" aria-hidden="true"><path d="M10.5 2.5l3 3L6 13H3v-3z"/></svg>
                                        </button>
                                        <form method="POST" action="{{ route('lessons.destroy', $lesson) }}"
                                              data-confirm="Удалить пару «{{ $lesson->subject }}» ({{ mb_strtolower($name) }}, {{ $lesson->starts_at }})?">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="icon-btn" aria-label="Удалить пару: {{ $lesson->subject }}">
                                                <svg viewBox="0 0 16 16" aria-hidden="true"><path d="M4 4l8 8M12 4l-8 8"/></svg>
                                            </button>
                                        </form>
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    <button type="button" class="weekday__add" data-add-lesson data-weekday="{{ $day }}">+ Добавить пару</button>
                </section>
            @endforeach
        </div>

        <section class="semester" aria-labelledby="semester-title">
            <div class="semester__intro">
                <h2 class="section-title" id="semester-title">Семестр</h2>
                <p class="muted">
                    Необязательно. С датами пары показываются только внутри семестра,
                    а чётность недели считается от его первой недели.
                </p>
            </div>
            <form method="POST" action="{{ route('schedule.semester') }}" class="semester__form">
                @csrf
                @method('PUT')
                <label class="field">
                    <span>Начало</span>
                    <input type="date" name="semester_start" value="{{ old('semester_start', $timetable->semesterStart?->toDateString()) }}">
                </label>
                <label class="field">
                    <span>Окончание</span>
                    <input type="date" name="semester_end" value="{{ old('semester_end', $timetable->semesterEnd?->toDateString()) }}">
                </label>
                <button type="submit" class="btn btn--ghost">Сохранить даты</button>
            </form>
            @if ($semesterErrors->any())
                <div class="alert" role="alert">
                    @foreach ($semesterErrors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            @endif
        </section>
    </main>
@endsection

@section('dialogs')
<dialog class="modal modal--wide" id="lesson-dialog" aria-labelledby="lesson-title"
        @if ($hasLessonErrors) data-open-on-load @endif>
    <form method="POST" class="modal__form" id="lesson-form"
          action="{{ $editingId ? route('lessons.update', $editingId) : route('lessons.store') }}"
          data-store-action="{{ route('lessons.store') }}">
        @csrf
        <input type="hidden" name="_method" value="PUT" @disabled(! $editingId)>
        <input type="hidden" name="lesson_id" value="{{ $editingId }}">
        @include('partials.owner-input')

        <div class="modal__head">
            <h2 id="lesson-title" class="modal__title" data-lesson-title>{{ $editingId ? 'Изменить пару' : 'Новая пара' }}</h2>
            <button type="button" class="icon-btn" data-close aria-label="Закрыть">
                <svg viewBox="0 0 16 16" aria-hidden="true"><path d="M4 4l8 8M12 4l-8 8"/></svg>
            </button>
        </div>

        <fieldset class="segmented" data-mode="create" @if ($editingId) hidden disabled @endif>
            <legend>Дни недели — можно отметить несколько</legend>
            @foreach (Lesson::WEEKDAYS_SHORT as $num => $short)
                <label class="chip chip--plain" title="{{ Lesson::WEEKDAYS[$num] }}">
                    <input type="checkbox" name="weekdays[]" value="{{ $num }}" @checked(in_array($num, $v['weekdays'], true))>
                    {{ $short }}
                </label>
            @endforeach
        </fieldset>

        <div class="field-row">
            <label class="field" data-mode="edit" @unless ($editingId) hidden @endunless>
                <span>День</span>
                <select name="weekday" @unless ($editingId) disabled @endunless>
                    @foreach (Lesson::WEEKDAYS as $num => $name)
                        <option value="{{ $num }}" @selected($v['weekday'] === $num)>{{ $name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="field field--time">
                <span>Начало</span>
                <input type="time" name="starts_at" value="{{ $v['starts_at'] }}" required>
            </label>
            <label class="field field--time">
                <span>Конец</span>
                <input type="time" name="ends_at" value="{{ $v['ends_at'] }}" required>
            </label>
        </div>

        @if ($slots->isNotEmpty())
            <div class="slot-picker" role="group" aria-label="Время уже добавленных пар">
                @foreach ($slots as $slot)
                    <button type="button" class="chip chip--plain" data-slot="{{ $slot }}">{{ $slot }}</button>
                @endforeach
            </div>
        @endif

        <label class="field">
            <span>Предмет</span>
            <input type="text" name="subject" value="{{ $v['subject'] }}" maxlength="100" required autocomplete="off"
                   placeholder="Например, Математический анализ">
        </label>

        <div class="field-row">
            <label class="field">
                <span>Тип занятия</span>
                <select name="kind">
                    <option value="">Не указан</option>
                    @foreach (Lesson::KINDS as $key => $label)
                        <option value="{{ $key }}" @selected($v['kind'] === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="field">
                <span>Аудитория</span>
                <input type="text" name="room" value="{{ $v['room'] }}" maxlength="30" autocomplete="off">
            </label>
        </div>

        <label class="field">
            <span>Группа (подгруппа)</span>
            <input type="text" name="group_name" value="{{ $v['group_name'] }}" maxlength="100" autocomplete="off"
                   placeholder="Например, ИС-21 (1 подгруппа)">
        </label>

        <fieldset class="segmented">
            <legend>Недели</legend>
            @foreach (Lesson::WEEKS as $key => $label)
                <label class="chip chip--plain">
                    <input type="radio" name="weeks" value="{{ $key }}" @checked($v['weeks'] === $key)>
                    {{ $label }}
                </label>
            @endforeach
        </fieldset>

        <p class="form-error" data-days-error hidden role="alert">Отметьте хотя бы один день недели.</p>

        @if ($hasLessonErrors)
            <div class="alert" role="alert">
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <div class="modal__actions">
            <button type="button" class="btn btn--ghost" data-close>Отмена</button>
            <button type="submit" class="btn btn--primary" data-lesson-submit>{{ $editingId ? 'Сохранить' : 'Добавить пару' }}</button>
        </div>
    </form>
</dialog>
@endsection

@push('scripts')
    <script src="{{ asset('js/schedule.js') }}?v={{ filemtime(public_path('js/schedule.js')) }}" defer></script>
@endpush
