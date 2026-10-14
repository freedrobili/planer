{{-- Сохраняет активный фильтр по тегу после отправки формы. --}}
@if ($filter)
    <input type="hidden" name="filter" value="{{ $filter->id }}">
@endif
