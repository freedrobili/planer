<?php

namespace App\Models;

use Database\Factories\TaskItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Пункт чек-листа внутри задачи.
 */
class TaskItem extends Model
{
    /** @use HasFactory<TaskItemFactory> */
    use HasFactory;

    protected $fillable = ['title', 'done', 'position'];

    protected $casts = [
        'done' => 'boolean',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }
}
