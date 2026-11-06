@extends('layouts.public')

@section('title', __('Education Centers') . ' — ' . config('app.name'))
@section('description', __('Plateforme de gestion et de découverte des centres de formation.'))

@php
    $heroTitleHtml = preg_replace(
        '/(ici\.|هنا\.)/u',
        '<span class="relative inline-block whitespace-nowrap text-brand-600">$1<svg class="absolute -inset-x-1 -bottom-1.5 h-2.5 w-[calc(100%+0.5rem)] text-brand-400" viewBox="0 0 120 12" fill="none" preserveAspectRatio="none" aria-hidden="true"><path d="M3 9c30-6 84-6 114-3" stroke="currentColor" stroke-width="4" stroke-linecap="round" opacity=".45"/></svg></span>',
        e(__('Votre avenir commence ici.'))
    );

    $heroFeatures = [
        ['icon' => 'book', 'title' => __('Formations reconnues'), 'text' => __('Des programmes adaptés au marché.')],
        ['icon' => 'users', 'title' => __('Accompagnement'), 'text' => __('Une équipe à votre écoute.')],
        ['icon' => 'chart', 'title' => __('Insertion professionnelle'), 'text' => __('Des compétences pour votre avenir.')],
    ];

    $domainBadges = [
        'langues' => ['label' => __('Langues'), 'class' => 'bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-300'],
        'informatique' => ['label' => __('Informatique'), 'class' => 'bg-turquoise-50 text-turquoise-600 dark:bg-turquoise-500/15 dark:text-turquoise-400'],
        'sante' => ['label' => __('Santé'), 'class' => 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-400'],
        'hotellerie' => ['label' => __('Hôtellerie'), 'class' => 'bg-amber-50 text-amber-600 dark:bg-amber-500/15 dark:text-amber-400'],
        'maconnerie' => ['label' => __('Maçonnerie'), 'class' => 'bg-orange-50 text-orange-600 dark:bg-orange-500/15 dark:text-orange-400'],
        'design-interieur' => ['label' => __('Design intérieur'), 'class' => 'bg-pink-50 text-pink-600 dark:bg-pink-500/15 dark:text-pink-400'],
        'design-graphique' => ['label' => __('Design graphique'), 'class' => 'bg-fuchsia-50 text-fuchsia-600 dark:bg-fuchsia-500/15 dark:text-fuchsia-400'],
    ];

    $whyFeatures = [
        ['icon' => 'building', 'title' => __('Multi-centres'), 'text' => __('Un même standard pédagogique, plusieurs villes.')],
        ['icon' => 'clipboard', 'title' => __('Suivi clair'), 'text' => __('Présences, notes, documents et certificats centralisés.')],
        ['icon' => 'sparkles', 'title' => __('Candidature simple'), 'text' => __('Déposez votre dossier en ligne, nous le traitons ensuite.')],
    ];
@endphp

@section('content')
{{-- ───────────────────────── HERO ───────────────────────── --}}
<section class="relative overflow-hidden bg-gradient-to-b from-brand-50/60 via-slate-50/40 to-transparent dark:from-slate-900/60 dark:via-slate-950/40 dark:to-transparent">
    <div class="pointer-events-none absolute -top-32 -start-32 h-96 w-96 rounded-full bg-brand-200/40 blur-3xl dark:bg-brand-500/10" aria-hidden="true"></div>
    <div class="pointer-events-none absolute top-24 -end-24 h-80 w-80 rounded-full bg-turquoise-100/50 blur-3xl dark:bg-turquoise-500/10" aria-hidden="true"></div>

    <div class="relative mx-auto grid max-w-7xl gap-14 px-4 pt-14 pb-24 sm:px-6 lg:grid-cols-2 lg:items-center lg:gap-10 lg:px-8 lg:pt-20 lg:pb-32">
        <div>
            <p class="text-xs font-bold uppercase tracking-[0.25em] text-brand-600 sm:text-sm">{{ __('Education Centers') }}</p>
            <h1 class="mt-4 text-4xl leading-[1.15] font-extrabold tracking-tight text-slate-900 sm:text-5xl lg:text-[3.4rem] dark:text-white">
                {!! $heroTitleHtml !!}
            </h1>
            <p class="mt-5 max-w-lg text-lg leading-relaxed text-slate-500 dark:text-slate-400">{{ __('Découvrez nos centres, nos formations et déposez votre candidature en quelques minutes.') }}</p>

            <div class="mt-8 flex flex-wrap items-center gap-4">
                <a href="{{ route('public.formations') }}" class="inline-flex items-center gap-2 rounded-xl bg-brand-600 px-6 py-3.5 text-sm font-semibold text-white shadow-lg shadow-brand-600/30 transition hover:-translate-y-0.5 hover:bg-brand-700">
                    {{ __('Découvrir nos formations') }}
                    <x-icon name="arrow-right" class="h-4 w-4 rtl:-scale-x-100" />
                </a>
                <a href="{{ route('public.apply') }}" class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-6 py-3.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:-translate-y-0.5 hover:border-slate-400 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-200">
                    {{ __('S’inscrire maintenant') }}
                </a>
            </div>

            <div class="mt-12 grid gap-6 sm:grid-cols-3">
                @foreach($heroFeatures as $feature)
                    <div class="flex gap-3 sm:block">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-white text-brand-600 shadow-sm ring-1 ring-slate-200 dark:bg-slate-900 dark:text-brand-400 dark:ring-slate-700">
                            <x-icon name="{{ $feature['icon'] }}" class="h-5 w-5" />
                        </span>
                        <div class="sm:mt-3">
                            <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ $feature['title'] }}</p>
                            <p class="mt-1 text-xs leading-relaxed text-slate-500 dark:text-slate-400">{{ $feature['text'] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="relative mx-auto mb-10 w-full max-w-xl lg:mb-0 lg:max-w-none">
            <div class="pointer-events-none absolute -top-10 -end-6 h-44 w-44 rounded-full border-[10px] border-brand-100/80 dark:border-brand-500/10" aria-hidden="true"></div>

            <div class="relative overflow-hidden rounded-[2rem] shadow-2xl shadow-brand-900/15 ring-1 ring-slate-900/5 dark:ring-white/10">
                <img src="{{ asset('images/landing/hero-student.jpg') }}" alt="{{ __('Étudiante devant un campus Education Centers') }}" class="h-72 w-full object-cover object-top sm:h-96 lg:h-[28rem]" width="1536" height="1024">
                <div class="absolute inset-0 bg-gradient-to-t from-brand-900/10 to-transparent" aria-hidden="true"></div>
            </div>

            <div class="animate-ec-float absolute top-8 -end-2 max-w-[11rem] rounded-2xl bg-white/95 p-3.5 shadow-xl ring-1 ring-slate-900/5 backdrop-blur sm:-end-6 sm:max-w-[12rem] dark:bg-slate-900/95 dark:ring-white/10">
                <div class="flex items-center gap-2">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-300">
                        <x-icon name="map" class="h-4 w-4" />
                    </span>
                    <p class="text-xs leading-snug font-semibold text-slate-800 dark:text-slate-100">{{ __('Des centres proches de vous') }}</p>
                </div>
                <svg class="mt-1.5 h-5 w-16 text-brand-400 rtl:rotate-180" viewBox="0 0 64 20" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><path d="M4 4c10 12 30 14 44 8m0 0-6-5m6 5-8 2"/></svg>
            </div>

            <div class="animate-ec-float-slow absolute top-1/2 -start-3 -translate-y-1/2 rounded-2xl bg-white/95 px-4 py-3 shadow-xl ring-1 ring-slate-900/5 backdrop-blur sm:-start-6 dark:bg-slate-900/95 dark:ring-white/10">
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-brand-600 text-white">
                    <x-icon name="academic" class="h-4 w-4" />
                </span>
                <p class="mt-2 text-xs leading-snug font-semibold text-slate-800 dark:text-slate-100">
                    {{ __('Apprenez') }}<br>{{ __('Évoluez') }}<br>{{ __('Réussissez') }}
                </p>
            </div>

            <div class="absolute -bottom-10 start-4 end-4 rounded-2xl bg-white/95 p-5 shadow-2xl ring-1 ring-slate-900/5 backdrop-blur sm:start-10 sm:end-10 dark:bg-slate-900/95 dark:ring-white/10">
                <dl class="grid grid-cols-3 divide-x divide-slate-100 text-center dark:divide-slate-800">
                    <div class="flex flex-col px-2">
                        <dd class="order-1 flex items-center justify-center gap-1.5 text-xl font-extrabold text-slate-900 sm:text-2xl dark:text-white">
                            <x-icon name="building" class="h-4 w-4 text-brand-500" />
                            {{ format_number($stats['centers']) }}
                        </dd>
                        <dt class="order-2 mt-1.5 text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('Centres') }}</dt>
                    </div>
                    <div class="flex flex-col px-2">
                        <dd class="order-1 flex items-center justify-center gap-1.5 text-xl font-extrabold text-slate-900 sm:text-2xl dark:text-white">
                            <x-icon name="book" class="h-4 w-4 text-brand-500" />
                            {{ format_number($stats['formations']) }}
                        </dd>
                        <dt class="order-2 mt-1.5 text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('Formations') }}</dt>
                    </div>
                    <div class="flex flex-col px-2">
                        <dd class="order-1 flex items-center justify-center gap-1.5 text-xl font-extrabold text-slate-900 sm:text-2xl dark:text-white">
                            <x-icon name="users" class="h-4 w-4 text-brand-500" />
                            {{ format_number($stats['students']) }}
                        </dd>
                        <dt class="order-2 mt-1.5 text-xs font-medium text-slate-500 dark:text-slate-400">{{ __('Étudiants') }}</dt>
                    </div>
                </dl>
            </div>
        </div>
    </div>
</section>

{{-- ───────────────────────── NOS CENTRES ───────────────────────── --}}
<section class="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8 lg:py-24">
    <div class="mb-10 flex flex-wrap items-end justify-between gap-4">
        <div>
            <h2 class="text-3xl font-bold tracking-tight text-slate-900 dark:text-white">{{ __('Nos centres') }}</h2>
            <p class="mt-2 text-slate-500 dark:text-slate-400">{{ __('Des campus modernes et bien équipés pour votre réussite.') }}</p>
        </div>
        <a href="{{ route('public.centers') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-brand-600 transition hover:gap-2.5 hover:text-brand-700">
            {{ __('Voir tous les centres') }}
            <x-icon name="arrow-right" class="h-4 w-4 rtl:-scale-x-100" />
        </a>
    </div>

    <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
        @forelse($centers as $center)
            @php
                $campusImg = 'images/landing/campus-'.(($loop->index % 3) + 1).'.jpg';
            @endphp
            <article class="group ec-card overflow-hidden transition duration-300 hover:-translate-y-1 hover:shadow-lg">
                <div class="relative h-44 overflow-hidden">
                    <img src="{{ asset($campusImg) }}" alt="{{ $center->translated('name') }}" class="h-full w-full object-cover transition duration-500 group-hover:scale-105" width="1536" height="1024">
                    <span class="absolute top-3 end-3 rounded-full bg-white/95 px-3 py-1 text-xs font-semibold text-slate-700 shadow-sm backdrop-blur dark:bg-slate-900/95 dark:text-slate-200">{{ $center->city }}</span>
                </div>
                <div class="p-5">
                    <h3 class="text-lg font-bold text-slate-900 dark:text-white">{{ $center->translated('name') }}</h3>
                    <div class="mt-3 space-y-1.5 text-sm text-slate-500 dark:text-slate-400">
                        <p class="flex items-center gap-2">
                            <x-icon name="map" class="h-4 w-4 shrink-0 text-slate-400" />
                            {{ $center->city }}{{ $center->country ? ', '.$center->country : '' }}
                        </p>
                        <p class="flex items-center gap-2">
                            <x-icon name="users" class="h-4 w-4 shrink-0 text-slate-400" />
                            {{ format_number($center->students_count) }} {{ __('étudiants') }}
                            <span class="text-slate-300 dark:text-slate-600">&bull;</span>
                            {{ format_number($center->formations_count) }} {{ __('formation(s)') }}
                        </p>
                    </div>
                    <a href="{{ route('public.centers.show', $center) }}" class="ec-btn-secondary ec-btn-sm mt-5 !rounded-xl group-hover:!border-brand-300 group-hover:!text-brand-700 dark:group-hover:!border-brand-600 dark:group-hover:!text-brand-300">
                        {{ __('Voir le centre') }}
                        <x-icon name="arrow-right" class="h-3.5 w-3.5 rtl:-scale-x-100" />
                    </a>
                </div>
            </article>
        @empty
            <p class="text-sm text-slate-500 md:col-span-2 lg:col-span-3">{{ __('Aucun centre publié pour le moment.') }}</p>
        @endforelse
    </div>
</section>

{{-- ───────────────────── FORMATIONS POPULAIRES ───────────────────── --}}
<section class="bg-slate-50/60 py-16 dark:bg-slate-900/40 lg:py-24 backdrop-blur-[2px]">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="mb-10 flex flex-wrap items-end justify-between gap-4">
            <div>
                <h2 class="text-3xl font-bold tracking-tight text-slate-900 dark:text-white">{{ __('Formations populaires') }}</h2>
                <p class="mt-2 text-slate-500 dark:text-slate-400">{{ __('Découvrez nos formations les plus demandées.') }}</p>
            </div>
            <a href="{{ route('public.formations') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-brand-600 transition hover:gap-2.5 hover:text-brand-700">
                {{ __('Voir toutes les formations') }}
                <x-icon name="arrow-right" class="h-4 w-4 rtl:-scale-x-100" />
            </a>
        </div>

        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
            @forelse($formations as $formation)
                @php
                    $classroomImg = 'images/landing/classroom-'.(($loop->index % 2) + 1).'.jpg';
                    $badge = $domainBadges[strtolower((string) $formation->domain)] ?? null;
                @endphp
                <a href="{{ route('public.formations.show', $formation) }}" class="group ec-card flex items-center gap-4 p-4 transition duration-300 hover:-translate-y-0.5 hover:border-brand-200 hover:shadow-md dark:hover:border-brand-500/40">
                    <img src="{{ asset($classroomImg) }}" alt="" class="h-20 w-24 shrink-0 rounded-xl object-cover sm:h-24 sm:w-28" width="1536" height="1024">
                    <div class="min-w-0 flex-1">
                        @if($badge)
                            <span class="inline-block rounded-full px-2.5 py-0.5 text-[11px] font-bold {{ $badge['class'] }}">{{ $badge['label'] }}</span>
                        @endif
                        <h3 class="mt-1 truncate text-sm font-bold text-slate-900 dark:text-white">{{ $formation->translated('name') }}</h3>
                        <p class="mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-slate-500 dark:text-slate-400">
                            @if($formation->center)
                                <span class="inline-flex items-center gap-1 font-medium text-slate-600 dark:text-slate-300">
                                    <x-icon name="building" class="h-3.5 w-3.5 text-slate-400" />
                                    {{ $formation->center->translated('name') }}
                                </span>
                            @endif
                            @if($formation->duration_hours)
                                <span class="inline-flex items-center gap-1">
                                    <x-icon name="clock" class="h-3.5 w-3.5" />
                                    {{ format_number($formation->duration_hours) }} h
                                </span>
                            @endif
                            @php
                                $displayedPrice = $formation->price ?? $formation->annual_price;
                            @endphp
                            @if($displayedPrice !== null && $displayedPrice > 0)
                                <span class="inline-flex items-center gap-1 font-semibold text-slate-700 dark:text-slate-200">
                                    <x-icon name="banknotes" class="h-3.5 w-3.5" />
                                    {{ format_price((float) $displayedPrice, $formation->currency ?? default_currency()) }}
                                </span>
                            @endif
                        </p>
                    </div>
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-slate-200 text-slate-400 transition group-hover:border-brand-600 group-hover:bg-brand-600 group-hover:text-white dark:border-slate-700">
                        <x-icon name="arrow-right" class="h-4 w-4 rtl:-scale-x-100" />
                    </span>
                </a>
            @empty
                <p class="text-sm text-slate-500 md:col-span-2 lg:col-span-3">{{ __('Aucune formation publiée pour le moment.') }}</p>
            @endforelse
        </div>
    </div>
</section>

{{-- ───────────────────── POURQUOI NOUS CHOISIR ───────────────────── --}}
<section class="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8 lg:py-24">
    <div class="mb-10 max-w-2xl">
        <h2 class="text-3xl font-bold tracking-tight text-slate-900 dark:text-white">{{ __('Pourquoi nous choisir') }}</h2>
        <p class="mt-2 text-slate-500 dark:text-slate-400">{{ __('Une expérience pensée pour votre réussite, de la candidature au diplôme.') }}</p>
    </div>

    <div class="grid gap-6 md:grid-cols-3">
        @foreach($whyFeatures as $index => $feature)
            <div class="relative ec-card overflow-hidden p-6 transition duration-300 hover:-translate-y-1 hover:shadow-lg">
                <span class="pointer-events-none absolute -top-3 end-3 text-6xl font-extrabold text-slate-100 dark:text-slate-800" aria-hidden="true">0{{ $index + 1 }}</span>
                <span class="relative flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-brand-500 to-brand-700 text-white shadow-md shadow-brand-600/30">
                    <x-icon name="{{ $feature['icon'] }}" class="h-6 w-6" />
                </span>
                <h3 class="relative mt-5 text-lg font-bold text-slate-900 dark:text-white">{{ $feature['title'] }}</h3>
                <p class="relative mt-2 text-sm leading-relaxed text-slate-500 dark:text-slate-400">{{ $feature['text'] }}</p>
            </div>
        @endforeach
    </div>
</section>

{{-- ───────────────────────── ACTUALITÉS ───────────────────────── --}}
@if($announcements->isNotEmpty())
    <section class="bg-slate-50/60 py-16 dark:bg-slate-900/40 lg:py-24 backdrop-blur-[2px]">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="mb-10 flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h2 class="text-3xl font-bold tracking-tight text-slate-900 dark:text-white">{{ __('Actualités') }}</h2>
                    <p class="mt-2 text-slate-500 dark:text-slate-400">{{ __('Les dernières nouvelles de nos centres.') }}</p>
                </div>
                <a href="{{ route('public.announcements') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-brand-600 transition hover:gap-2.5 hover:text-brand-700">
                    {{ __('Toutes les actualités') }}
                    <x-icon name="arrow-right" class="h-4 w-4 rtl:-scale-x-100" />
                </a>
            </div>

            <div class="grid gap-6 md:grid-cols-3">
                @foreach($announcements as $announcement)
                    <a href="{{ route('public.announcements.show', $announcement->slug) }}" class="group ec-card block overflow-hidden transition duration-300 hover:-translate-y-1 hover:shadow-lg">
                        @if($announcement->image_path)
                            <div class="h-40 overflow-hidden">
                                <img src="{{ asset('storage/'.$announcement->image_path) }}" alt="" class="h-full w-full object-cover transition duration-500 group-hover:scale-105">
                            </div>
                        @else
                            <div class="flex h-40 items-center justify-center bg-gradient-to-br from-brand-500 via-brand-600 to-brand-800">
                                <x-icon name="megaphone" class="h-10 w-10 text-white/70" />
                            </div>
                        @endif
                        <div class="p-5">
                            <p class="text-xs font-semibold text-slate-400">{{ format_date($announcement->published_at, 'd M Y') }}</p>
                            <h3 class="mt-2 font-bold text-slate-900 transition group-hover:text-brand-600 dark:text-white">{{ $announcement->title }}</h3>
                            <p class="mt-2 line-clamp-3 text-sm leading-relaxed text-slate-500 dark:text-slate-400">{{ \Illuminate\Support\Str::limit(strip_tags($announcement->content), 140) }}</p>
                            <span class="mt-4 inline-flex items-center gap-1.5 text-sm font-semibold text-brand-600">
                                {{ __('Lire la suite') }}
                                <x-icon name="arrow-right" class="h-4 w-4 rtl:-scale-x-100" />
                            </span>
                        </div>
                    </a>
                @endforeach
            </div>
        </div>
    </section>
@endif

{{-- ───────────────────────── APPEL À L'ACTION ───────────────────────── --}}
<section class="mx-auto max-w-7xl px-4 pt-4 pb-20 sm:px-6 lg:px-8 lg:pb-28">
    <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-brand-600 via-brand-600 to-brand-800 px-6 py-16 text-center shadow-2xl shadow-brand-900/25 sm:px-16 sm:py-20">
        <div class="pointer-events-none absolute -top-24 -start-24 h-72 w-72 rounded-full bg-white/10 blur-2xl" aria-hidden="true"></div>
        <div class="pointer-events-none absolute -bottom-32 -end-16 h-80 w-80 rounded-full bg-brand-400/30 blur-3xl" aria-hidden="true"></div>

        <div class="relative">
            <h2 class="mx-auto max-w-2xl text-3xl font-bold tracking-tight text-white sm:text-4xl">{{ __('Prêt à rejoindre un centre ?') }}</h2>
            <p class="mx-auto mt-4 max-w-xl text-lg text-brand-100">{{ __('Déposez votre candidature. L’équipe du centre vous recontactera.') }}</p>
            <a href="{{ route('public.apply') }}" class="mt-8 inline-flex items-center gap-2 rounded-xl bg-white px-7 py-3.5 text-sm font-bold text-brand-700 shadow-xl transition hover:-translate-y-0.5 hover:bg-brand-50">
                {{ __('Déposer ma candidature') }}
                <x-icon name="arrow-right" class="h-4 w-4 rtl:-scale-x-100" />
            </a>
        </div>
    </div>
</section>
@endsection
