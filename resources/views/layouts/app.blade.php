<!DOCTYPE html>
<html lang="ru"@if ($appearance->forcedTheme()) data-theme="{{ $appearance->forcedTheme() }}"@endif>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title') — Планер</title>
    <link rel="icon" href="{{ asset('icons/icon.svg') }}" type="image/svg+xml">
    <link rel="icon" href="{{ asset('icons/favicon-32.png') }}" sizes="32x32" type="image/png">
    <link rel="apple-touch-icon" href="{{ asset('icons/apple-touch-icon.png') }}">
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    <meta name="application-name" content="Планер">
    <meta name="apple-mobile-web-app-title" content="Планер">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="theme-color" content="{{ $appearance->palette()['paper'] }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" id="fonts-link" @if ($appearance->fontsUrl()) href="{{ $appearance->fontsUrl() }}" @endif>
    @stack('head')
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
    <style id="appearance-style">{!! $appearance->css() !!}</style>
</head>
<body>
<div class="page @yield('page-class')">
    <header class="top">
        <nav class="tabs" aria-label="Разделы">
            <a class="tab" href="{{ route('planner') }}" @if (request()->routeIs('planner')) aria-current="page" @endif>
                <svg class="tab__icon" viewBox="0 0 16 16" aria-hidden="true"><path d="M2.5 4.25l1.25 1.25L6 3.25M2.5 11l1.25 1.25L6 10M8.5 4.5h5M8.5 11.25h5"/></svg>
                <span>Планер</span>
            </a>
            <a class="tab" href="{{ route('schedule') }}" @if (request()->routeIs('schedule')) aria-current="page" @endif>
                <svg class="tab__icon" viewBox="0 0 16 16" aria-hidden="true"><path d="M1.5 6L8 3l6.5 3L8 9zM4.25 7.75v3.25c0 .9 1.7 2 3.75 2s3.75-1.1 3.75-2V7.75M14.5 6v3.5"/></svg>
                <span>Пары</span>
            </a>
            <a class="tab" href="{{ route('people') }}" @if (request()->routeIs('people')) aria-current="page" @endif>
                <svg class="tab__icon" viewBox="0 0 16 16" aria-hidden="true"><circle cx="6" cy="5.25" r="2.25"/><path d="M1.75 13.5c.4-2.4 2.1-3.75 4.25-3.75s3.85 1.35 4.25 3.75M10.75 3.25a2.25 2.25 0 0 1 0 4.25M12.25 9.9c1.1.5 1.8 1.6 2 3.6"/></svg>
                <span>Люди</span>
            </a>
            <a class="tab" href="{{ route('habits') }}" @if (request()->routeIs('habits')) aria-current="page" @endif>
                <svg class="tab__icon" viewBox="0 0 16 16" aria-hidden="true"><path d="M13.25 8.5a5.25 5.25 0 1 1-1.6-4.25M13.25 2v2.75H10.5M5.75 8.25l1.5 1.5 3-3"/></svg>
                <span>Привычки</span>
            </a>
            <a class="tab" href="{{ route('stats') }}" @if (request()->routeIs('stats')) aria-current="page" @endif>
                <svg class="tab__icon" viewBox="0 0 16 16" aria-hidden="true"><path d="M3 13.5V8.5M8 13.5V3M13 13.5V6.5M1.5 13.5h13"/></svg>
                <span>Статистика</span>
            </a>
            <a class="tab tab--settings" href="{{ route('settings') }}" @if (request()->routeIs('settings')) aria-current="page" @endif>
                <svg class="tab__icon" viewBox="0 0 16 16" aria-hidden="true"><path d="M2 4.5h2M7.5 4.5H14M2 11.5h6.5M12 11.5h2"/><circle cx="5.75" cy="4.5" r="1.75"/><circle cx="10.25" cy="11.5" r="1.75"/></svg>
                <span>Оформление</span>
            </a>
        </nav>
        <div class="top__aside">
            @yield('header-aside')
            <a class="icon-btn settings-link" href="{{ route('settings') }}" aria-label="Оформление" title="Оформление"
               @if (request()->routeIs('settings')) aria-current="page" @endif>
                <svg viewBox="0 0 16 16" aria-hidden="true"><path d="M2 4.5h2M7.5 4.5H14M2 11.5h6.5M12 11.5h2"/><circle cx="5.75" cy="4.5" r="1.75"/><circle cx="10.25" cy="11.5" r="1.75"/></svg>
            </a>
            <form method="POST" action="{{ route('settings.theme') }}" data-theme-toggle>
                @csrf
                @method('PATCH')
                <input type="hidden" name="theme" value="{{ $appearance->get('theme') === 'dark' ? 'light' : 'dark' }}">
                <button type="submit" class="icon-btn theme-toggle" aria-label="Переключить светлую и тёмную тему">
                    <svg class="theme-toggle__moon" viewBox="0 0 16 16" aria-hidden="true"><path d="M13 9.5A5.5 5.5 0 0 1 6.5 3a5.5 5.5 0 1 0 6.5 6.5z"/></svg>
                    <svg class="theme-toggle__sun" viewBox="0 0 16 16" aria-hidden="true"><circle cx="8" cy="8" r="2.75"/><path d="M8 1.5v1.5M8 13v1.5M1.5 8H3M13 8h1.5M3.4 3.4l1.06 1.06M11.54 11.54l1.06 1.06M3.4 12.6l1.06-1.06M11.54 4.46l1.06-1.06"/></svg>
                </button>
            </form>
        </div>
    </header>

    @yield('content')
</div>

@yield('dialogs')

@if (session('status'))
    <div class="toast" role="status" data-toast @if (session('undo')) data-toast-long @endif>
        <span>{{ session('status') }}</span>
        @if (session('undo'))
            <form method="POST" action="{{ session('undo') }}">
                @csrf
                <button type="submit" class="toast__action">Вернуть</button>
            </form>
        @endif
    </div>
@endif

<script src="{{ asset('js/common.js') }}?v={{ filemtime(public_path('js/common.js')) }}" defer></script>
@stack('scripts')
</body>
</html>
