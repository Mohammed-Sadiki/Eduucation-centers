@props([
    'title',
    'description' => null,
    'back' => null,
    'backLabel' => null,
])

@php
    $routeName = request()->route()?->getName() ?? '';
    $isRootPage = in_array($routeName, ['admin.home', 'home', 'login']) || ($routeName === 'dashboard' && !request()->has('view'));

    // Si explicitement désactivé (false, 'false', 'none')
    $showBack = true;
    if ($back === false || $back === 'false' || $back === 'none' || ($back === null && $isRootPage)) {
        $showBack = false;
    }

    $fallbackUrl = is_string($back) && !in_array($back, ['true', '1']) ? $back : null;
@endphp

<div {{ $attributes->merge(['class' => 'mb-7 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between']) }}>
    <div class="flex items-center gap-3.5 [direction:ltr]" dir="ltr">
        @if($showBack)
            <x-ui.back-button :fallback="$fallbackUrl" :label="$backLabel" />
        @endif
        <div class="text-start {{ current_dir() === 'rtl' ? '[direction:rtl]' : '' }}">
            <h1 class="ec-page-title text-slate-900 dark:text-white">{{ $title }}</h1>
            @if($description)
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $description }}</p>
            @endif
        </div>
    </div>
    @if($slot->isNotEmpty())
        <div class="flex flex-wrap items-center gap-2">{{ $slot }}</div>
    @endif
</div>
