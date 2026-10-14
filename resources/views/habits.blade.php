@use('App\Models\Lesson')
@use('App\Support\Plural')
@php
    $cap = fn (string $s) => mb_strtoupper(mb_substr($s, 0, 1)).mb_substr($s, 1);
    $range = $weekStart->month === $weekEnd->month
        ? $weekStart->format('j').'–'.$weekEnd->translatedFormat('j F')
        : $weekStart->translatedFormat('j F').' – '.$weekEnd->translatedFormat('j F');
    $isCurrentWeek = $today->betweenIncluded($weekStart, $weekEnd);
    $weekKey = $weekStart->toDateString();
    $days_ = fn (int $n) => Plural::ru($n, 'день', 'дня', 'дней');
@endphp
@extends('layouts.app')

@section('title', 'Привычки')

@section('page-class', 'page--wide')

@section('header-aside')
    <nav class="week-nav" aria-label="Неделя">
        <a class="icon-btn" href="{{ route('habits', ['date' => $weekStart->copy()->subWeek()->toDateString()]) }}" aria-label="Предыдущая неделя">
            <svg viewBox="0 0 16 16" aria-hidden="true"><path d="M10 3L5 8l5 5"/></svg>
        </a>
        <span class="week-nav__range">{{ $range }}</span>
        <a class="icon-btn" href="{{ route('habits', ['date' => $weekStart->copy()->addWeek()->toDateString()]) }}" aria-label="Следующая неделя">
            <svg viewBox="0 0 16 16" aria-hidden="true"><path d="M6 3l5 5-5 5"/></svg>
        </a>
        @unless ($isCurrentWeek)
            <a class="btn btn--ghost" href="{{ route('habits') }}">Сегодня</a>
        @endunless
    </nav>
@endsection

@section('content')
    <main class="sheet sheet--habits">
        <div class="sheet__head">
            <h1 class="sheet__title">Привычки<span>Отмечайте дни, когда сделали. Серия растёт, пока не пропущен ни один нужный день.</span></h1>
            <button type="button" class="btn btn--primary" data-add-habit>+ Новая привычка</button>
        </div>

        @if ($rows->isEmpty())
            <div class="habits-empty">
                <p>Привычек пока нет. Добавьте первую — например, «Вода, 8 стаканов» или «Чтение 20 минут».</p>
                <button type="button" class="btn btn--primary" data-add-habit>+ Новая привычка</button>
            </div>
        @else
            <div class="habits-scroll">
                <div class="habits" role="table" aria-label="Привычки за неделю {{ $range }}">
                    <div class="habits__row habits__row--head" role="row">
                        <span role="columnheader" class="habits__corner">Привычка</span>
                        @foreach ($days as $day)
                            <span role="columnheader" class="habits__day {{ $day->isSameDay($today) ? 'is-today' : '' }}">
                                {{ Lesson::WEEKDAYS_SHORT[$day->dayOfWeekIso] }}<b>{{ $day->format('j') }}</b>
                            </span>
                        @endforeach
                        <span role="columnheader" class="habits__stat">Серия</span>
                        <span role="columnheader" class="habits__stat">30 дней</span>
                    </div>

                    @foreach ($rows as ['habit' => $habit, 'counts' => $counts, 'progress' => $progress])
                        <div class="habits__row tag--{{ $habit->color }}" role="row" data-habit="{{ $habit->id }}">
                            <span role="rowheader" class="habit">
                                <span class="habit__dot" aria-hidden="true"></span>
                                <span class="habit__text">
                                    <span class="habit__name">{{ $habit->name }}</span>
                                    <span class="habit__meta">{{ $habit->scheduleLabel() }}@if ($habit->isCounted()), цель {{ $habit->target }} в день @endif</span>
                                </span>
                                <span class="habit__actions">
                                    <button type="button" class="icon-btn" data-edit-habit
                                            data-action="{{ route('habits.update', $habit) }}"
                                            data-name="{{ $habit->name }}"
                                            data-color="{{ $habit->color }}"
                                            data-weekdays="{{ implode(',', $habit->weekdays) }}"
                                            data-target="{{ $habit->target }}"
                                            aria-label="Изменить привычку {{ $habit->name }}">
                                        <svg viewBox="0 0 16 16" aria-hidden="true"><path d="M10.5 2.5l3 3L6 13H3v-3z"/></svg>
                                    </button>
                                    <form method="POST" action="{{ route('habits.destroy', $habit) }}"
                                          data-confirm="Удалить привычку «{{ $habit->name }}» вместе со всеми отметками?">
                                        @csrf
                                        @method('DELETE')
                                        <input type="hidden" name="week" value="{{ $weekKey }}">
                                        <button type="submit" class="icon-btn" aria-label="Удалить привычку {{ $habit->name }}">
                                            <svg viewBox="0 0 16 16" aria-hidden="true"><path d="M4 4l8 8M12 4l-8 8"/></svg>
                                        </button>
                                    </form>
                                </span>
                            </span>

                            @foreach ($days as $day)
                                @php
                                    $count = $counts[$day->toDateString()] ?? 0;
                                    $done = $count >= $habit->target;
                                    $dayName = Lesson::WEEKDAYS_SHORT[$day->dayOfWeekIso].', '.$day->translatedFormat('j F');
                                @endphp
                                <span role="cell" class="habits__cell {{ $day->isSameDay($today) ? 'is-today' : '' }}">
                                    @if (! $habit->isScheduledOn($day))
                                        <span class="habit-cell is-off" title="В этот день не нужно">
                                            <span class="visually-hidden">{{ $dayName }}: не запланировано</span>
                                        </span>
                                    @elseif ($day->gt($today))
                                        <span class="habit-cell is-future"><span class="visually-hidden">{{ $dayName }}: ещё впереди</span></span>
                                    @else
                                        <form method="POST" action="{{ route('habits.check', [$habit, $day->toDateString()]) }}" data-habit-check>
                                            @csrf
                                            <button type="submit" class="habit-cell {{ $done ? 'is-done' : '' }}"
                                                    style="--fill: {{ min(100, (int) round($count / $habit->target * 100)) }}%"
                                                    aria-pressed="{{ $done ? 'true' : 'false' }}"
                                                    aria-label="{{ $habit->name }}, {{ $dayName }}: {{ $habit->isCounted() ? "{$count} из {$habit->target}" : ($done ? 'выполнено' : 'не выполнено') }}"
                                                    title="{{ $habit->isCounted() ? 'Нажмите, чтобы добавить 1. После цели — сброс.' : 'Отметить день' }}">
                                                <svg class="habit-cell__check" viewBox="0 0 16 16" aria-hidden="true"><path d="M3.5 8.5l3 3 6-7"/></svg>
                                                @if ($habit->isCounted())
                                                    <span class="habit-cell__count" aria-hidden="true">{{ $count ?: '' }}</span>
                                                @endif
                                            </button>
                                        </form>
                                    @endif
                                </span>
                            @endforeach

                            <span role="cell" class="habit__streak" data-streak>
                                <b>{{ $days_($progress['current']) }}</b>
                                <small>лучшая {{ $progress['best'] }}</small>
                            </span>
                            <span role="cell" class="habit__rate" data-rate>{{ $progress['rate'] === null ? '—' : $progress['rate'].'%' }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </main>
@endsection

@section('dialogs')
<dialog class="modal modal--wide" id="habit-dialog" aria-labelledby="habit-title"
        @if ($errors->any()) data-open-on-load @endif>
    <form method="POST" class="modal__form" id="habit-form"
          action="{{ old('habit_id') ? route('habits.update', old('habit_id')) : route('habits.store') }}"
          data-store-action="{{ route('habits.store') }}">
        @csrf
        <input type="hidden" name="_method" value="PUT" @disabled(! old('habit_id'))>
        <input type="hidden" name="habit_id" value="{{ old('habit_id') }}">
        <input type="hidden" name="week" value="{{ $weekKey }}">

        <div class="modal__head">
            <h2 id="habit-title" class="modal__title" data-habit-title>{{ old('habit_id') ? 'Изменить привычку' : 'Новая привычка' }}</h2>
            <button type="button" class="icon-btn" data-close aria-label="Закрыть">
                <svg viewBox="0 0 16 16" aria-hidden="true"><path d="M4 4l8 8M12 4l-8 8"/></svg>
            </button>
        </div>

        <label class="field">
            <span>Привычка</span>
            <input type="text" name="name" maxlength="60" required autocomplete="off"
                   value="{{ old('name') }}" placeholder="Например, Вода или Чтение 20 минут">
        </label>

        <fieldset class="swatches">
            <legend>Цвет</legend>
            @foreach ($colors as $key => $label)
                <label class="swatch tag--{{ $key }}" title="{{ $label }}">
                    <input type="radio" name="color" value="{{ $key }}" @checked(old('color', 'green') === $key)>
                    <span class="visually-hidden">{{ $label }}</span>
                </label>
            @endforeach
        </fieldset>

        <fieldset class="segmented">
            <legend>Дни недели</legend>
            @foreach (Lesson::WEEKDAYS_SHORT as $num => $short)
                <label class="chip chip--plain" title="{{ Lesson::WEEKDAYS[$num] }}">
                    <input type="checkbox" name="weekdays[]" value="{{ $num }}"
                           @checked(in_array($num, array_map('intval', (array) old('weekdays', [1, 2, 3, 4, 5, 6, 7])), true))>
                    {{ $short }}
                </label>
            @endforeach
            <span class="habit-presets">
                <button type="button" class="link-btn" data-weekdays-preset="1,2,3,4,5,6,7">каждый день</button>
                <button type="button" class="link-btn" data-weekdays-preset="1,2,3,4,5">будни</button>
                <button type="button" class="link-btn" data-weekdays-preset="6,7">выходные</button>
            </span>
        </fieldset>

        <label class="field field--narrow">
            <span>Сколько раз в день</span>
            <input type="number" name="target" min="1" max="{{ \App\Models\Habit::MAX_TARGET }}" value="{{ old('target', 1) }}" required>
        </label>
        <p class="setting__hint">1 — просто отметить день. Больше 1 — счётчик: например, 8 стаканов воды, каждый клик +1.</p>

        <p class="form-error" data-days-error hidden role="alert">Отметьте хотя бы один день недели.</p>

        @if ($errors->any())
            <div class="alert" role="alert">
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <div class="modal__actions">
            <button type="button" class="btn btn--ghost" data-close>Отмена</button>
            <button type="submit" class="btn btn--primary" data-habit-submit>{{ old('habit_id') ? 'Сохранить' : 'Добавить привычку' }}</button>
        </div>
    </form>
</dialog>
@endsection

@push('scripts')
    <script src="{{ asset('js/habits.js') }}?v={{ filemtime(public_path('js/habits.js')) }}" defer></script>
@endpush
