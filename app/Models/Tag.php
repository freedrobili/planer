<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Tag extends Model
{
    use HasFactory;

    /** Доступные цвета: ключ => название. Сами оттенки заданы в CSS для светлой и тёмной темы. */
    public const COLORS = [
        'blue' => 'Синий',
        'green' => 'Зелёный',
        'red' => 'Красный',
        'orange' => 'Оранжевый',
        'violet' => 'Фиолетовый',
        'pink' => 'Розовый',
        'teal' => 'Бирюзовый',
        'gray' => 'Серый',
    ];

    /**
     * Оттенки цветов для картинок экспорта: [светлая тема, тёмная тема].
     * Совпадают с переменными --tag-* в public/css/app.css.
     *
     * @var array<string, array{0: string, 1: string}>
     */
    public const SWATCHES = [
        'blue' => ['#2e49d6', '#8ea0ff'],
        'green' => ['#2b8150', '#6fcf97'],
        'red' => ['#c4372f', '#ff8a80'],
        'orange' => ['#b25d09', '#f2a65a'],
        'violet' => ['#7444c4', '#b897ff'],
        'pink' => ['#b9356f', '#f58bbd'],
        'teal' => ['#0d7a7b', '#5ccfcb'],
        'gray' => ['#646a77', '#a3a9b6'],
    ];

    protected $fillable = ['name', 'color'];

    public function tasks(): BelongsToMany
    {
        return $this->belongsToMany(Task::class);
    }
}
