<?php

namespace App\Support;

use App\Models\Person;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Чей график: свой («Я», person = null) или другого человека.
 * Выбор приходит в адресе (?person=me|id) и запоминается в сессии,
 * так что планер и расписание пар показывают одного и того же человека.
 */
final class Owner
{
    public const ME = 'me';

    private const SESSION_KEY = 'planner.person';

    private const OVERLAY_SESSION_KEY = 'planner.with';

    public function __construct(public readonly ?Person $person = null) {}

    public static function me(): self
    {
        return new self;
    }

    /** «me» или id человека; null, если такого человека нет. */
    public static function fromKey(mixed $key): ?self
    {
        if ($key === self::ME) {
            return self::me();
        }

        if (is_numeric($key) && ($person = Person::find((int) $key)) !== null) {
            return new self($person);
        }

        return null;
    }

    /**
     * Все графики: сначала свой, потом люди в заданном порядке.
     *
     * @return Collection<int, self>
     */
    public static function all(): Collection
    {
        return collect([self::me()])
            ->concat(Person::ordered()->get()->map(fn (Person $person) => new self($person)));
    }

    /** Текущий график: из адреса (и тогда запоминается) или из сессии. По умолчанию — свой. */
    public static function current(Request $request): self
    {
        $requested = self::fromKey($request->query('person'));

        if ($requested !== null) {
            $request->session()->put(self::SESSION_KEY, $requested->key());

            return $requested;
        }

        return self::fromKey($request->session()->get(self::SESSION_KEY)) ?? self::me();
    }

    /**
     * Чей график наложен поверх текущего. ?with=none убирает наложение.
     * Наложить график сам на себя нельзя.
     */
    public static function overlay(Request $request, self $current): ?self
    {
        $requested = $request->query('with');

        if ($requested === 'none') {
            $request->session()->forget(self::OVERLAY_SESSION_KEY);

            return null;
        }

        $overlay = self::fromKey($requested);
        if ($overlay !== null) {
            $request->session()->put(self::OVERLAY_SESSION_KEY, $overlay->key());
        } else {
            $overlay = self::fromKey($request->session()->get(self::OVERLAY_SESSION_KEY));
        }

        return $overlay === null || $overlay->is($current) ? null : $overlay;
    }

    public function isMe(): bool
    {
        return $this->person === null;
    }

    public function is(self $other): bool
    {
        return $this->key() === $other->key();
    }

    public function key(): string
    {
        return $this->isMe() ? self::ME : (string) $this->person->id;
    }

    public function name(): string
    {
        return $this->isMe() ? 'Я' : $this->person->name;
    }

    /** Класс цвета: у людей — цвет из палитры тегов, у себя — акцентный цвет оформления. */
    public function colorClass(): string
    {
        return 'tag--'.($this->isMe() ? 'me' : $this->person->color);
    }

    public function personId(): ?int
    {
        return $this->person?->id;
    }

    /**
     * Ограничивает запрос задачами или парами этого графика.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function scope(Builder $query): Builder
    {
        return $this->isMe()
            ? $query->whereNull('person_id')
            : $query->where('person_id', $this->person->id);
    }
}
