{{-- Переключение между графиками и наложение другого графика. Показывается, когда добавлен хотя бы один человек. --}}
@if ($owners->count() > 1)
    <div class="people-bar">
        <nav class="people-switch" aria-label="Чей график">
            @foreach ($owners as $item)
                <a class="chip person-chip {{ $item->colorClass() }}" href="{{ $plannerUrl($selected, ['person' => $item->key()]) }}"
                   @if ($item->is($owner)) aria-current="page" @endif>{{ $item->name() }}</a>
            @endforeach
        </nav>
        <form class="overlay-pick" method="GET" action="{{ route('planner') }}">
            <input type="hidden" name="date" value="{{ $selected->toDateString() }}">
            @if ($filter)
                <input type="hidden" name="tag" value="{{ $filter->id }}">
            @endif
            <label for="overlay-select">Наложить</label>
            <select id="overlay-select" name="with" data-autosubmit>
                <option value="none">ничей график</option>
                @foreach ($owners as $item)
                    @unless ($item->is($owner))
                        <option value="{{ $item->key() }}" @selected($overlay?->is($item))>{{ $item->isMe() ? 'мой график' : $item->name() }}</option>
                    @endunless
                @endforeach
            </select>
            <noscript><button type="submit" class="btn btn--ghost">Показать</button></noscript>
        </form>
    </div>
@endif
