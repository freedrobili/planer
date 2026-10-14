@php
    $tagErrors = $errors->getBag('tags');
    $editingId = old('editing');
    $editing = $editingId ? $tags->firstWhere('id', (int) $editingId) : null;
@endphp
<dialog class="modal modal--wide" id="tags-dialog" aria-labelledby="tags-title"
        @if (session('openTags') || $tagErrors->any()) data-open-on-load @endif>
    <div class="modal__form">
        <div class="modal__head">
            <h2 id="tags-title" class="modal__title">Теги</h2>
            <button type="button" class="icon-btn" data-close aria-label="Закрыть">
                <svg viewBox="0 0 16 16" aria-hidden="true"><path d="M4 4l8 8M12 4l-8 8"/></svg>
            </button>
        </div>

        @if ($tags->isEmpty())
            <p class="muted">Тегов пока нет. Создайте первый — например, «Работа» или «Личное».</p>
        @else
            <ul class="tag-list">
                @foreach ($tags as $tag)
                    <li>
                        <span class="tag tag--{{ $tag->color }}">{{ $tag->name }}</span>
                        <span class="tag-list__count">
                            {{ $tag->tasks_count ? \App\Support\Plural::ru($tag->tasks_count, 'задача', 'задачи', 'задач') : 'нет задач' }}
                        </span>
                        <button type="button" class="icon-btn" data-edit-tag
                                data-id="{{ $tag->id }}"
                                data-action="{{ route('tags.update', $tag) }}"
                                data-name="{{ $tag->name }}"
                                data-color="{{ $tag->color }}"
                                aria-label="Изменить тег {{ $tag->name }}">
                            <svg viewBox="0 0 16 16" aria-hidden="true"><path d="M10.5 2.5l3 3L6 13H3v-3z"/></svg>
                        </button>
                        <form method="POST" action="{{ route('tags.destroy', $tag) }}"
                              data-confirm="Удалить тег «{{ $tag->name }}»? С задач он тоже исчезнет, сами задачи останутся.">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="icon-btn" aria-label="Удалить тег {{ $tag->name }}">
                                <svg viewBox="0 0 16 16" aria-hidden="true"><path d="M4 4l8 8M12 4l-8 8"/></svg>
                            </button>
                        </form>
                    </li>
                @endforeach
            </ul>
        @endif

        <form method="POST" class="tag-form" id="tag-form"
              action="{{ $editing ? route('tags.update', $editing) : route('tags.store') }}"
              data-store-action="{{ route('tags.store') }}">
            @csrf
            <input type="hidden" name="_method" value="PUT" @disabled(! $editing)>
            <input type="hidden" name="editing" value="{{ $editing?->id }}">

            <h3 class="tag-form__title" data-tag-form-title>{{ $editing ? 'Изменить тег' : 'Новый тег' }}</h3>

            <label class="field">
                <span>Название</span>
                <input type="text" name="name" maxlength="30" required autocomplete="off"
                       value="{{ old('name', '') }}" placeholder="Например, Учёба">
            </label>

            <fieldset class="swatches">
                <legend>Цвет</legend>
                @php $currentColor = old('color', 'blue'); @endphp
                @foreach ($colors as $key => $label)
                    <label class="swatch tag--{{ $key }}" title="{{ $label }}">
                        <input type="radio" name="color" value="{{ $key }}" @checked($currentColor === $key)>
                        <span class="visually-hidden">{{ $label }}</span>
                    </label>
                @endforeach
            </fieldset>

            @if ($tagErrors->any())
                <div class="alert" role="alert">
                    @foreach ($tagErrors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            <div class="modal__actions">
                <button type="button" class="btn btn--ghost" data-cancel-edit @if (! $editing) hidden @endif>Отменить изменение</button>
                <button type="submit" class="btn btn--primary" data-tag-submit>{{ $editing ? 'Сохранить' : 'Создать тег' }}</button>
            </div>
        </form>
    </div>
</dialog>
