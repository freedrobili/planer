{{-- «Сегодня» есть всегда, чтобы шапка не меняла ширину; когда уже показан сегодняшний день — неактивна. --}}
@if ($isCurrent)
    <span class="chip chip--plain today-link" aria-disabled="true">Сегодня</span>
@else
    <a class="chip chip--plain today-link" href="{{ $href }}">Сегодня</a>
@endif
