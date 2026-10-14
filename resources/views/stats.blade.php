@use('App\Models\Lesson')
@use('App\Support\Plural')
@php
    $percent = fn (array $period) => $period['total'] ? (int) round($period['done'] / $period['total'] * 100) : 0;
    $days = fn (int $n) => Plural::ru($n, 'день', 'дня', 'дней');
    $bestWeekday = $byWeekday->sortByDesc('done')->first();
    $hasWindowData = $weeks->sum('total') > 0;
@endphp
@extends('layouts.app')

@section('title', 'Статистика')

@section('page-class', 'page--wide')

@section('content')
    <main class="sheet sheet--stats">
        <div class="sheet__head">
            <h1 class="sheet__title">Статистика<span>Графики — за 8 недель: {{ $windowLabel }}</span></h1>
        </div>

        <section class="kpis" aria-label="Главные показатели">
            <div class="kpi">
                <span class="kpi__label">Эта неделя</span>
                <span class="kpi__value">{{ $week['done'] }} <small>из {{ $week['total'] }}</small></span>
                <span class="meter" aria-hidden="true"><span style="width: {{ $percent($week) }}%"></span></span>
                <span class="kpi__note">{{ $week['total'] ? $percent($week).'% выполнено' : 'Задач пока нет' }}</span>
            </div>
            <div class="kpi">
                <span class="kpi__label">{{ \Illuminate\Support\Str::ucfirst($monthName) }}</span>
                <span class="kpi__value">{{ $month['done'] }} <small>из {{ $month['total'] }}</small></span>
                <span class="meter" aria-hidden="true"><span style="width: {{ $percent($month) }}%"></span></span>
                <span class="kpi__note">{{ $month['total'] ? $percent($month).'% выполнено' : 'Задач пока нет' }}</span>
            </div>
            <div class="kpi">
                <span class="kpi__label">Серия</span>
                <span class="kpi__value">{{ $days($streaks['current']) }}</span>
                <span class="kpi__note">подряд с выполненными задачами</span>
            </div>
            <div class="kpi">
                <span class="kpi__label">Лучшая серия</span>
                <span class="kpi__value">{{ $days($streaks['best']) }}</span>
                <span class="kpi__note">
                    @if ($streaks['best'] > 0 && $streaks['current'] >= $streaks['best'])
                        Рекорд — прямо сейчас
                    @else
                        за всё время
                    @endif
                </span>
            </div>
        </section>

        @if (! $hasWindowData)
            <p class="stats-empty">За последние 8 недель задач нет — графики появятся, когда вы начнёте отмечать выполненное.</p>
        @else
            <div class="stats-grid">
                <section class="chart-card" aria-labelledby="chart-weeks-title">
                    <div class="chart-card__head">
                        <h2 class="chart-card__title" id="chart-weeks-title">По неделям</h2>
                        <ul class="legend" aria-label="Обозначения">
                            <li><span class="legend__key legend__key--done"></span>Выполнено</li>
                            <li><span class="legend__key legend__key--rest"></span>Не выполнено</li>
                        </ul>
                    </div>

                    <div class="columns" style="--max: {{ $weeksMax }}">
                        <div class="columns__grid" aria-hidden="true">
                            <span data-value="{{ $weeksMax }}"></span>
                            <span data-value="{{ intdiv($weeksMax, 2) ?: '' }}"></span>
                            <span data-value="0"></span>
                        </div>
                        <ol class="columns__list">
                            @foreach ($weeks as $item)
                                @php $tip = "{$item['label']}: выполнено {$item['done']} из {$item['total']}"; @endphp
                                <li class="column {{ $item['isCurrent'] ? 'is-current' : '' }}" tabindex="0"
                                    data-tip="{{ $tip }}" aria-label="{{ $tip }}">
                                    <span class="column__stack" style="height: {{ $item['total'] / $weeksMax * 100 }}%">
                                        @if ($item['total'] > $item['done'])
                                            <span class="column__seg column__seg--rest" style="flex-grow: {{ $item['total'] - $item['done'] }}"></span>
                                        @endif
                                        @if ($item['done'] > 0)
                                            <span class="column__seg column__seg--done" style="flex-grow: {{ $item['done'] }}"></span>
                                        @endif
                                    </span>
                                    @if ($item['isCurrent'] && $item['total'])
                                        <span class="column__value">{{ $item['done'] }}/{{ $item['total'] }}</span>
                                    @endif
                                    <span class="column__label">{{ $item['label'] }}</span>
                                </li>
                            @endforeach
                        </ol>
                    </div>

                    <details class="chart-table">
                        <summary>Показать таблицей</summary>
                        <table>
                            <thead><tr><th scope="col">Неделя</th><th scope="col">Выполнено</th><th scope="col">Всего</th></tr></thead>
                            <tbody>
                                @foreach ($weeks as $item)
                                    <tr><td>{{ $item['label'] }}</td><td>{{ $item['done'] }}</td><td>{{ $item['total'] }}</td></tr>
                                @endforeach
                            </tbody>
                        </table>
                    </details>
                </section>

                <div class="stats-side">
                    <section class="chart-card" aria-labelledby="chart-tags-title">
                        <div class="chart-card__head">
                            <h2 class="chart-card__title" id="chart-tags-title">По тегам</h2>
                            <span class="chart-card__note">выполнено</span>
                        </div>
                        @php $tagMax = max(1, $byTag->max('done')); @endphp
                        <ul class="hbars">
                            @foreach ($byTag as $row)
                                @php $tip = "{$row['name']}: выполнено {$row['done']} из {$row['total']}"; @endphp
                                <li class="hbar {{ $row['color'] ? 'tag--'.$row['color'] : 'hbar--untagged' }}" tabindex="0"
                                    data-tip="{{ $tip }}" aria-label="{{ $tip }}">
                                    <span class="hbar__name">{{ $row['name'] }}</span>
                                    <span class="hbar__track">
                                        @if ($row['done'] > 0)
                                            <span class="hbar__bar" style="width: {{ $row['done'] / $tagMax * 100 }}%"></span>
                                        @endif
                                        <span class="hbar__value">{{ $row['done'] }}</span>
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    </section>

                    <section class="chart-card" aria-labelledby="chart-weekday-title">
                        <div class="chart-card__head">
                            <h2 class="chart-card__title" id="chart-weekday-title">По дням недели</h2>
                            @if ($bestWeekday['done'] > 0)
                                <span class="chart-card__note">чаще всего — {{ mb_strtolower(Lesson::WEEKDAYS[$bestWeekday['weekday']]) }}</span>
                            @endif
                        </div>
                        <ol class="columns columns--compact">
                            @foreach ($byWeekday as $row)
                                @php $tip = Lesson::WEEKDAYS[$row['weekday']].": выполнено {$row['done']}"; @endphp
                                <li class="column {{ $row['done'] > 0 && $row['weekday'] === $bestWeekday['weekday'] ? 'is-best' : '' }}" tabindex="0"
                                    data-tip="{{ $tip }}" aria-label="{{ $tip }}">
                                    <span class="column__stack" style="height: {{ $row['done'] / $weekdayMax * 100 }}%">
                                        @if ($row['done'] > 0)
                                            <span class="column__seg column__seg--done" style="flex-grow: 1"></span>
                                        @endif
                                    </span>
                                    <span class="column__label">{{ Lesson::WEEKDAYS_SHORT[$row['weekday']] }}</span>
                                </li>
                            @endforeach
                        </ol>
                    </section>
                </div>
            </div>
        @endif
    </main>

    <div class="chart-tip" role="tooltip" hidden data-chart-tip></div>
@endsection

@push('scripts')
    <script src="{{ asset('js/stats.js') }}?v={{ filemtime(public_path('js/stats.js')) }}" defer></script>
@endpush
