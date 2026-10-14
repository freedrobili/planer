<?php

namespace App\Http\Controllers;

use App\Models\Tag;
use App\Support\Appearance;
use App\Support\Owner;
use App\Support\PlannerDays;
use App\Support\PlannerImage;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    /**
     * Выгрузка дня или недели в HTML-страницу или SVG-картинку.
     * PNG браузер делает сам из SVG — так не нужны дополнительные зависимости на сервере.
     *
     * Ответ отдаётся потоком: так в файл не попадают скрипты, которые инструменты
     * разработки (Laravel Boost) встраивают в обычные HTML-ответы.
     */
    public function __invoke(Request $request, Appearance $appearance): StreamedResponse
    {
        $data = $request->validate([
            'period' => ['required', Rule::in(['day', 'week'])],
            'format' => ['required', Rule::in(['html', 'svg'])],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'tag' => ['nullable', 'integer'],
            'person' => ['nullable', 'string'],
            'with' => ['nullable', 'string'],
        ], [
            'period.*' => 'Выберите, что выгрузить: день или неделю.',
            'format.*' => 'Выберите формат: HTML, SVG или PNG.',
            'date.*' => 'Дата должна быть в формате ГГГГ-ММ-ДД.',
            'tag.*' => 'Фильтр по тегу указан неверно.',
        ]);

        $date = isset($data['date']) ? Carbon::createFromFormat('!Y-m-d', $data['date']) : Carbon::today();
        $isWeek = $data['period'] === 'week';
        $from = $isWeek ? $date->copy()->startOfWeek(Carbon::MONDAY) : $date;
        $to = $isWeek ? $from->copy()->addDays(6) : $date;
        $filter = isset($data['tag']) ? Tag::find($data['tag']) : null;

        $owner = Owner::fromKey($data['person'] ?? null) ?? Owner::me();
        $overlay = Owner::fromKey($data['with'] ?? null);
        if ($overlay?->is($owner)) {
            $overlay = null;
        }

        $days = PlannerDays::overlay(
            PlannerDays::load($owner)->between($from, $to, $filter),
            $overlay,
            $appearance->dayStart(),
            $appearance->dayEnd(),
        );
        $title = $isWeek ? 'Неделя '.$this->range($from, $to) : Str::ucfirst($date->translatedFormat('l, j F'));
        if (! $owner->isMe()) {
            $title = $owner->name().'. '.$title;
        }
        $summary = $this->summary($days->sum('total'), $days->sum('done'), $filter);
        if ($overlay !== null) {
            $summary .= '. Наложен график: '.($overlay->isMe() ? 'мой' : $overlay->name());
        }
        $filename = 'planner-'.($isWeek ? 'week-' : '').$from->toDateString().'.'.$data['format'];

        if ($data['format'] === 'svg') {
            $content = (new PlannerImage($appearance->palette()))
                ->render($title, $summary, $days, showDayNames: $isWeek, overlay: $overlay);
            $type = 'image/svg+xml';
        } else {
            $content = view('exports.page', [
                'appearance' => $appearance,
                'styles' => file_get_contents(public_path('css/app.css'))
                    .$appearance->css()
                    .file_get_contents(public_path('css/export.css')),
                'title' => $title,
                'summary' => $summary,
                'days' => $days,
                'isWeek' => $isWeek,
                'overlay' => $overlay,
            ])->render();
            $type = 'text/html; charset=UTF-8';
        }

        return response()->streamDownload(function () use ($content): void {
            echo $content;
        }, $filename, ['Content-Type' => $type]);
    }

    private function range(Carbon $from, Carbon $to): string
    {
        return $from->month === $to->month
            ? $from->format('j').'–'.$to->translatedFormat('j F')
            : $from->translatedFormat('j F').' – '.$to->translatedFormat('j F');
    }

    private function summary(int $total, int $done, ?Tag $filter): string
    {
        $prefix = $filter ? "Тег «{$filter->name}». " : '';

        return $prefix.($total > 0 ? "Готово {$done} из {$total}" : 'Задач нет');
    }
}
