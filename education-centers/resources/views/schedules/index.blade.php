@extends('layouts.app')

@php
    $dir = current_dir();
    $locale = app()->getLocale();
    $isRtl = $dir === 'rtl';
    $typeColors = \App\Http\Controllers\ScheduleController::TYPE_COLORS;
@endphp

@section('title', __('Emploi du temps'))
@section('description', __('Planning interactif des séances.'))

@section('content')
{{-- ══════════════════════════════════════════════════════════════════
     EN-TÊTE DE PAGE
═══════════════════════════════════════════════════════════════════ --}}
<x-ui.page-header :title="__('Planning des séances')" :description="__('Cours, examens et ateliers.')">
    @if(auth()->user()->can('create', \App\Models\Schedule::class))
        <a href="{{ route('schedules.create') }}" class="ec-btn-primary">
            <x-icon name="plus" class="h-4 w-4" /> {{ __('Programmer une séance') }}
        </a>
    @endif
</x-ui.page-header>

{{-- ══════════════════════════════════════════════════════════════════
     BARRE DE FILTRES
═══════════════════════════════════════════════════════════════════ --}}
<form method="GET" action="{{ route('schedules.index') }}" class="ec-card mb-5 p-4 sm:p-5" id="planning-filters">
    <div class="grid gap-4 lg:grid-cols-12 lg:items-end">
        @if(auth()->user()->isSuperAdmin())
            <div class="lg:col-span-3">
                <label class="ec-label">{{ __('Centre') }}</label>
                <select name="center_id" class="ec-select">
                    <option value="">{{ __('Tous les centres') }}</option>
                    @foreach($centers as $c)
                        <option value="{{ $c->id }}" @selected(request('center_id') == $c->id)>{{ $c->translated('name') }}</option>
                    @endforeach
                </select>
            </div>
        @endif

        <div class="{{ auth()->user()->isSuperAdmin() ? 'lg:col-span-3' : 'lg:col-span-4' }}">
            <label class="ec-label">{{ __('Classe') }}</label>
            <select name="class_id" class="ec-select">
                <option value="">{{ __('Toutes les classes') }}</option>
                @foreach($classes as $cl)
                    <option value="{{ $cl->id }}" @selected(request('class_id') == $cl->id)>{{ $cl->perimeter_label }}</option>
                @endforeach
            </select>
        </div>

        <div class="{{ auth()->user()->isSuperAdmin() ? 'lg:col-span-3' : 'lg:col-span-4' }}">
            <label class="ec-label">{{ __('Professeur') }}</label>
            <select name="teacher_id" class="ec-select">
                <option value="">{{ __('Tous les professeurs') }}</option>
                @foreach($teachers as $t)
                    <option value="{{ $t->id }}" @selected(request('teacher_id') == $t->id)>{{ $t->full_name_with_formation }}</option>
                @endforeach
            </select>
        </div>

        <div class="{{ auth()->user()->isSuperAdmin() ? 'lg:col-span-3' : 'lg:col-span-4' }}">
            <label class="ec-label">{{ __('Type de séance') }}</label>
            <select name="type" class="ec-select">
                <option value="">{{ __('Tous les types') }}</option>
                @foreach($typeOptions as $value => $label)
                    <option value="{{ $value }}" @selected(request('type') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="flex gap-2 lg:col-span-12 xl:col-span-3">
            <button type="submit" class="ec-btn-secondary flex-1 justify-center">
                <x-icon name="filter" class="h-4 w-4" /> <span class="hidden sm:inline">{{ __('Filtrer') }}</span>
            </button>
            @if(collect(['center_id','class_id','teacher_id','type','status','date'])->filter(fn($k) => request($k))->isNotEmpty())
                <a href="{{ route('schedules.index') }}" class="ec-btn-ghost" title="{{ __('Réinitialiser') }}">
                    <x-icon name="x" class="h-4 w-4" />
                </a>
            @endif
        </div>
    </div>
</form>

{{-- ══════════════════════════════════════════════════════════════════
     PLANNING + PANNEAU LATÉRAL
═══════════════════════════════════════════════════════════════════ --}}
<div class="grid gap-5 xl:grid-cols-5">
    {{-- Calendrier principal --}}
    <div class="xl:col-span-4">
        <div class="ec-card p-3 sm:p-5">
            <div id="planning-calendar"
                 data-locale="{{ $locale }}"
                 data-dir="{{ $dir }}"
                 data-initial-date="{{ $initialDate }}"></div>
        </div>
        <noscript>
            <div class="ec-card mt-4 p-5 text-sm text-slate-600 dark:text-slate-300">
                {{ __('Le calendrier interactif nécessite JavaScript. Consultez la liste détaillée depuis le menu.') }}
            </div>
        </noscript>
    </div>

    {{-- Panneau latéral : mini-calendrier + légende + aide --}}
    <div class="space-y-5">
        <div class="ec-card p-4">
            <div id="planning-mini"
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
                <p>{{ __('Cliquez sur une séance pour voir les détails ou la modifier.') }}</p>
            </div>
        </div>
    </div>
</div>

{{-- ══════════════════════════════════════════════════════════════════
     MODALE DE DÉTAIL D'UNE SÉANCE
═══════════════════════════════════════════════════════════════════ --}}
<div x-data="scheduleDetail()"
     x-on:schedule-detail.window="open($event.detail)"
     x-on:keydown.escape.window="close()"
     x-show="show" x-cloak
     class="fixed inset-0 z-50 flex items-center justify-center p-4"
     role="dialog" aria-modal="true">
    <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" x-on:click="close()" x-show="show"
         x-transition.opacity></div>

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
            <template x-if="event && event.conflict">
                <div class="mb-4 flex items-start gap-2 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-300">
                    <x-icon name="warning" class="mt-0.5 h-4 w-4 shrink-0" />
                    <span>{{ __('Conflit détecté : ce créneau chevauche une autre séance (professeur, salle ou classe).') }}</span>
                </div>
            </template>

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
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ __('Classe') }}</dt>
                    <dd class="mt-0.5 text-sm font-medium text-slate-800 dark:text-slate-100" x-text="event ? (event.className || '—') : ''"></dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ __('Professeur') }}</dt>
                    <dd class="mt-0.5 text-sm font-medium text-slate-800 dark:text-slate-100" x-text="event ? (event.teacherName || '—') : ''"></dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ __('Salle') }}</dt>
                    <dd class="mt-0.5 text-sm font-medium text-slate-800 dark:text-slate-100" x-text="event ? (event.roomName || '—') : ''"></dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ __('Centre') }}</dt>
                    <dd class="mt-0.5 text-sm font-medium text-slate-800 dark:text-slate-100" x-text="event ? (event.centerName || '—') : ''"></dd>
                </div>
                <div class="sm:col-span-2" x-show="event && event.description">
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ __('Description') }}</dt>
                    <dd class="mt-0.5 whitespace-pre-line text-sm text-slate-600 dark:text-slate-300" x-text="event ? event.description : ''"></dd>
                </div>
            </dl>
        </div>

        <div class="flex flex-wrap items-center justify-end gap-2 border-t border-slate-100 bg-slate-50/60 px-5 py-3 dark:border-slate-800 dark:bg-slate-800/30"
             x-show="event">
            <a :href="event ? event.showUrl : '#'" class="ec-btn-ghost ec-btn-sm">{{ __('Voir') }}</a>

            <template x-if="event && event.canEdit">
                <a :href="event.editUrl" class="ec-btn-secondary ec-btn-sm">
                    <x-icon name="pencil" class="h-4 w-4" /> {{ __('Modifier') }}
                </a>
            </template>

            <template x-if="event && event.canComplete">
                <button type="button" class="ec-btn-success ec-btn-sm" @click="submitStatus(event.statusUrl, 'completed')">
                    {{ __('Marquer terminée') }}
                </button>
            </template>

            <template x-if="event && event.canReactivate">
                <button type="button" class="ec-btn-secondary ec-btn-sm" @click="submitStatus(event.statusUrl, 'scheduled')">
                    {{ __('Reprogrammer') }}
                </button>
            </template>

            <template x-if="event && event.canCancel">
                <button type="button" class="ec-btn-danger ec-btn-sm" @click="submitStatus(event.statusUrl, 'cancelled')">
                    {{ __('Annuler la séance') }}
                </button>
            </template>
        </div>
    </div>
</div>

{{-- Données des séances (provenant de la base, injectées côté serveur) --}}
<script type="application/json" id="planning-events">@json($events)</script>
@endsection

{{-- ══════════════════════════════════════════════════════════════════
     STYLE DU PLANNING (thème clair/violet + pastels par type)
═══════════════════════════════════════════════════════════════════ --}}
@push('styles')
<style>
    /* Variables de thème FullCalendar */
    #planning-calendar, #planning-mini {
        --fc-border-color: rgb(226 232 240);
        --fc-today-bg-color: rgb(238 242 255 / 0.5);
        --fc-page-bg-color: transparent;
        --fc-neutral-bg-color: rgb(248 250 252);
        --fc-small-font-size: 0.8rem;
        font-feature-settings: 'cv02', 'cv03';
    }
    .dark #planning-calendar, .dark #planning-mini {
        --fc-border-color: rgb(30 41 59);
        --fc-today-bg-color: rgb(99 102 241 / 0.10);
        --fc-neutral-bg-color: rgb(15 23 42);
    }

    /* En-tête du calendrier */
    #planning-calendar .fc-toolbar { flex-wrap: wrap; gap: .75rem; margin-bottom: 1.25rem !important; }
    #planning-calendar .fc-toolbar-title { font-size: 1.35rem; font-weight: 800; letter-spacing: -.01em; color: #0f172a; }
    .dark #planning-calendar .fc-toolbar-title { color: #fff; }
    #planning-calendar .fc-scroller { scrollbar-width: thin; }
    #planning-calendar .fc-button {
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
    .dark #planning-calendar .fc-button { border-color: rgb(51 65 85); background: rgb(30 41 59); color: #cbd5e1; }
    #planning-calendar .fc-button:hover { background: rgb(248 250 252); }
    .dark #planning-calendar .fc-button:hover { background: rgb(51 65 85); }
    #planning-calendar .fc-button-primary:not(:disabled).fc-button-active,
    #planning-calendar .fc-button-primary:not(:disabled):disabled { }
    #planning-calendar .fc-button-active {
        background: #4f46e5 !important;
        color: #fff !important;
        border-color: #4f46e5 !important;
    }
    #planning-calendar .fc-button:disabled { opacity: .5; }
    #planning-calendar .fc-today-button { background: #eef2ff; border-color: #c7d2fe; color: #4338ca; }
    .dark #planning-calendar .fc-today-button { background: rgb(99 102 241 / .15); border-color: rgb(99 102 241 / .3); color: #a5b4fc; }

    /* Grille / jours */
    #planning-calendar .fc-col-header-cell-cushion, .dark #planning-calendar .fc-col-header-cell-cushion {
        color: #475569; font-weight: 700; font-size: .82rem; padding: .6rem .25rem; text-decoration: none;
    }
    .dark #planning-calendar .fc-col-header-cell-cushion { color: #94a3b8; }
    #planning-calendar .fc-timegrid-slot-label-cushion, .dark #planning-calendar .fc-timegrid-slot-label-cushion {
        color: #94a3b8; font-size: .72rem; text-decoration: none;
    }
    #planning-calendar .fc-daygrid-day-number, .dark #planning-calendar .fc-daygrid-day-number {
        color: #475569; font-weight: 600; text-decoration: none;
    }
    .dark #planning-calendar .fc-daygrid-day-number { color: #cbd5e1; }
    #planning-calendar .fc-list-day-cushion { background: rgb(248 250 252); }
    .dark #planning-calendar .fc-list-day-cushion { background: rgb(30 41 59); }
    #planning-calendar .fc-list-event:hover td { background: rgb(238 242 255 / .6); }
    .dark #planning-calendar .fc-list-event:hover td { background: rgb(99 102 241 / .1); }

    /* Séances (pastel + liseré coloré à gauche) */
    .fc-event.ec-fc-event {
        border: 1px solid transparent;
        border-inline-start-width: 4px;
        border-radius: .55rem;
        padding: .1rem .3rem;
        font-size: .78rem;
        cursor: pointer;
        box-shadow: 0 1px 2px rgb(15 23 42 / .05);
    }
    .fc-event.ec-fc-event .fc-event-title,
    .fc-event.ec-fc-event .fc-event-time { font-weight: 600; }
    #planning-calendar .fc-event-time,
    #planning-calendar .fc-list-event-time {
        direction: ltr;
        unicode-bidi: isolate;
        white-space: nowrap;
    }
    #planning-calendar .ec-fc-event .fc-event-main { color: inherit !important; }
    .ec-fc-event--course   { background: rgb(59 130 246 / .12);  border-inline-start-color: #3b82f6; color: #1e40af; }
    .ec-fc-event--workshop { background: rgb(16 185 129 / .12);  border-inline-start-color: #10b981; color: #065f46; }
    .ec-fc-event--exam     { background: rgb(245 158 11 / .14);  border-inline-start-color: #f59e0b; color: #92400e; }
    .ec-fc-event--other    { background: rgb(239 68 68 / .12);   border-inline-start-color: #ef4444; color: #991b1b; }
    .dark .ec-fc-event--course   { background: rgb(59 130 246 / .18);  color: #bfdbfe; }
    .dark .ec-fc-event--workshop { background: rgb(16 185 129 / .18);  color: #a7f3d0; }
    .dark .ec-fc-event--exam     { background: rgb(245 158 11 / .18);  color: #fde68a; }
    .dark .ec-fc-event--other    { background: rgb(239 68 68 / .18);   color: #fecaca; }
    .ec-fc-event--cancelled { opacity: .55; text-decoration: line-through; }
    .ec-fc-event--conflict  { outline: 2px solid #ef4444; outline-offset: -1px; }

    /* Mini-calendrier */
    #planning-mini .fc-toolbar-title { font-size: .95rem; font-weight: 700; }
    #planning-mini .fc-button { padding: .1rem .4rem !important; font-size: .72rem !important; }
    #planning-mini .fc-daygrid-day-frame { min-height: 2rem; }
    #planning-mini .fc-daygrid-day-number { font-size: .72rem; }
    #planning-mini .fc-col-header-cell-cushion {
        display: block;
        padding: .35rem .05rem;
        font-size: .62rem;
        font-weight: 700;
        line-height: 1.2;
        white-space: nowrap;
    }
</style>
@endpush

{{-- ══════════════════════════════════════════════════════════════════
     SCRIPT : FullCalendar (CDN) + modale de détail
═══════════════════════════════════════════════════════════════════ --}}
@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js" defer></script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/locales-all.global.min.js" defer></script>
<script>
// Modale de détail (exposée globalement pour l'écouteur d'événement Alpine).
// Définie de façon synchrone (hors DOMContentLoaded) afin d'être disponible
// avant le boot d'Alpine, qui est chargé en module différé.
window.scheduleDetail = function () {
        return {
            show: false,
            event: null,
            open(props) { this.event = props; this.show = true; },
            close() { this.show = false; this.event = null; },
            submitStatus(url, status) {
                if (!window.confirm(status === 'cancelled'
                    ? @js(__('Confirmer l\'annulation de cette séance ?'))
                    : @js(__('Confirmer cette action ?')))) {
                    return;
                }
                const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = url;
                const build = (name, value) => {
                    const i = document.createElement('input');
                    i.type = 'hidden'; i.name = name; i.value = value;
                    form.appendChild(i);
                };
                build('_token', token);
                build('_method', 'PATCH');
                build('status', status);
                document.body.appendChild(form);
                form.submit();
            },
        };
};
</script>
<script>
window.addEventListener('load', function () {
    if (typeof FullCalendar === 'undefined') { return; }

    const el = document.getElementById('planning-calendar');
    const miniEl = document.getElementById('planning-mini');
    if (!el) { return; }

    const events = JSON.parse(document.getElementById('planning-events').textContent || '[]');
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
            today: @js(__('Aujourd\'hui')),
            day: @js(__('Jour')),
            week: @js(__('Semaine')),
            month: @js(__('Mois')),
            list: @js(__('Liste')),
        },
        headerToolbar: isMobile
            ? { start: 'prev,next', center: 'title', end: 'today' }
            : { start: 'prev,next today', center: 'title', end: 'timeGridDay,timeGridWeek,dayGridMonth,listWeek' },
        views: {
            listWeek: { listDayFormat: { month: 'long', year: 'numeric', day: 'numeric' } },
        },
        eventClick: function (info) {
            info.jsEvent.preventDefault();
            const props = info.event.extendedProps || {};
            window.dispatchEvent(new CustomEvent('schedule-detail', {
                detail: { ...props, title: info.event.title },
            }));
        },
    });
    calendar.render();

    // Mini-calendrier : simple navigateur de dates synchronisé avec le principal
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
