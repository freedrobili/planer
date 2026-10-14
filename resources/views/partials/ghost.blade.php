{{-- Задача или пара из наложенного графика: только для просмотра. --}}
@php
    $isLesson = $entry['type'] === 'lesson';
    $item = $entry['item'];
    $time = $isLesson ? $item->starts_at.'–'.$item->ends_at : $item->timeLabel();
@endphp
<li class="ghost {{ $overlay->colorClass() }} {{ ! $isLesson && $item->done ? 'is-done' : '' }}">
    @if ($time)
        <span class="ghost__time">{{ $time }}</span>
    @endif
    <span class="ghost__title">{{ $isLesson ? $item->subject : $item->title }}</span>
    <span class="ghost__who">{{ $overlay->name() }}{{ $isLesson ? ' · пара' : '' }}</span>
</li>
