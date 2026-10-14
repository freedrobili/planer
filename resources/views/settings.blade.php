@use('App\Support\Appearance')
@php
    // Введённые ранее значения тоже нормализуем: они попадают в атрибуты style.
    $values = Appearance::fromInput(array_merge($current, session()->getOldInput()))->all();
    $value = fn (string $key) => $values[$key];
    $isCustomAccent = ! array_key_exists($value('accent'), Appearance::ACCENTS);
    $isCustomLesson = ! array_key_exists($value('lesson_color'), Appearance::LESSON_COLORS);
    $allFonts = collect(Appearance::FONTS)->pluck('families')->flatten()->unique()->implode('&family=');
@endphp
@extends('layouts.app')

@section('title', 'Оформление')

@section('page-class', 'page--wide')

@push('head')
    {{-- Все шрифты сразу, чтобы образцы на этой странице показывались своим начертанием. --}}
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family={{ $allFonts }}&display=swap">
@endpush

@section('content')
    <main class="sheet">
        <div class="sheet__head">
            <h1 class="sheet__title">Оформление<span>Изменения видны сразу. Чтобы они остались, нажмите «Сохранить».</span></h1>
        </div>

        <form method="POST" action="{{ route('settings.update') }}" class="settings" id="settings-form"
              data-preview-url="{{ route('settings.preview') }}">
            @csrf
            @method('PUT')

            @if ($errors->any())
                <div class="alert" role="alert">
                    @foreach ($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            <div class="settings__cols">
                <div class="settings__col">
                    <fieldset class="setting setting--row">
                        <legend class="setting__title">Тема</legend>
                        <div class="segmented">
                            @foreach (Appearance::THEMES as $key => $label)
                                <label class="chip chip--plain">
                                    <input type="radio" name="theme" value="{{ $key }}" @checked($value('theme') === $key)>
                                    {{ $label }}
                                </label>
                            @endforeach
                        </div>
                    </fieldset>

                    <fieldset class="setting setting--row">
                        <legend class="setting__title">Акцентный цвет</legend>
                        <div class="swatches">
                            @foreach (Appearance::ACCENTS as $hex => $label)
                                <label class="swatch" style="--tag: {{ $hex }}" title="{{ $label }}">
                                    <input type="radio" name="accent" value="{{ $hex }}" @checked($value('accent') === $hex)>
                                    <span class="visually-hidden">{{ $label }}</span>
                                </label>
                            @endforeach
                            <label class="swatch swatch--custom" style="--tag: {{ $value('accent') }}" title="Свой цвет">
                                <input type="radio" name="accent" value="{{ $value('accent') }}" @checked($isCustomAccent) data-custom-radio>
                                <input type="color" value="{{ $value('accent') }}" data-custom-color aria-label="Свой акцентный цвет">
                            </label>
                        </div>
                    </fieldset>

                    <fieldset class="setting setting--row">
                        <legend class="setting__title">Цвет пар</legend>
                        <div class="swatches">
                            @foreach (Appearance::LESSON_COLORS as $hex => $label)
                                <label class="swatch" style="--tag: {{ $hex }}" title="{{ $label }}">
                                    <input type="radio" name="lesson_color" value="{{ $hex }}" @checked($value('lesson_color') === $hex)>
                                    <span class="visually-hidden">{{ $label }}</span>
                                </label>
                            @endforeach
                            <label class="swatch swatch--custom" style="--tag: {{ $value('lesson_color') }}" title="Свой цвет">
                                <input type="radio" name="lesson_color" value="{{ $value('lesson_color') }}" @checked($isCustomLesson) data-custom-radio>
                                <input type="color" value="{{ $value('lesson_color') }}" data-custom-color aria-label="Свой цвет пар">
                            </label>
                        </div>
                    </fieldset>

                    <div class="settings__grid">
                        @foreach ([
                            'text_size' => ['Размер текста', Appearance::TEXT_SIZES],
                            'radius' => ['Скругление углов', Appearance::RADII],
                            'density' => ['Плотность расписания', Appearance::DENSITIES],
                            'width' => ['Ширина страницы', Appearance::WIDTHS],
                        ] as $name => [$title, $options])
                            <fieldset class="setting setting--row">
                                <legend class="setting__title">{{ $title }}</legend>
                                <div class="segmented">
                                    @foreach ($options as $key => $option)
                                        <label class="chip chip--plain">
                                            <input type="radio" name="{{ $name }}" value="{{ $key }}" @checked($value($name) === $key)>
                                            {{ $option['label'] }}
                                        </label>
                                    @endforeach
                                </div>
                            </fieldset>
                        @endforeach
                    </div>

                    <fieldset class="setting setting--row">
                        <legend class="setting__title">Часы в расписании дня</legend>
                        <div class="field-row field-row--inline">
                            <label class="field">
                                <span>С</span>
                                <select name="day_start">
                                    @foreach (range(0, 23) as $hour)
                                        <option value="{{ $hour }}" @selected((int) $value('day_start') === $hour)>{{ sprintf('%02d:00', $hour) }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="field">
                                <span>До</span>
                                <select name="day_end">
                                    @foreach (range(0, 23) as $hour)
                                        <option value="{{ $hour }}" @selected((int) $value('day_end') === $hour)>{{ sprintf('%02d:00', $hour) }}</option>
                                    @endforeach
                                </select>
                            </label>
                        </div>
                        <p class="setting__hint">Задачи и пары вне этих часов всё равно будут видны.</p>
                    </fieldset>
                </div>
                <div class="settings__col">
                    <fieldset class="setting">
                        <legend class="setting__title">Фон</legend>
                        <div class="bg-options">
                            @foreach (Appearance::BACKGROUNDS as $key => $background)
                                <label class="bg-option">
                                    <input type="radio" name="background" value="{{ $key }}" @checked($value('background') === $key)>
                                    <span class="bg-option__sample" aria-hidden="true">
                                        <span style="background: {{ $background['light']['paper'] }}; color: {{ $background['light']['ink'] }}">Аа</span>
                                        <span style="background: {{ $background['dark']['paper'] }}; color: {{ $background['dark']['ink'] }}">Аа</span>
                                    </span>
                                    {{ $background['label'] }}
                                </label>
                            @endforeach
                        </div>
                    </fieldset>

                    <fieldset class="setting">
                        <legend class="setting__title">Шрифт</legend>
                        <div class="font-options">
                            @foreach (Appearance::FONTS as $key => $font)
                                <label class="font-option">
                                    <input type="radio" name="font" value="{{ $key }}" @checked($value('font') === $key)>
                                    <span class="font-option__sample" style="font-family: {{ $font['display'] }}">Аа</span>
                                    <span class="font-option__label" style="font-family: {{ $font['ui'] }}">{{ $font['label'] }}</span>
                                </label>
                            @endforeach
                        </div>
                    </fieldset>

                    <section class="setting" aria-labelledby="sample-title">
                        <h2 class="setting__title" id="sample-title">Как это выглядит</h2>
                        <div class="sample" aria-hidden="true">
                            <div class="hour">
                                <span class="hour__label">09:00</span>
                                <div class="hour__body">
                                    <ul class="tasks">
                                        <li class="task">
                                            <span class="check"><svg viewBox="0 0 16 16"><path d="M3.5 8.5l3 3 6-7"/></svg></span>
                                            <time class="task__time">09:30</time>
                                            <span class="task__main">
                                                <span class="task__title">Позвонить в деканат</span>
                                                <span class="tag tag--blue">Учёба</span>
                                            </span>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                            <div class="hour is-busy">
                                <span class="hour__label">10:00</span>
                                <div class="hour__body">
                                    <ul class="tasks">
                                        <li class="lesson">
                                            <span class="lesson__time"><span>10:10</span><span>11:40</span></span>
                                            <span class="lesson__main">
                                                <span class="lesson__subject">Математический анализ</span>
                                                <span class="lesson__meta"><span>Лекция</span><span>ауд. 305</span></span>
                                            </span>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>
            </div>

            <div class="settings__actions">
                <button type="submit" form="reset-form" class="link-btn">Сбросить к стандартному</button>
                <p class="settings__dirty" data-dirty hidden>Есть несохранённые изменения</p>
                <a href="{{ route('settings') }}" class="btn btn--ghost" data-discard hidden>Отменить изменения</a>
                <button type="submit" class="btn btn--primary">Сохранить</button>
            </div>
        </form>

        <form method="POST" action="{{ route('settings.reset') }}" id="reset-form"
              data-confirm="Вернуть стандартное оформление? Все настройки на этой странице сбросятся.">
            @csrf
            @method('DELETE')
        </form>
    </main>
@endsection

@push('scripts')
    <script src="{{ asset('js/settings.js') }}?v={{ filemtime(public_path('js/settings.js')) }}" defer></script>
@endpush
