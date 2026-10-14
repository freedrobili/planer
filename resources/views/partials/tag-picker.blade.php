{{-- Выбор тегов для задачи. $selected — массив id отмеченных тегов. --}}
<div class="tag-picker" role="group" aria-label="Теги">
    @foreach ($tags as $tag)
        <label class="chip tag--{{ $tag->color }}">
            <input type="checkbox" name="tags[]" value="{{ $tag->id }}" @checked(in_array($tag->id, $selected))>
            {{ $tag->name }}
        </label>
    @endforeach
    @if ($withAdd)
        <button type="button" class="chip chip--add" data-open-tags>+ Новый тег</button>
    @endif
</div>
