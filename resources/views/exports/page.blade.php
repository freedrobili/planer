<!DOCTYPE html>
<html lang="ru"@if ($appearance->forcedTheme()) data-theme="{{ $appearance->forcedTheme() }}"@endif>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} — Планер</title>
    @if ($appearance->fontsUrl())
        <link rel="stylesheet" href="{{ $appearance->fontsUrl() }}">
    @endif
    <style>{!! $styles !!}</style>
</head>
<body>
<main class="export {{ $isWeek ? 'export--week' : 'export--day' }}">
    <header class="export__head">
        <h1 class="export__title">{{ $title }}</h1>
        <p class="export__summary">{{ $summary }}</p>
    </header>

    <div class="export__days">
        @foreach ($days as $day)
            <section class="export-day">
                @if ($isWeek)
                    <h2 class="export-day__title">
                        {{ \Illuminate\Support\Str::ucfirst($day['date']->translatedFormat('l, j F')) }}
                        @if ($day['total'])
                            <span>{{ $day['done'] }}/{{ $day['total'] }}</span>
                        @endif
                    </h2>
                @endif
                <span class="day__meter" aria-hidden="true">
                    <span style="width: {{ $day['total'] ? round($day['done'] / $day['total'] * 100) : 0 }}%"></span>
                </span>

                @if ($overlay && $day['freeTogether'] !== null)
                    <p class="free-together {{ $overlay->colorClass() }}">
                        @if ($day['freeTogether'])
                            Оба свободны:
                            @foreach ($day['freeTogether'] as $window)
                                <span class="free-together__slot">{{ $window['from'] }}–{{ $window['to'] }}</span>
                            @endforeach
                        @else
                            Общего свободного времени нет
                        @endif
                    </p>
                @endif

                @if ($day['untimed']->isEmpty() && $day['ghostUntimed']->isEmpty() && $day['timeline']->isEmpty())
                    <p class="export-day__empty">Свободно</p>
                @endif

                @if ($day['untimed']->isNotEmpty() || $day['ghostUntimed']->isNotEmpty())
                    <ul class="tasks">
                        @foreach ($day['untimed'] as $task)
                            @include('exports.task', ['task' => $task])
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
                                @include('exports.task', ['task' => $entry['item']])
                            @endif
                        @endforeach
                    </ul>
                @endif
            </section>
        @endforeach
    </div>

    <footer class="export__foot">Выгружено из Планера {{ now()->translatedFormat('j F Y, H:i') }}</footer>
</main>
</body>
</html>
