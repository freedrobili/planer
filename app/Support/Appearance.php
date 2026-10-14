<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Настройки оформления: тема, цвета, шрифты и размеры.
 *
 * Хранятся в таблице settings с префиксом «appearance.». Любое значение
 * проходит через normalize(), поэтому в CSS попадают только известные
 * пресеты и проверенные HEX-цвета.
 */
class Appearance
{
    private const PREFIX = 'appearance.';

    public const DEFAULTS = [
        'theme' => 'system',
        'accent' => '#2e49d6',
        'background' => 'paper',
        'font' => 'expressive',
        'text_size' => 'm',
        'radius' => 'normal',
        'density' => 'comfortable',
        'width' => 'normal',
        'lesson_color' => '#4a5470',
        'day_start' => 6,
        'day_end' => 23,
    ];

    public const THEMES = [
        'system' => 'Как в системе',
        'light' => 'Светлая',
        'dark' => 'Тёмная',
    ];

    public const ACCENTS = [
        '#2e49d6' => 'Кобальт',
        '#0f7b5f' => 'Изумруд',
        '#c0392b' => 'Гранат',
        '#b25d09' => 'Янтарь',
        '#7444c4' => 'Аметист',
        '#c2387a' => 'Фуксия',
        '#1d2333' => 'Графит',
    ];

    public const LESSON_COLORS = [
        '#4a5470' => 'Сланец',
        '#2e6f8e' => 'Морской',
        '#6b5a3e' => 'Ореховый',
        '#5b4a8a' => 'Сливовый',
        '#3f6b4a' => 'Хвойный',
    ];

    /**
     * Палитры фона: у каждой светлый и тёмный вариант.
     *
     * @var array<string, array{label: string, light: array<string, string>, dark: array<string, string>}>
     */
    public const BACKGROUNDS = [
        'paper' => [
            'label' => 'Бумага',
            'light' => ['paper' => '#f2f3ee', 'sheet' => '#fbfbf8', 'ink' => '#1d2333', 'graphite' => '#727887', 'rule' => '#dcdfd7', 'rule-soft' => '#e8eae4'],
            'dark' => ['paper' => '#14171f', 'sheet' => '#1a1e28', 'ink' => '#e7e9e3', 'graphite' => '#8d93a1', 'rule' => '#2c3240', 'rule-soft' => '#232835'],
        ],
        'white' => [
            'label' => 'Чистый',
            'light' => ['paper' => '#f4f4f5', 'sheet' => '#ffffff', 'ink' => '#18181b', 'graphite' => '#71717a', 'rule' => '#e4e4e7', 'rule-soft' => '#efeff1'],
            'dark' => ['paper' => '#0c0c0e', 'sheet' => '#16161a', 'ink' => '#ededef', 'graphite' => '#8b8b93', 'rule' => '#2a2a30', 'rule-soft' => '#1f1f24'],
        ],
        'warm' => [
            'label' => 'Тёплый',
            'light' => ['paper' => '#f3efe8', 'sheet' => '#fbf9f5', 'ink' => '#2a241d', 'graphite' => '#7d7468', 'rule' => '#e2dbd0', 'rule-soft' => '#ece6dc'],
            'dark' => ['paper' => '#1a1714', 'sheet' => '#221e1a', 'ink' => '#ece6dc', 'graphite' => '#9a9083', 'rule' => '#362f28', 'rule-soft' => '#2a2520'],
        ],
        'sage' => [
            'label' => 'Шалфей',
            'light' => ['paper' => '#ecf0ea', 'sheet' => '#f8faf6', 'ink' => '#1f2a24', 'graphite' => '#6d786f', 'rule' => '#d4dcd3', 'rule-soft' => '#e3e9e1'],
            'dark' => ['paper' => '#121714', 'sheet' => '#19201b', 'ink' => '#e3e9e3', 'graphite' => '#8a968c', 'rule' => '#2a332c', 'rule-soft' => '#202722'],
        ],
        'sky' => [
            'label' => 'Небо',
            'light' => ['paper' => '#ecf0f7', 'sheet' => '#f8fafd', 'ink' => '#1a2233', 'graphite' => '#6b7488', 'rule' => '#d7dde8', 'rule-soft' => '#e5e9f1'],
            'dark' => ['paper' => '#0e1320', 'sheet' => '#151b2b', 'ink' => '#e5e9f2', 'graphite' => '#8b94a8', 'rule' => '#263047', 'rule-soft' => '#1b2234'],
        ],
    ];

    /**
     * Пары шрифтов (все с кириллицей). families — параметры для Google Fonts.
     *
     * @var array<string, array{label: string, display: string, ui: string, families: list<string>}>
     */
    public const FONTS = [
        'expressive' => [
            'label' => 'Яркий',
            'display' => '"Unbounded", "Golos Text", system-ui, sans-serif',
            'ui' => '"Golos Text", system-ui, sans-serif',
            'families' => ['Golos+Text:wght@400;500;600', 'Unbounded:wght@400;600'],
        ],
        'strict' => [
            'label' => 'Строгий',
            'display' => '"Golos Text", system-ui, sans-serif',
            'ui' => '"Golos Text", system-ui, sans-serif',
            'families' => ['Golos+Text:wght@400;500;600;700'],
        ],
        'classic' => [
            'label' => 'Книжный',
            'display' => '"Lora", Georgia, serif',
            'ui' => '"Golos Text", system-ui, sans-serif',
            'families' => ['Golos+Text:wght@400;500;600', 'Lora:wght@500;600'],
        ],
        'rounded' => [
            'label' => 'Мягкий',
            'display' => '"Nunito", system-ui, sans-serif',
            'ui' => '"Nunito", system-ui, sans-serif',
            'families' => ['Nunito:wght@400;500;600;700;800'],
        ],
        'mono' => [
            'label' => 'Моно',
            'display' => '"JetBrains Mono", ui-monospace, monospace',
            'ui' => '"Golos Text", system-ui, sans-serif',
            'families' => ['Golos+Text:wght@400;500;600', 'JetBrains+Mono:wght@500;600'],
        ],
        'system' => [
            'label' => 'Системный',
            'display' => 'system-ui, -apple-system, "Segoe UI", sans-serif',
            'ui' => 'system-ui, -apple-system, "Segoe UI", sans-serif',
            'families' => [],
        ],
    ];

    /** @var array<string, array{label: string, value: string}> */
    public const TEXT_SIZES = [
        's' => ['label' => 'Мелкий', 'value' => '93.75%'],
        'm' => ['label' => 'Обычный', 'value' => '100%'],
        'l' => ['label' => 'Крупный', 'value' => '110%'],
    ];

    /** @var array<string, array{label: string, s: string, m: string, l: string}> */
    public const RADII = [
        'sharp' => ['label' => 'Острые', 's' => '2px', 'm' => '3px', 'l' => '4px'],
        'normal' => ['label' => 'Обычные', 's' => '6px', 'm' => '10px', 'l' => '14px'],
        'soft' => ['label' => 'Мягкие', 's' => '10px', 'm' => '16px', 'l' => '24px'],
    ];

    /** @var array<string, array{label: string, row: string, item: string}> */
    public const DENSITIES = [
        'compact' => ['label' => 'Компактно', 'row' => '2.5rem', 'item' => '2rem'],
        'comfortable' => ['label' => 'Обычно', 'row' => '3.25rem', 'item' => '2.5rem'],
        'spacious' => ['label' => 'Просторно', 'row' => '4rem', 'item' => '2.75rem'],
    ];

    /** @var array<string, array{label: string, value: string}> */
    public const WIDTHS = [
        'narrow' => ['label' => 'Узкая', 'value' => '44rem'],
        'normal' => ['label' => 'Обычная', 'value' => '52rem'],
        'wide' => ['label' => 'Широкая', 'value' => '64rem'],
    ];

    /**
     * @param  array<string, string|int>  $values  уже нормализованные значения
     */
    public function __construct(private readonly array $values) {}

    public static function fromSettings(): self
    {
        $stored = Setting::where('key', 'like', self::PREFIX.'%')
            ->pluck('value', 'key')
            ->mapWithKeys(fn (?string $value, string $key) => [substr($key, strlen(self::PREFIX)) => $value])
            ->all();

        return new self(self::normalize($stored));
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public static function fromInput(array $input): self
    {
        return new self(self::normalize($input));
    }

    /**
     * Правила проверки формы оформления.
     *
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        $hex = ['required', 'regex:/^#[0-9a-fA-F]{6}$/'];

        return [
            'theme' => ['required', Rule::in(array_keys(self::THEMES))],
            'accent' => $hex,
            'background' => ['required', Rule::in(array_keys(self::BACKGROUNDS))],
            'font' => ['required', Rule::in(array_keys(self::FONTS))],
            'text_size' => ['required', Rule::in(array_keys(self::TEXT_SIZES))],
            'radius' => ['required', Rule::in(array_keys(self::RADII))],
            'density' => ['required', Rule::in(array_keys(self::DENSITIES))],
            'width' => ['required', Rule::in(array_keys(self::WIDTHS))],
            'lesson_color' => $hex,
            'day_start' => ['required', 'integer', 'between:0,23'],
            'day_end' => ['required', 'integer', 'between:0,23', 'gte:day_start'],
        ];
    }

    public static function reset(): void
    {
        Setting::where('key', 'like', self::PREFIX.'%')->delete();
    }

    public function save(): void
    {
        DB::transaction(function (): void {
            foreach ($this->values as $key => $value) {
                Setting::put(self::PREFIX.$key, (string) $value);
            }
        });
    }

    public function get(string $key): string|int
    {
        return $this->values[$key];
    }

    /**
     * @return array<string, string|int>
     */
    public function all(): array
    {
        return $this->values;
    }

    /** null — следовать системной теме. */
    public function forcedTheme(): ?string
    {
        return $this->values['theme'] === 'system' ? null : $this->values['theme'];
    }

    public function dayStart(): int
    {
        return $this->values['day_start'];
    }

    public function dayEnd(): int
    {
        return $this->values['day_end'];
    }

    public function fontsUrl(): ?string
    {
        $families = self::FONTS[$this->values['font']]['families'];

        if ($families === []) {
            return null;
        }

        return 'https://fonts.googleapis.com/css2?family='.implode('&family=', $families).'&display=swap';
    }

    /** CSS-переменные, переопределяющие значения по умолчанию из app.css. */
    public function css(): string
    {
        $font = self::FONTS[$this->values['font']];
        $radius = self::RADII[$this->values['radius']];
        $density = self::DENSITIES[$this->values['density']];
        $background = self::BACKGROUNDS[$this->values['background']];

        $root = [
            'accent-base' => $this->values['accent'],
            'accent-on' => self::readableTextOn($this->values['accent']),
            'accent-text-base' => self::readableOn($this->values['accent'], [$background['light']['sheet'], $background['light']['paper']]),
            'lesson-base' => $this->values['lesson_color'],
            'font-ui' => $font['ui'],
            'font-display' => $font['display'],
            'radius-s' => $radius['s'],
            'radius-m' => $radius['m'],
            'radius-l' => $radius['l'],
            'page-w' => self::WIDTHS[$this->values['width']]['value'],
            'row-h' => $density['row'],
            'item-h' => $density['item'],
        ] + $background['light'];

        $light = self::declarations($root).'font-size:'.self::TEXT_SIZES[$this->values['text_size']]['value'].';';
        $dark = self::declarations($background['dark']);

        return ':root{'.$light.'}'
            .':root[data-theme="dark"]{'.$dark.'}'
            .'@media (prefers-color-scheme: dark){:root:not([data-theme="light"]){'.$dark.'}}';
    }

    /**
     * Готовые цвета для картинок экспорта. Картинка не умеет следовать системной теме,
     * поэтому тёмная палитра берётся, только если тёмная тема выбрана явно.
     *
     * @return array{dark: bool, paper: string, sheet: string, ink: string, graphite: string, rule: string, accent: string, accentText: string, accentOn: string, lesson: string}
     */
    public function palette(): array
    {
        $dark = $this->values['theme'] === 'dark';
        $background = self::BACKGROUNDS[$this->values['background']][$dark ? 'dark' : 'light'];

        return [
            'dark' => $dark,
            'paper' => $background['paper'],
            'sheet' => $background['sheet'],
            'ink' => $background['ink'],
            'graphite' => $background['graphite'],
            'rule' => $background['rule'],
            'accent' => $dark ? self::mixWithWhite($this->values['accent'], 0.42) : $this->values['accent'],
            'accentText' => $dark
                ? self::mixWithWhite($this->values['accent'], 0.42)
                : self::readableOn($this->values['accent'], [$background['sheet']]),
            'accentOn' => $dark ? $background['paper'] : self::readableTextOn($this->values['accent']),
            'lesson' => $dark ? self::mixWithWhite($this->values['lesson_color'], 0.55) : $this->values['lesson_color'],
        ];
    }

    /**
     * Осветляет цвет, смешивая его с белым в доле $white (0…1) — как color-mix в CSS тёмной темы.
     */
    private static function mixWithWhite(string $hex, float $white): string
    {
        $channels = array_map(
            fn (string $pair): int => (int) round(hexdec($pair) * (1 - $white) + 255 * $white),
            str_split(ltrim($hex, '#'), 2),
        );

        return vsprintf('#%02x%02x%02x', $channels);
    }

    /**
     * Акцентный цвет для текста на светлом фоне: тот же оттенок, затемнённый настолько,
     * чтобы контраст со всеми фонами был не ниже 4.5 : 1 (норма WCAG для обычного текста).
     *
     * @param  list<string>  $backgrounds
     */
    public static function readableOn(string $hex, array $backgrounds, float $minContrast = 4.5): string
    {
        $channels = array_map('hexdec', str_split(ltrim($hex, '#'), 2));

        for ($darken = 0.0; $darken < 1.0; $darken += 0.04) {
            $color = vsprintf('#%02x%02x%02x', array_map(fn (int $c) => (int) round($c * (1 - $darken)), $channels));
            $luminance = self::luminance($color);

            $passes = collect($backgrounds)->every(
                fn (string $background) => (self::luminance($background) + 0.05) / ($luminance + 0.05) >= $minContrast,
            );
            if ($passes) {
                return $color;
            }
        }

        return '#000000';
    }

    /**
     * Цвет текста на кнопке с фоном $hex: белый или почти чёрный — что контрастнее.
     */
    public static function readableTextOn(string $hex): string
    {
        $luminance = self::luminance($hex);
        $againstWhite = 1.05 / ($luminance + 0.05);
        $againstDark = ($luminance + 0.05) / (self::luminance('#111318') + 0.05);

        return $againstWhite >= $againstDark ? '#ffffff' : '#111318';
    }

    private static function luminance(string $hex): float
    {
        $channels = array_map(
            function (string $pair): float {
                $c = hexdec($pair) / 255;

                return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
            },
            str_split(ltrim($hex, '#'), 2),
        );

        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    }

    /**
     * @param  array<string, string>  $variables
     */
    private static function declarations(array $variables): string
    {
        return collect($variables)->map(fn (string $value, string $name) => "--{$name}:{$value};")->implode('');
    }

    /**
     * Оставляет только допустимые значения, остальное заменяет значениями по умолчанию.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, string|int>
     */
    private static function normalize(array $input): array
    {
        $choices = [
            'theme' => self::THEMES,
            'background' => self::BACKGROUNDS,
            'font' => self::FONTS,
            'text_size' => self::TEXT_SIZES,
            'radius' => self::RADII,
            'density' => self::DENSITIES,
            'width' => self::WIDTHS,
        ];

        $values = self::DEFAULTS;

        foreach ($choices as $key => $options) {
            if (isset($input[$key]) && is_string($input[$key]) && array_key_exists($input[$key], $options)) {
                $values[$key] = $input[$key];
            }
        }

        foreach (['accent', 'lesson_color'] as $key) {
            if (isset($input[$key]) && is_string($input[$key]) && preg_match('/^#[0-9a-fA-F]{6}$/', $input[$key])) {
                $values[$key] = strtolower($input[$key]);
            }
        }

        foreach (['day_start', 'day_end'] as $key) {
            if (isset($input[$key]) && is_numeric($input[$key]) && (int) $input[$key] >= 0 && (int) $input[$key] <= 23) {
                $values[$key] = (int) $input[$key];
            }
        }

        if ($values['day_end'] < $values['day_start']) {
            $values['day_end'] = $values['day_start'];
        }

        return $values;
    }
}
