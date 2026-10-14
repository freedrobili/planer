@use('App\Models\Person')
@use('App\Support\Plural')
@extends('layouts.app')

@section('title', 'Люди')

@section('page-class', 'page--wide')

@section('content')
    <main class="sheet sheet--people">
        <div class="sheet__head">
            <h1 class="sheet__title">Люди<span>Графики близких: смотрите их день, переключайтесь между графиками и накладывайте один на другой.</span></h1>
            <button type="button" class="btn btn--primary" data-add-person>+ Добавить человека</button>
        </div>

        <ul class="people-grid">
            @foreach ($cards as $card)
                @php $owner = $card['owner']; @endphp
                <li class="person-card {{ $owner->colorClass() }}">
                    <div class="person-card__head">
                        <span class="person-card__dot" aria-hidden="true"></span>
                        <h2 class="person-card__name">{{ $owner->isMe() ? 'Мой график' : $owner->name() }}</h2>
                        @unless ($owner->isMe())
                            <span class="person-card__actions">
                                <button type="button" class="icon-btn" data-edit-person
                                        data-action="{{ route('people.update', $owner->person) }}"
                                        data-name="{{ $owner->name() }}"
                                        data-color="{{ $owner->person->color }}"
                                        aria-label="Изменить: {{ $owner->name() }}">
                                    <svg viewBox="0 0 16 16" aria-hidden="true"><path d="M10.5 2.5l3 3L6 13H3v-3z"/></svg>
                                </button>
                                <form method="POST" action="{{ route('people.destroy', $owner->person) }}"
                                      data-confirm="Удалить график «{{ $owner->name() }}» вместе со всеми задачами и парами?">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="icon-btn" aria-label="Удалить: {{ $owner->name() }}">
                                        <svg viewBox="0 0 16 16" aria-hidden="true"><path d="M4 4l8 8M12 4l-8 8"/></svg>
                                    </button>
                                </form>
                            </span>
                        @endunless
                    </div>

                    <dl class="person-card__day">
                        <div>
                            <dt>Сейчас</dt>
                            <dd>
                                @if ($card['now'])
                                    {{ $card['now']['title'] }} <span class="person-card__time">до {{ $card['now']['to'] }}</span>
                                @else
                                    <span class="person-card__free">свободное время</span>
                                @endif
                            </dd>
                        </div>
                        <div>
                            <dt>Дальше</dt>
                            <dd>
                                @if ($card['next'])
                                    <span class="person-card__time">{{ $card['next']['from'] }}</span> {{ $card['next']['title'] }}
                                @else
                                    <span class="person-card__free">сегодня больше ничего</span>
                                @endif
                            </dd>
                        </div>
                    </dl>

                    <p class="person-card__meta">
                        Сегодня:
                        @if ($card['tasks'] || $card['lessons'])
                            {{ $card['tasks'] ? Plural::ru($card['tasks'], 'задача', 'задачи', 'задач').' (готово '.$card['done'].')' : 'задач нет' }},
                            {{ $card['lessons'] ? Plural::ru($card['lessons'], 'пара', 'пары', 'пар') : 'пар нет' }}
                        @else
                            ничего не запланировано
                        @endif
                    </p>

                    <div class="person-card__links">
                        <a class="btn btn--ghost" href="{{ route('planner', ['person' => $owner->key(), 'with' => 'none']) }}">График</a>
                        <a class="btn btn--ghost" href="{{ route('schedule', ['person' => $owner->key()]) }}">Пары</a>
                        @unless ($owner->isMe())
                            <a class="btn btn--ghost" href="{{ route('planner', ['person' => 'me', 'with' => $owner->key(), 'view' => 'day']) }}">Наложить на мой</a>
                        @endunless
                    </div>
                </li>
            @endforeach

            @if ($cards->count() === 1)
                <li class="person-card person-card--empty">
                    <p>Добавьте человека, чей график важно видеть: его задачи и пары будут вестись отдельно, а в планере их можно наложить на свои и найти общее свободное время.</p>
                    <button type="button" class="btn btn--primary" data-add-person>+ Добавить человека</button>
                </li>
            @endif
        </ul>
    </main>
@endsection

@section('dialogs')
<dialog class="modal" id="person-dialog" aria-labelledby="person-title" @if ($errors->any()) data-open-on-load @endif>
    <form method="POST" class="modal__form" id="person-form"
          action="{{ old('person_id') ? route('people.update', old('person_id')) : route('people.store') }}"
          data-store-action="{{ route('people.store') }}">
        @csrf
        <input type="hidden" name="_method" value="PUT" @disabled(! old('person_id'))>
        <input type="hidden" name="person_id" value="{{ old('person_id') }}">

        <div class="modal__head">
            <h2 id="person-title" class="modal__title" data-person-title>{{ old('person_id') ? 'Изменить' : 'Новый человек' }}</h2>
            <button type="button" class="icon-btn" data-close aria-label="Закрыть">
                <svg viewBox="0 0 16 16" aria-hidden="true"><path d="M4 4l8 8M12 4l-8 8"/></svg>
            </button>
        </div>

        <label class="field">
            <span>Имя</span>
            <input type="text" name="name" maxlength="{{ Person::MAX_NAME }}" required autocomplete="off"
                   value="{{ old('name') }}" placeholder="Например, Маша или Папа">
        </label>

        <fieldset class="swatches">
            <legend>Цвет — так его задачи будут выделяться при наложении</legend>
            @foreach ($colors as $key => $label)
                <label class="swatch tag--{{ $key }}" title="{{ $label }}">
                    <input type="radio" name="color" value="{{ $key }}" @checked(old('color', 'violet') === $key)>
                    <span class="visually-hidden">{{ $label }}</span>
                </label>
            @endforeach
        </fieldset>

        @if ($errors->any())
            <div class="alert" role="alert">
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <div class="modal__actions">
            <button type="button" class="btn btn--ghost" data-close>Отмена</button>
            <button type="submit" class="btn btn--primary" data-person-submit>{{ old('person_id') ? 'Сохранить' : 'Добавить' }}</button>
        </div>
    </form>
</dialog>
@endsection

@push('scripts')
    <script src="{{ asset('js/people.js') }}?v={{ filemtime(public_path('js/people.js')) }}" defer></script>
@endpush
