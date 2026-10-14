<?php

namespace App\Http\Controllers;

use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TagController extends Controller
{
    public function store(Request $request)
    {
        $tag = Tag::create($this->validated($request));

        return $this->back("Тег «{$tag->name}» создан");
    }

    public function update(Request $request, Tag $tag)
    {
        $tag->update($this->validated($request, $tag));

        return $this->back("Тег «{$tag->name}» сохранён");
    }

    public function destroy(Tag $tag)
    {
        $tag->tasks()->detach();
        $tag->delete();

        return $this->back("Тег «{$tag->name}» удалён");
    }

    private function validated(Request $request, ?Tag $tag = null): array
    {
        $request->merge(['name' => trim((string) $request->input('name'))]);

        return $request->validateWithBag('tags', [
            'name' => ['required', 'string', 'max:30', Rule::unique('tags', 'name')->ignore($tag)],
            'color' => ['required', Rule::in(array_keys(Tag::COLORS))],
        ], [
            'name.required' => 'Придумайте название тега.',
            'name.max' => 'Название тега должно быть не длиннее 30 символов.',
            'name.unique' => 'Тег с таким названием уже есть.',
            'color.required' => 'Выберите цвет тега.',
            'color.in' => 'Выберите цвет из предложенных.',
        ]);
    }

    private function back(string $status)
    {
        // Возвращаемся на ту же страницу и снова открываем окно с тегами.
        return redirect()->back()->with('status', $status)->with('openTags', true);
    }
}
