@props(['title', 'description' => null])

<div {{ $attributes->merge(['class' => 'mb-7 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between']) }}>
    <div>
        <h1 class="ec-page-title text-slate-900 dark:text-white">{{ $title }}</h1>
        @if($description)
            <p class="mt-1.5 text-base text-slate-500 dark:text-slate-400">{{ $description }}</p>
        @endif
    </div>
    @if($slot->isNotEmpty())
        <div class="flex flex-wrap items-center gap-2">{{ $slot }}</div>
    @endif
</div>
