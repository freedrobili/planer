<li class="lesson">
    <span class="lesson__time"><span>{{ $lesson->starts_at }}</span><span>{{ $lesson->ends_at }}</span></span>
    <span class="lesson__main">
        <span class="lesson__subject">{{ $lesson->subject }}</span>
        <span class="lesson__meta">
            @if ($lesson->kindLabel())<span>{{ $lesson->kindLabel() }}</span>@endif
            @if ($lesson->room)<span>ауд. {{ $lesson->room }}</span>@endif
            @if ($lesson->group_name)<span>{{ $lesson->group_name }}</span>@endif
        </span>
    </span>
</li>
