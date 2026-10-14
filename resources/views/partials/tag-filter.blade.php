@if ($tags->isNotEmpty())
    <nav class="filter" aria-label="Фильтр по тегу">
        <a href="{{ $plannerUrl($selected, ['tag' => null]) }}"
           class="chip chip--all" @unless ($filter) aria-current="true" @endunless>Все задачи</a>
        @foreach ($tags as $tag)
            <a href="{{ $plannerUrl($selected, ['tag' => $tag->id]) }}"
               class="chip tag--{{ $tag->color }}" @if ($filter?->id === $tag->id) aria-current="true" @endif>{{ $tag->name }}</a>
        @endforeach
    </nav>
@endif
