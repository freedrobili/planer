<?php

namespace App\Models;

use App\Support\Owner;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Task extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = ['title', 'description', 'date', 'time', 'ends_at', 'done', 'person_id'];

    protected $casts = [
        'date' => 'date',
        'done' => 'boolean',
    ];

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class)->orderBy('name');
    }

    public function items(): HasMany
    {
        return $this->hasMany(TaskItem::class)->orderBy('position')->orderBy('id');
    }

    /**
     * Задачи с $from по $to включительно (даты в формате Y-m-d).
     *
     * Дата хранится как «Y-m-d 00:00:00», поэтому верхнюю границу
     * дополняем до конца дня, иначе последний день диапазона выпадает.
     */
    public function scopeBetween(Builder $query, string $from, string $to): Builder
    {
        return $query->whereBetween('date', [$from, $to.' 23:59:59']);
    }

    /** Задачи одного графика: свои или конкретного человека. */
    public function scopeOwnedBy(Builder $query, Owner $owner): Builder
    {
        return $owner->scope($query);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderByRaw('time is null desc')->orderBy('time')->orderBy('id');
    }

    /** Час, к которому относится задача (null — без времени). */
    public function hour(): ?int
    {
        return $this->time === null ? null : (int) substr($this->time, 0, 2);
    }

    /** «14:00» или «14:00–16:00», если у задачи есть время окончания. */
    public function timeLabel(): ?string
    {
        if ($this->time === null) {
            return null;
        }

        return $this->ends_at ? $this->time.'–'.$this->ends_at : $this->time;
    }

    /**
     * Часы, которые занимает задача с длительностью (для полосы в расписании дня).
     *
     * @return list<int>
     */
    public function coveredHours(): array
    {
        if ($this->time === null || $this->ends_at === null) {
            return [];
        }

        $start = (int) substr($this->time, 0, 2);
        [$endHour, $endMinute] = array_map('intval', explode(':', $this->ends_at));
        $last = $endMinute > 0 ? $endHour : $endHour - 1;

        return range($start, max($start, $last));
    }

    /**
     * Пересекается ли задача по времени с парой. Задача без длительности —
     * это момент времени: он пересекается, если попадает внутрь пары.
     */
    public function overlaps(Lesson $lesson): bool
    {
        if ($this->time === null) {
            return false;
        }

        $end = $this->ends_at ?? $this->time;

        return $this->ends_at === null
            ? $this->time >= $lesson->starts_at && $this->time < $lesson->ends_at
            : $this->time < $lesson->ends_at && $lesson->starts_at < $end;
    }
}
