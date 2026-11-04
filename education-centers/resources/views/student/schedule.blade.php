@extends('layouts.app')

@section('title', __('Mon emploi du temps'))

@php
    $hasFormationFilter = $formations->count() > 1;
    $hasClassFilter = $classes->count() > 1;
    $typeOptions = [
        'course' => __('Cours'),
        'workshop' => __('Atelier / TP'),
        'exam' => __('Examen'),
        'other' => __('Autre'),
    ];
@endphp

@section('content')
    <x-ui.page-header
        :title="__('Mon emploi du temps')"
        :description="__('Vos séances personnelles : cours, examens et ateliers.').' '.($student?->center?->name ?? auth()->user()->center?->name)"
    >
        <a href="{{ route('student.formations') }}" class="ec-btn-secondary">
            <x-icon name="book" class="h-4 w-4" /> {{ __('Mes formations') }}
        </a>
    </x-ui.page-header>

    {{-- Filtres limités aux formations et classes de l'élève. --}}
    @if($hasFormationFilter || $hasClassFilter)
        <form method="GET" action="{{ route('student.schedule') }}" class="ec-card mb-5 p-4 sm:p-5">
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 lg:items-end">
                @if($hasFormationFilter)
                    <label class="block">
                        <span class="ec-label">{{ __('Formation') }}</span>
                        <select name="formation_id" class="ec-select">
                            <option value="">{{ __('Toutes mes formations') }}</option>
                            @foreach($formations as $formation)
                                <option value="{{ $formation->id }}" @selected((int) $formationId === (int) $formation->id)>
                                    {{ $formation->general_formation_name }}
                                </option>
                            @endforeach
                        </select>
                    </label>
                @endif

                @if($hasClassFilter)
                    <label class="block">
                        <span class="ec-label">{{ __('Classe') }}</span>
                        <select name="class_id" class="ec-select">
                            <option value="">{{ __('Toutes mes classes') }}</option>
                            @foreach($classes as $class)
                                <option value="{{ $class->id }}" @selected((int) $classId === (int) $class->id)>
                                    {{ $class->perimeter_label }}{{ $class->code ? ' ('.$class->code.')' : '' }}
                                </option>
                            @endforeach
                        </select>
                    </label>
                @endif

                <div class="flex flex-wrap gap-2">
                    <button type="submit" class="ec-btn-primary">
                        <x-icon name="filter" class="h-4 w-4" /> {{ __('Filtrer') }}
                    </button>
                    @if($formationId || $classId)
                        <a href="{{ route('student.schedule') }}" class="ec-btn-ghost">{{ __('Réinitialiser') }}</a>
                    @endif
                </div>
            </div>
        </form>
    @endif

    <div class="grid gap-5 xl:grid-cols-4">
        <div class="xl:col-span-3">
            <div class="ec-card p-3 sm:p-5">
                <div id="student-calendar"
                     data-locale="{{ $locale }}"
                     data-dir="{{ $dir }}"
                     data-initial-date="{{ $initialDate }}"></div>
            </div>
            <noscript>
                <div class="ec-card mt-4 p-5 text-sm text-slate-600 dark:text-slate-300">
                    {{ __('Le calendrier interactif nécessite JavaScript. La liste ci-dessous reste disponible.') }}
                </div>
            </noscript>

            {{-- Repli accessible lorsque JavaScript est indisponible. --}}
            <div class="ec-panel mt-4">
                <div class="ec-panel__head">
                    <div class="ec-panel__title">
                        <span class="ec-icon-chip bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-300">
                            <x-icon name="calendar" class="h-5 w-5" />
                        </span>
                        {{ __('Séances affichées') }}
                    </div>
                    <span class="text-xs font-semibold text-slate-500">{{ $schedules->count() }}</span>
                </div>
                <div class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse($schedules as $schedule)
                        <div class="flex flex-col gap-1.5 px-5 py-3 text-sm sm:flex-row sm:items-center sm:justify-between">
                            <div class="min-w-0">
                                <p class="truncate font-semibold text-slate-800 dark:text-slate-100">
                                    {{ $schedule->title ?: $schedule->classRoom?->perimeter_label }}
                                </p>
                                <p class="truncate text-xs text-slate-500">
                                    {{ format_date($schedule->date, 'D d M Y') }} · {{ $schedule->formatted_time }}
                                    @if($schedule->classRoom?->name)
                                        · {{ $schedule->classRoom->perimeter_label }}
                                    @endif
                                </p>
                            </div>
                            <x-status-badge type="schedule" :status="$schedule->status" />
                        </div>
                    @empty
                        <x-ui.empty
                            :title="__('Aucune séance')"
                            :description="__('Aucune séance ne correspond à votre périmètre pour cette période.')"
                        />
                    @endforelse
                </div>
            </div>
        </div>

        <aside class="space-y-5">
            <div class="ec-card p-4">
                <div id="student-mini-calendar"
                     data-locale="{{ $locale }}"
                     data-dir="{{ $dir }}"
                     data-initial-date="{{ $initialDate }}"></div>
            </div>

            <div class="ec-card p-5">
                <h3 class="mb-3 text-sm font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                    {{ __('Types de séances') }}
                </h3>
                <ul class="space-y-2.5">
                    @foreach($typeOptions as $value => $label)
                        <li class="flex items-center gap-2.5 text-sm text-slate-700 dark:text-slate-200">
                            <span class="h-3 w-3 shrink-0 rounded-full" style="background-color: {{ $typeColors[$value] }}"></span>
                            {{ $label }}
                        </li>
                    @endforeach
                </ul>
            </div>

            <div class="rounded-xl border border-brand-200/70 bg-brand-50/60 p-4 text-sm text-brand-800 dark:border-brand-500/25 dark:bg-brand-500/10 dark:text-brand-200">
                <div class="flex items-start gap-2">
                    <x-icon name="info" class="mt-0.5 h-4 w-4 shrink-0" />
                    <p>{{ __('Cliquez sur une séance pour consulter son horaire, sa salle et son professeur.') }}</p>
                </div>
            </div>
        </aside>
    </div>

    {{-- Modale de détail en lecture seule. --}}
    <div x-data="studentScheduleDetail()"
         x-on:student-schedule-detail.window="open($event.detail)"
         x-on:keydown.escape.window="close()"
         x-show="show" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4"
         role="dialog" aria-modal="true">
        <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" x-on:click="close()" x-show="show" x-transition.opacity></div>

        <div class="relative w-full max-w-lg overflow-hidden rounded-2xl bg-white shadow-2xl dark:bg-slate-900"
             x-show="show"
             x-transition:enter="ease-out duration-200"
             x-transition:enter-start="opacity-0 translate-y-3 scale-95"
             x-transition:enter-end="opacity-100 translate-y-0 scale-100">
            <div class="flex items-start justify-between gap-3 border-b border-slate-100 px-5 py-4 dark:border-slate-800"
                 :style="'border-top: 4px solid ' + (event ? event.color : '#6366f1')">
                <div class="min-w-0">
                    <h2 class="truncate text-lg font-bold text-slate-900 dark:text-white" x-text="event ? event.title : ''"></h2>
                    <div class="mt-1.5 flex flex-wrap items-center gap-2">
                        <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold text-white"
                              :style="'background-color:' + (event ? event.color : '#6366f1')" x-text="event ? event.typeLabel : ''"></span>
                        <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-300"
                              x-text="event ? event.statusLabel : ''"></span>
                    </div>
                </div>
                <button type="button" class="rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800"
                        x-on:click="close()" aria-label="{{ __('Fermer') }}">
                    <x-icon name="x" class="h-5 w-5" />
                </button>
            </div>

            <div class="max-h-[60vh] overflow-y-auto px-5 py-4">
                <dl class="grid grid-cols-1 gap-x-4 gap-y-3 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ __('Date') }}</dt>
                        <dd class="mt-0.5 text-sm font-medium text-slate-800 dark:text-slate-100" x-text="event ? event.date : ''"></dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ __('Horaire') }}</dt>
                        <dd class="mt-0.5 text-sm font-medium text-slate-800 dark:text-slate-100" dir="ltr" x-text="event ? event.time : ''"></dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ __('Formation') }}</dt>
                        <dd class="mt-0.5 text-sm font-medium text-slate-800 dark:text-slate-100" x-text="event ? (event.formationName || '-') : ''"></dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ __('Classe') }}</dt>
                        <dd class="mt-0.5 text-sm font-medium text-slate-800 dark:text-slate-100" x-text="event ? (event.className || '-') : ''"></dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ __('Professeur') }}</dt>
                        <dd class="mt-0.5 text-sm font-medium text-slate-800 dark:text-slate-100" x-text="event ? (event.teacherName || '-') : ''"></dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ __('Salle') }}</dt>
                        <dd class="mt-0.5 text-sm font-medium text-slate-800 dark:text-slate-100" x-text="event ? (event.roomName || '-') : ''"></dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ __('Centre') }}</dt>
                        <dd class="mt-0.5 text-sm font-medium text-slate-800 dark:text-slate-100" x-text="event ? (event.centerName || '-') : ''"></dd>
                    </div>
                    <div class="sm:col-span-2" x-show="event && event.description">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ __('Description') }}</dt>
                        <dd class="mt-0.5 whitespace-pre-line text-sm text-slate-600 dark:text-slate-300" x-text="event ? event.description : ''"></dd>
                    </div>
                </dl>
            </div>
        </div>
    </div>

    <script type="application/json" id="student-calendar-events">@json($events)</script>
@endsection

@push('styles')
<style>
    #student-calendar, #student-mini-calendar {
        --fc-border-color: rgb(226 232 240);
        --fc-today-bg-color: rgb(238 242 255 / 0.5);
        --fc-page-bg-color: transparent;
        --fc-neutral-bg-color: rgb(248 250 252);
        --fc-small-font-size: 0.8rem;
        font-feature-settings: 'cv02', 'cv03';
    }
    .dark #student-calendar, .dark #student-mini-calendar {
        --fc-border-color: rgb(30 41 59);
        --fc-today-bg-color: rgb(99 102 241 / 0.10);
        --fc-neutral-bg-color: rgb(15 23 42);
    }
    #student-calendar .fc-toolbar { flex-wrap: wrap; gap: .75rem; margin-bottom: 1.25rem !important; }
    #student-calendar .fc-toolbar-title { font-size: 1.35rem; font-weight: 800; letter-spacing: -.01em; color: #0f172a; }
    .dark #student-calendar .fc-toolbar-title { color: #fff; }
    #student-calendar .fc-scroller { scrollbar-width: thin; }
    #student-calendar .fc-button {
        border-radius: .6rem !important;
        border: 1px solid rgb(226 232 240);
        background: #fff;
        color: #334155;
        font-weight: 600;
        font-size: .82rem;
        padding: .4rem .8rem;
        box-shadow: none !important;
        text-transform: none;
    }
    .dark #student-calendar .fc-button { border-color: rgb(51 65 85); background: rgb(30 41 59); color: #cbd5e1; }
    #student-calendar .fc-button:hover { background: rgb(248 250 252); }
    .dark #student-calendar .fc-button:hover { background: rgb(51 65 85); }
    #student-calendar .fc-button-active {
        background: #4f46e5 !important;
        color: #fff !important;
        border-color: #4f46e5 !important;
    }
    #student-calendar .fc-button:disabled { opacity: .5; }
    #student-calendar .fc-today-button { background: #eef2ff; border-color: #c7d2fe; color: #4338ca; }
    .dark #student-calendar .fc-today-button { background: rgb(99 102 241 / .15); border-color: rgb(99 102 241 / .3); color: #a5b4fc; }
    #student-calendar .fc-col-header-cell-cushion { color: #475569; font-weight: 700; font-size: .82rem; padding: .6rem .25rem; text-decoration: none; }
    .dark #student-calendar .fc-col-header-cell-cushion { color: #94a3b8; }
    #student-calendar .fc-timegrid-slot-label-cushion { color: #94a3b8; font-size: .72rem; text-decoration: none; }
    #student-calendar .fc-daygrid-day-number { color: #475569; font-weight: 600; text-decoration: none; }
    .dark #student-calendar .fc-daygrid-day-number { color: #cbd5e1; }
    #student-calendar .fc-list-day-cushion { background: rgb(248 250 252); }
    .dark #student-calendar .fc-list-day-cushion { background: rgb(30 41 59); }
    #student-calendar .fc-list-event:hover td { background: rgb(238 242 255 / .6); }
    .dark #student-calendar .fc-list-event:hover td { background: rgb(99 102 241 / .1); }
    .fc-event.ec-fc-event {
        border: 1px solid transparent;
        border-inline-start-width: 4px;
        border-radius: .55rem;
        padding: .1rem .3rem;
        font-size: .78rem;
        cursor: pointer;
        box-shadow: 0 1px 2px rgb(15 23 42 / .05);
    }
    .fc-event.ec-fc-event .fc-event-title, .fc-event.ec-fc-event .fc-event-time { font-weight: 600; }
    #student-calendar .fc-event-time, #student-calendar .fc-list-event-time { direction: ltr; unicode-bidi: isolate; white-space: nowrap; }
    #student-calendar .ec-fc-event .fc-event-main { color: inherit !important; }
    .ec-fc-event--course { background: rgb(59 130 246 / .12); border-inline-start-color: #3b82f6; color: #1e40af; }
    .ec-fc-event--workshop { background: rgb(16 185 129 / .12); border-inline-start-color: #10b981; color: #065f46; }
    .ec-fc-event--exam { background: rgb(245 158 11 / .14); border-inline-start-color: #f59e0b; color: #92400e; }
    .ec-fc-event--other { background: rgb(239 68 68 / .12); border-inline-start-color: #ef4444; color: #991b1b; }
    .dark .ec-fc-event--course { background: rgb(59 130 246 / .18); color: #bfdbfe; }
    .dark .ec-fc-event--workshop { background: rgb(16 185 129 / .18); color: #a7f3d0; }
    .dark .ec-fc-event--exam { background: rgb(245 158 11 / .18); color: #fde68a; }
    .dark .ec-fc-event--other { background: rgb(239 68 68 / .18); color: #fecaca; }
    .ec-fc-event--cancelled { opacity: .55; text-decoration: line-through; }
    #student-mini-calendar .fc-toolbar-title { font-size: .95rem; font-weight: 700; }
    #student-mini-calendar .fc-button { padding: .1rem .4rem !important; font-size: .72rem !important; }
    #student-mini-calendar .fc-daygrid-day-frame { min-height: 2rem; }
    #student-mini-calendar .fc-daygrid-day-number { font-size: .72rem; }
    #student-mini-calendar .fc-col-header-cell-cushion {
        display: block; padding: .35rem .05rem; font-size: .62rem; font-weight: 700; line-height: 1.2; white-space: nowrap;
    }
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js" defer></script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/locales-all.global.min.js" defer></script>
<script>
window.studentScheduleDetail = function () {
    return {
        show: false,
        event: null,
        open(props) { this.event = props; this.show = true; },
        close() { this.show = false; this.event = null; },
    };
};
</script>
<script>
window.addEventListener('load', function () {
    if (typeof FullCalendar === 'undefined') { return; }

    const el = document.getElementById('student-calendar');
    const miniEl = document.getElementById('student-mini-calendar');
    if (!el) { return; }

    const events = JSON.parse(document.getElementById('student-calendar-events').textContent || '[]');
    const locale = el.dataset.locale || 'fr';
    const isRtl = el.dataset.dir === 'rtl';
    const initialDate = el.dataset.initialDate || undefined;
    const isMobile = window.matchMedia('(max-width: 639px)').matches;

    const calendar = new FullCalendar.Calendar(el, {
        initialView: isMobile ? 'timeGridDay' : 'timeGridWeek',
        locale: locale,
        direction: isRtl ? 'rtl' : 'ltr',
        initialDate: initialDate,
        events: events,
        height: 720,
        firstDay: 1,
        nowIndicator: true,
        slotMinTime: '07:00:00',
        slotMaxTime: '21:00:00',
        scrollTime: '08:00:00',
        expandRows: true,
        dayMaxEventRows: 4,
        eventTimeFormat: { hour: '2-digit', minute: '2-digit', hour12: false },
        allDayText: @js(__('Toute la journée')),
        buttonText: {
            today: @js(__("Aujourd'hui")),
            day: @js(__('Jour')),
            week: @js(__('Semaine')),
            month: @js(__('Mois')),
            list: @js(__('Liste')),
        },
        headerToolbar: isMobile
            ? { start: 'prev,next', center: 'title', end: 'today' }
            : { start: 'prev,next today', center: 'title', end: 'timeGridDay,timeGridWeek,dayGridMonth,listWeek' },
        eventClick: function (info) {
            info.jsEvent.preventDefault();
            const props = info.event.extendedProps || {};
            window.dispatchEvent(new CustomEvent('student-schedule-detail', {
                detail: { ...props, title: info.event.title },
            }));
        },
    });
    calendar.render();

    if (miniEl) {
        const mini = new FullCalendar.Calendar(miniEl, {
            initialView: 'dayGridMonth',
            locale: locale,
            direction: isRtl ? 'rtl' : 'ltr',
            initialDate: initialDate,
            height: 'auto',
            headerToolbar: { start: 'prev', center: 'title', end: 'next' },
            dayHeaderFormat: { weekday: 'narrow' },
            fixedWeekCount: false,
            dateClick: function (info) { calendar.gotoDate(info.date); },
        });
        mini.render();
    }
});
</script>
@endpush
