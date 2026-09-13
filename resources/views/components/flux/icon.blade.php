@props(['name' => null])

@php
    $label = is_string($name) ? str_replace(['-', '_'], ' ', $name) : 'icon';

    $icons = [
        'adjustments-horizontal' => '<path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M3.75 6H7.5m3 0a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0Zm3 12h6.75M3.75 18h3.75m6 0a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0Zm3-6h3.75M3.75 12h9.75m3 0a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0Z" />',
        'archive-box' => '<path stroke-linecap="round" stroke-linejoin="round" d="m20.25 7.5-.625 10.632A2.25 2.25 0 0 1 17.378 20.25H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5m16.5 0h-16.5m16.5 0-1.5-3.75h-13.5L3.75 7.5m6 4.5h4.5" />',
        'archive-box-x-mark' => '<path stroke-linecap="round" stroke-linejoin="round" d="m20.25 7.5-.625 10.632A2.25 2.25 0 0 1 17.378 20.25H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5m16.5 0h-16.5m16.5 0-1.5-3.75h-13.5L3.75 7.5m6.72 5.03 3.06 3.06m0-3.06-3.06 3.06" />',
        'arrow-down-tray' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M7.5 10.5 12 15m0 0 4.5-4.5M12 15V3" />',
        'arrow-path' => '<path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992V4.356M2.985 19.644v-4.992h4.992m-4.218-.001A9 9 0 0 0 18.36 6.64M20.241 9.35A9 9 0 0 0 5.64 17.36" />',
        'arrow-up-tray' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M7.5 7.5 12 3m0 0 4.5 4.5M12 3v12" />',
        'arrow-uturn-left' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 15 3 9m0 0 6-6M3 9h12a6 6 0 0 1 0 12h-3" />',
        'bars-3' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />',
        'briefcase' => '<path stroke-linecap="round" stroke-linejoin="round" d="M20.25 14.15v4.1A2.25 2.25 0 0 1 18 20.5H6a2.25 2.25 0 0 1-2.25-2.25v-4.1m16.5 0A2.25 2.25 0 0 0 18 11.9H6a2.25 2.25 0 0 0-2.25 2.25m16.5 0V8.25A2.25 2.25 0 0 0 18 6h-3.75V4.5A1.5 1.5 0 0 0 12.75 3h-1.5a1.5 1.5 0 0 0-1.5 1.5V6H6a2.25 2.25 0 0 0-2.25 2.25v5.9" />',
        'building-office-2' => '<path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5M6 21V3.75A.75.75 0 0 1 6.75 3h7.5a.75.75 0 0 1 .75.75V21m3 0V8.25a.75.75 0 0 0-.75-.75H15M9 6.75h3M9 10.5h3M9 14.25h3M9 18h3" />',
        'calendar' => '<path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25m10.5-2.25v2.25M3.75 8.25h16.5M5.25 5.25h13.5A1.5 1.5 0 0 1 20.25 6.75v12A1.5 1.5 0 0 1 18.75 20.25H5.25A1.5 1.5 0 0 1 3.75 18.75v-12A1.5 1.5 0 0 1 5.25 5.25Z" />',
        'calendar-days' => '<path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25m10.5-2.25v2.25M3.75 8.25h16.5M7.5 12h.008v.008H7.5V12Zm4.5 0h.008v.008H12V12Zm4.5 0h.008v.008H16.5V12Zm-9 4.5h.008v.008H7.5V16.5Zm4.5 0h.008v.008H12V16.5Zm4.5 0h.008v.008H16.5V16.5Z" />',
        'chart-bar' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3 3v18h18M7.5 16.5v-6m4.5 6v-9m4.5 9v-12" />',
        'check' => '<path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />',
        'check-badge' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M12 2.25l2.17 2.2 3.08-.45.54 3.08 2.71 1.48-1.4 2.75 1.4 2.75-2.71 1.48-.54 3.08-3.08-.45L12 21.75l-2.17-2.2-3.08.45-.54-3.08-2.71-1.48 1.4-2.75-1.4-2.75 2.71-1.48.54-3.08 3.08.45L12 2.25Z" />',
        'chevron-down' => '<path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" />',
        'chevron-right' => '<path stroke-linecap="round" stroke-linejoin="round" d="m9 18 6-6-6-6" />',
        'clipboard-document-list' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 5.25h6M9 12h.008M9 15h.008M12 12h3m-3 3h3M8.25 3h7.5A2.25 2.25 0 0 1 18 5.25v13.5A2.25 2.25 0 0 1 15.75 21h-7.5A2.25 2.25 0 0 1 6 18.75V5.25A2.25 2.25 0 0 1 8.25 3Z" />',
        'clock' => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4 2m5-2a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />',
        'document-check' => '<path stroke-linecap="round" stroke-linejoin="round" d="M10.125 2.25H6.75A2.25 2.25 0 0 0 4.5 4.5v15a2.25 2.25 0 0 0 2.25 2.25h10.5a2.25 2.25 0 0 0 2.25-2.25V11.625m-9.375-9.375L19.5 11.625m-9.375-9.375v7.125h7.125M9 16.5l2.25 2.25L15.75 13.5" />',
        'document-duplicate' => '<path stroke-linecap="round" stroke-linejoin="round" d="M8.25 7.5V6A2.25 2.25 0 0 1 10.5 3.75h6.75A2.25 2.25 0 0 1 19.5 6v10.5a2.25 2.25 0 0 1-2.25 2.25h-1.5M6.75 7.5h6.75A2.25 2.25 0 0 1 15.75 9.75v10.5a2.25 2.25 0 0 1-2.25 2.25H6.75A2.25 2.25 0 0 1 4.5 20.25V9.75A2.25 2.25 0 0 1 6.75 7.5Z" />',
        'document-plus' => '<path stroke-linecap="round" stroke-linejoin="round" d="M10.125 2.25H6.75A2.25 2.25 0 0 0 4.5 4.5v15a2.25 2.25 0 0 0 2.25 2.25h10.5a2.25 2.25 0 0 0 2.25-2.25V11.625m-9.375-9.375L19.5 11.625m-9.375-9.375v7.125h7.125M12 14.25v4.5m2.25-2.25h-4.5" />',
        'document-text' => '<path stroke-linecap="round" stroke-linejoin="round" d="M10.125 2.25H6.75A2.25 2.25 0 0 0 4.5 4.5v15a2.25 2.25 0 0 0 2.25 2.25h10.5a2.25 2.25 0 0 0 2.25-2.25V11.625m-9.375-9.375L19.5 11.625m-9.375-9.375v7.125h7.125M8.25 15h7.5M8.25 18h4.5" />',
        'exclamation-triangle' => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0 3.75h.008v.008H12V16.5Zm8.3 2.1L13.95 4.35a2.25 2.25 0 0 0-3.9 0L3.7 18.6a2.25 2.25 0 0 0 1.95 3.4h12.7a2.25 2.25 0 0 0 1.95-3.4Z" />',
        'eye' => '<path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12s3.75-6.75 9.75-6.75S21.75 12 21.75 12 18 18.75 12 18.75 2.25 12 2.25 12Zm9.75 3a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z" />',
        'home' => '<path stroke-linecap="round" stroke-linejoin="round" d="m2.25 12 9.75-8.25L21.75 12M4.5 10.5v9A1.5 1.5 0 0 0 6 21h4.5v-6h3v6H18a1.5 1.5 0 0 0 1.5-1.5v-9" />',
        'inbox' => '<path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75 5.8 5.65A2.25 2.25 0 0 1 7.82 4.5h8.36a2.25 2.25 0 0 1 2.02 1.15l3.55 7.1M2.25 12.75v4.5A2.25 2.25 0 0 0 4.5 19.5h15a2.25 2.25 0 0 0 2.25-2.25v-4.5M2.25 12.75H8.4a3.75 3.75 0 0 0 7.2 0h6.15" />',
        'list-bullet' => '<path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.008M3.75 12h.008m-.008 5.25h.008" />',
        'lock-closed' => '<path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V7.5a4.5 4.5 0 1 0-9 0v3m-.75 0h10.5A1.5 1.5 0 0 1 18.75 12v7.5A1.5 1.5 0 0 1 17.25 21H6.75A1.5 1.5 0 0 1 5.25 19.5V12a1.5 1.5 0 0 1 1.5-1.5Z" />',
        'minus' => '<path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14" />',
        'paper-clip' => '<path stroke-linecap="round" stroke-linejoin="round" d="m18.375 12.739-7.693 7.693a4.5 4.5 0 0 1-6.364-6.364l9.193-9.193a3 3 0 0 1 4.243 4.243l-9.193 9.193a1.5 1.5 0 0 1-2.121-2.121l7.693-7.693" />',
        'pencil-square' => '<path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L9.582 17.07a4.5 4.5 0 0 1-1.897 1.13L4.5 19.125l.924-3.185a4.5 4.5 0 0 1 1.13-1.897L16.862 4.487ZM19.5 7.125 16.875 4.5" />',
        'plus' => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />',
        'rectangle-stack' => '<path stroke-linecap="round" stroke-linejoin="round" d="M6 7.5h12M6 12h12M6 16.5h12M4.5 4.5h15A1.5 1.5 0 0 1 21 6v12a1.5 1.5 0 0 1-1.5 1.5h-15A1.5 1.5 0 0 1 3 18V6a1.5 1.5 0 0 1 1.5-1.5Z" />',
        'shield-check' => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 21s7.5-3.75 7.5-10.5V5.25L12 2.25 4.5 5.25v5.25C4.5 17.25 12 21 12 21Zm-3-9 2.25 2.25L15.75 9" />',
        'squares-2x2' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3.75h6v6h-6v-6Zm10.5 0h6v6h-6v-6Zm-10.5 10.5h6v6h-6v-6Zm10.5 0h6v6h-6v-6Z" />',
        'tag' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 0 0 3 5.25v4.318c0 .597.237 1.169.659 1.591l9.182 9.182a2.25 2.25 0 0 0 3.182 0l4.318-4.318a2.25 2.25 0 0 0 0-3.182L11.16 3.659A2.25 2.25 0 0 0 9.568 3ZM6.75 6.75h.008v.008H6.75V6.75Z" />',
        'trash' => '<path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166M19.228 5.79 18.16 19.673A2.25 2.25 0 0 1 15.916 21.75H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0A48.108 48.108 0 0 0 16.5 5.5m-9 0a48.11 48.11 0 0 0-2.728.29m0 0A48.667 48.667 0 0 1 12 5.25c2.46 0 4.83.184 7.228.54M9.75 5.25V4.5A1.5 1.5 0 0 1 11.25 3h1.5a1.5 1.5 0 0 1 1.5 1.5v.75" />',
        'user' => '<path stroke-linecap="round" stroke-linejoin="round" d="M15.75 7.5a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.5 20.25a7.5 7.5 0 0 1 15 0" />',
        'users' => '<path stroke-linecap="round" stroke-linejoin="round" d="M15 19.5a6 6 0 1 0-12 0m12 0h6m-6 0a6 6 0 0 1 6-6m-9-6a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm7.5 3a2.25 2.25 0 1 0 0-4.5" />',
        'x-mark' => '<path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />',
    ];

    $path = $icons[$name] ?? '<path stroke-linecap="round" stroke-linejoin="round" d="M12 6.75v.008M12 12v.008M12 17.25v.008" />';
@endphp

<svg {{ $attributes->class('inline-block shrink-0') }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true" role="img">
    {!! $path !!}
    <title>{{ $label }}</title>
</svg>
