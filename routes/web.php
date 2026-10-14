<?php

use App\Http\Controllers\ExportController;
use App\Http\Controllers\HabitController;
use App\Http\Controllers\LessonController;
use App\Http\Controllers\PeopleController;
use App\Http\Controllers\PlannerController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\StatsController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\TaskController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PlannerController::class, 'index'])->name('planner');

Route::post('/tasks', [TaskController::class, 'store'])->name('tasks.store');
Route::put('/tasks/{task}', [TaskController::class, 'update'])->name('tasks.update');
Route::patch('/tasks/{task}/toggle', [TaskController::class, 'toggle'])->name('tasks.toggle');
Route::delete('/tasks/{task}', [TaskController::class, 'destroy'])->name('tasks.destroy');
Route::patch('/tasks/{task}/move', [TaskController::class, 'move'])->name('tasks.move');
Route::post('/tasks/{task}/restore', [TaskController::class, 'restore'])->name('tasks.restore')->withTrashed();
Route::get('/tasks/parse', [TaskController::class, 'parse'])->name('tasks.parse');

Route::post('/tags', [TagController::class, 'store'])->name('tags.store');
Route::put('/tags/{tag}', [TagController::class, 'update'])->name('tags.update');
Route::delete('/tags/{tag}', [TagController::class, 'destroy'])->name('tags.destroy');

Route::get('/schedule', [LessonController::class, 'index'])->name('schedule');
Route::put('/schedule/semester', [LessonController::class, 'semester'])->name('schedule.semester');
Route::post('/lessons', [LessonController::class, 'store'])->name('lessons.store');
Route::put('/lessons/{lesson}', [LessonController::class, 'update'])->name('lessons.update');
Route::delete('/lessons/{lesson}', [LessonController::class, 'destroy'])->name('lessons.destroy');

Route::get('/settings', [SettingsController::class, 'index'])->name('settings');
Route::put('/settings', [SettingsController::class, 'update'])->name('settings.update');
Route::post('/settings/preview', [SettingsController::class, 'preview'])->name('settings.preview');
Route::patch('/settings/theme', [SettingsController::class, 'theme'])->name('settings.theme');
Route::delete('/settings', [SettingsController::class, 'reset'])->name('settings.reset');

Route::get('/export', ExportController::class)->name('export');

Route::get('/habits', [HabitController::class, 'index'])->name('habits');
Route::post('/habits', [HabitController::class, 'store'])->name('habits.store');
Route::put('/habits/{habit}', [HabitController::class, 'update'])->name('habits.update');
Route::delete('/habits/{habit}', [HabitController::class, 'destroy'])->name('habits.destroy');
Route::post('/habits/{habit}/days/{date}', [HabitController::class, 'check'])->name('habits.check');

Route::get('/people', [PeopleController::class, 'index'])->name('people');
Route::post('/people', [PeopleController::class, 'store'])->name('people.store');
Route::put('/people/{person}', [PeopleController::class, 'update'])->name('people.update');
Route::delete('/people/{person}', [PeopleController::class, 'destroy'])->name('people.destroy');

Route::get('/stats', StatsController::class)->name('stats');
