{{-- Панель планера: режим «день / неделя», чей график, наложение и экспорт. --}}
<div class="planner-bar">
    <nav class="view-switch" aria-label="Режим планера">
        <a class="chip chip--plain" href="{{ $plannerUrl($selected, ['view' => 'day']) }}" @unless ($isWeek) aria-current="page" @endunless>День</a>
        <a class="chip chip--plain" href="{{ $plannerUrl($selected, ['view' => 'week']) }}" @if ($isWeek) aria-current="page" @endif>Неделя</a>
    </nav>

    @if ($owners->count() > 1)
        <nav class="people-switch" aria-label="Чей график">
            @foreach ($owners as $item)
                <a class="chip person-chip {{ $item->colorClass() }}" href="{{ $plannerUrl($selected, ['person' => $item->key()]) }}"
                   @if ($item->is($owner)) aria-current="page" @endif>{{ $item->name() }}</a>
            @endforeach
        </nav>
    @endif

    <div class="planner-bar__end">
        @if ($owners->count() > 1)
            <form class="overlay-pick" method="GET" action="{{ route('planner') }}">
                <input type="hidden" name="date" value="{{ $selected->toDateString() }}">
                @if ($filter)
                    <input type="hidden" name="tag" value="{{ $filter->id }}">
                @endif
                <label for="overlay-select">Наложить</label>
                <select id="overlay-select" name="with" data-autosubmit>
                    <option value="none">не накладывать</option>
                    @foreach ($owners as $item)
                        @unless ($item->is($owner))
                            <option value="{{ $item->key() }}" @selected($overlay?->is($item))>{{ $item->isMe() ? 'мой график' : $item->name() }}</option>
                        @endunless
                    @endforeach
                </select>
                <noscript><button type="submit" class="btn btn--ghost">Показать</button></noscript>
            </form>
        @endif
        <button type="button" class="icon-btn" data-open-export aria-label="Экспорт дня или недели" title="Экспорт">
            <svg viewBox="0 0 16 16" aria-hidden="true"><path d="M8 2.5v7.5M4.75 6.75 8 10l3.25-3.25M3 12.5h10"/></svg>
        </button>
    </div>
</div>
