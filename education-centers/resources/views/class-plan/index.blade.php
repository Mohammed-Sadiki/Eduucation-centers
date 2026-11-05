@extends('layouts.portal')

@section('title', __('Plan de classe') . ' — ' . config('app.name'))
@section('description', __('Visualisez le plan de salle de vos classes par professeur.'))

@section('content')

{{-- ─────────────────────────────────────────────────────────
     EN-TÊTE
──────────────────────────────────────────────────────────── --}}
<div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
    <div class="flex items-center gap-3.5 [direction:ltr]" dir="ltr">
        <x-ui.back-button id="class-plan-back-domaines" :fallback="auth()->user()->isSuperAdmin() ? route('super-admin.domaines') : route('center-admin.domaines')" />
        <div class="flex items-center gap-3 {{ current_dir() === 'rtl' ? '[direction:rtl] text-end' : '' }}">
            <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-brand-500 to-brand-700 text-white shadow-lg shadow-brand-600/30">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 6a2 2 0 012-2h12a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 14a2 2 0 012-2h12a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2z"/>
                </svg>
            </span>
            <div>
                <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 sm:text-3xl dark:text-white">
                    {{ __('Plan de classe') }}
                </h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    {{ __('Représentation visuelle d\'une salle de classe avec le professeur et ses élèves.') }}
                </p>
            </div>
        </div>
    </div>
</div>

{{-- ─────────────────────────────────────────────────────────
     FORMULAIRE DE SÉLECTION (Alpine.js)
──────────────────────────────────────────────────────────── --}}
<div
    x-data="classPlanApp()"
    x-init="init()"
    class="space-y-8"
>
    {{-- CARTE : Sélection du professeur + classe --}}
    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900 p-6 sm:p-8">
        <div class="mb-6">
            <h2 class="text-xl font-bold text-slate-900 dark:text-white flex items-center gap-2">
                <svg class="h-5 w-5 text-brand-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/>
                </svg>
                {{ __('Sélection du professeur') }}
            </h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                {{ __('Choisissez un professeur pour visualiser son plan de classe.') }}
            </p>
        </div>

        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
            {{-- Select Professeur --}}
            <div>
                <label for="select-teacher" class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">
                    {{ __('Choisir un professeur') }} <span class="text-red-500">*</span>
                </label>
                <div class="relative">
                    <select
                        id="select-teacher"
                        x-model="selectedTeacherId"
                        x-on:change="onTeacherChange()"
                        class="block w-full rounded-xl border border-slate-200 bg-white py-3 pe-10 ps-4 text-sm font-medium text-slate-900 shadow-sm transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-slate-700 dark:bg-slate-800 dark:text-white appearance-none cursor-pointer"
                    >
                        <option value="">— {{ __('Sélectionner un professeur') }} —</option>
                        @foreach($teachers as $teacher)
                            <option value="{{ $teacher->id }}" @selected($selectedTeacher?->id === $teacher->id)>
                                {{ $teacher->last_name }} {{ $teacher->first_name }}
                                @if($teacher->center) · {{ $teacher->center->name }} @endif
                            </option>
                        @endforeach
                    </select>
                    <div class="pointer-events-none absolute inset-y-0 end-0 flex items-center pe-3">
                        <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </div>
                </div>
            </div>

            {{-- Select Classe (visible si plusieurs classes) --}}
            <div x-show="classes.length > 1" x-transition>
                <label for="select-class" class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">
                    {{ __('Choisir une classe') }} <span class="text-red-500">*</span>
                </label>
                <div class="relative">
                    <select
                        id="select-class"
                        x-model="selectedClassId"
                        x-on:change="onClassChange()"
                        class="block w-full rounded-xl border border-slate-200 bg-white py-3 pe-10 ps-4 text-sm font-medium text-slate-900 shadow-sm transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-slate-700 dark:bg-slate-800 dark:text-white appearance-none cursor-pointer"
                    >
                        <option value="">— {{ __('Sélectionner une classe') }} —</option>
                        <template x-for="cls in classes" :key="cls.id">
                            <option :value="cls.id" x-text="cls.formation ? cls.name + ' — ' + cls.formation : cls.name"></option>
                        </template>
                    </select>
                    <div class="pointer-events-none absolute inset-y-0 end-0 flex items-center pe-3">
                        <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </div>
                </div>
            </div>

            {{-- Message : aucune classe pour ce prof --}}
            <div x-show="selectedTeacherId && classes.length === 0 && !loadingClasses"
                 x-transition
                 class="sm:col-span-2"
            >
                <div class="flex items-center gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-amber-800 dark:border-amber-700/50 dark:bg-amber-900/20 dark:text-amber-300">
                    <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/>
                    </svg>
                    <p class="text-sm font-medium">{{ __('Aucune classe n\'est associée à ce professeur.') }}</p>
                </div>
            </div>

            {{-- Spinner de chargement --}}
            <div x-show="loadingClasses || loadingStudents" x-transition class="sm:col-span-2 flex items-center gap-2 text-brand-600">
                <svg class="h-5 w-5 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
                <span class="text-sm font-medium" x-text="loadingClasses ? '{{ __('Chargement des classes...') }}' : '{{ __('Chargement des élèves...') }}'"></span>
            </div>
        </div>
    </div>

    {{-- ─────────────────────────────────────────────────────────
         PLAN DE CLASSE
    ──────────────────────────────────────────────────────────── --}}
    <div x-show="selectedClassId && !loadingStudents" x-transition>

        {{-- INFO BAR --}}
        <div class="mb-4 flex flex-wrap items-center gap-3" x-show="teacherName">
            <div class="flex items-center gap-2 rounded-full bg-brand-50 px-4 py-1.5 text-sm font-semibold text-brand-700 dark:bg-brand-900/30 dark:text-brand-300">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
                </svg>
                <span x-text="className"></span>
            </div>
            <div class="flex items-center gap-2 rounded-full bg-slate-100 px-4 py-1.5 text-sm font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z"/>
                </svg>
                <span x-text="students.length + ' {{ __('élève(s)') }}'"></span>
            </div>
        </div>

        {{-- LA SALLE DE CLASSE --}}
        <div class="classroom-container relative w-full overflow-hidden rounded-2xl border-2 border-slate-200 bg-gradient-to-b from-slate-50 to-white p-6 sm:p-10 dark:border-slate-700 dark:from-slate-900 dark:to-slate-950 shadow-lg">

            {{-- Ligne de tableau --}}
            <div class="absolute top-0 inset-x-0 h-1.5 rounded-t-2xl bg-gradient-to-r from-brand-500 via-brand-600 to-brand-700"></div>

            {{-- ZONE PROFESSEUR --}}
            <div class="mb-10 flex flex-col items-center">
                <div class="relative">
                    {{-- Badge PROFESSEUR --}}
                    <div class="mb-1 text-center">
                        <span class="inline-block rounded-full bg-brand-100 px-3 py-0.5 text-xs font-bold uppercase tracking-widest text-brand-700 dark:bg-brand-900/50 dark:text-brand-300">
                            {{ __('PROFESSEUR') }}
                        </span>
                    </div>

                    {{-- Bureau du professeur --}}
                    <div class="teacher-desk relative flex flex-col items-center justify-center rounded-2xl border-2 border-brand-200 bg-gradient-to-br from-brand-50 to-white px-10 py-5 shadow-md dark:border-brand-700/50 dark:from-brand-900/30 dark:to-slate-900 min-w-[200px] max-w-xs sm:max-w-sm text-center transition hover:shadow-lg hover:border-brand-300 dark:hover:border-brand-600">

                        {{-- Avatar professeur --}}
                        <div class="mb-3 flex h-14 w-14 items-center justify-center rounded-full bg-brand-600 text-white font-bold text-xl shadow-md shadow-brand-500/30" x-text="teacherInitials"></div>

                        {{-- Nom du professeur --}}
                        <p class="text-base font-bold text-slate-900 dark:text-white leading-tight" x-text="teacherName"></p>
                        <p class="mt-0.5 text-xs text-brand-600 dark:text-brand-400 font-medium" x-text="formationName || '{{ __('Professeur') }}'"></p>
                    </div>

                    {{-- Flèche vers les élèves --}}
                    <div class="mt-4 flex justify-center">
                        <div class="flex flex-col items-center gap-1">
                            <div class="h-6 w-0.5 bg-slate-300 dark:bg-slate-600"></div>
                            <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Séparateur allée --}}
            <div class="relative mb-10">
                <div class="absolute inset-0 flex items-center">
                    <div class="w-full border-t border-dashed border-slate-300 dark:border-slate-600"></div>
                </div>
                <div class="relative flex justify-center">
                    <span class="bg-white px-3 text-xs text-slate-400 dark:bg-slate-950 dark:text-slate-600 font-medium uppercase tracking-widest">
                        {{ __('Salle de classe') }}
                    </span>
                </div>
            </div>

            {{-- ZONE ÉLÈVES --}}
            <div x-show="students.length > 0">
                {{-- Grille des élèves : 4 par ligne desktop, 3 tablette, 2 mobile --}}
                <div class="students-grid grid grid-cols-2 gap-x-6 gap-y-8 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6">
                    <template x-for="(student, index) in students" :key="student.id">
                        <a :href="'/students/' + student.id + '?return_teacher_id=' + selectedTeacherId + '&return_class_id=' + selectedClassId"
                           class="student-seat flex flex-col items-center gap-2 group cursor-pointer transition transform hover:-translate-y-1 focus:outline-none"
                           x-data="{ hovered: false }"
                           x-on:mouseenter="hovered = true"
                           x-on:mouseleave="hovered = false"
                           :title="'{{ __('Consulter le dossier de') }} ' + student.full_name"
                        >
                            {{-- Numéro de siège --}}
                            <span class="text-2xs font-bold text-slate-300 dark:text-slate-600 mb-0.5 group-hover:text-brand-500" x-text="index + 1"></span>

                            {{-- Cercle photo --}}
                            <div class="relative">
                                <div
                                    class="h-16 w-16 rounded-full border-2 border-slate-200 bg-slate-100 overflow-hidden transition-all duration-200 shadow-sm group-hover:border-brand-500 group-hover:shadow-md group-hover:shadow-brand-500/20 dark:border-slate-700 dark:bg-slate-800 dark:group-hover:border-brand-400 flex items-center justify-center"
                                    :class="{ 'scale-105 border-brand-500': hovered }"
                                >
                                    <template x-if="student.profile_photo_url">
                                        <img
                                            :src="student.profile_photo_url"
                                            :alt="student.full_name"
                                            class="h-full w-full object-cover"
                                            onerror="this.style.display='none'; this.nextElementSibling.style.display='flex'"
                                        />
                                    </template>
                                    {{-- Icône par défaut si pas de photo --}}
                                    <div :class="student.profile_photo_url ? 'hidden' : 'flex'" class="flex h-full w-full items-center justify-center">
                                        <svg class="h-8 w-8 text-slate-400 dark:text-slate-500 group-hover:text-brand-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/>
                                        </svg>
                                    </div>
                                </div>
                                {{-- Badge statut actif --}}
                                <span class="absolute bottom-0.5 end-0.5 h-3.5 w-3.5 rounded-full border-2 border-white bg-emerald-400 dark:border-slate-900"></span>
                            </div>

                            {{-- Nom --}}
                            <div class="text-center max-w-[90px]">
                                <p class="text-xs font-semibold text-slate-800 dark:text-slate-200 leading-tight truncate group-hover:text-brand-600 dark:group-hover:text-brand-400" x-text="student.first_name"></p>
                                <p class="text-xs text-slate-500 dark:text-slate-400 leading-tight truncate" x-text="student.last_name"></p>
                            </div>

                            {{-- Tooltip : nom complet + action --}}
                            <div
                                x-show="hovered"
                                x-transition:enter="transition ease-out duration-150"
                                x-transition:enter-start="opacity-0 translate-y-1"
                                x-transition:enter-end="opacity-100 translate-y-0"
                                class="absolute z-10 mt-2 rounded-lg bg-slate-900 px-3 py-1.5 text-xs font-medium text-white shadow-lg pointer-events-none"
                                x-text="student.full_name + ' — {{ __('Voir détails') }}'"
                            ></div>
                        </a>
                    </template>
                </div>
            </div>

            {{-- Message classe vide --}}
            <div x-show="students.length === 0 && selectedClassId && !loadingStudents" x-transition class="flex flex-col items-center justify-center py-16 text-center">
                <div class="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-slate-100 dark:bg-slate-800">
                    <svg class="h-8 w-8 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"/>
                    </svg>
                </div>
                <p class="text-base font-semibold text-slate-600 dark:text-slate-400">{{ __('Aucun élève inscrit dans cette classe.') }}</p>
                <p class="mt-1 text-sm text-slate-400 dark:text-slate-500">{{ __('Les élèves apparaîtront ici une fois inscrits et acceptés dans la classe.') }}</p>
            </div>

        </div>{{-- .classroom-container --}}

    </div>{{-- plan visible --}}

    {{-- Placeholder initial --}}
    <div x-show="!selectedTeacherId" x-transition class="flex flex-col items-center justify-center rounded-2xl border-2 border-dashed border-slate-200 bg-white py-20 text-center dark:border-slate-700 dark:bg-slate-900">
        <div class="mb-4 flex h-20 w-20 items-center justify-center rounded-full bg-brand-50 dark:bg-brand-900/20">
            <svg class="h-10 w-10 text-brand-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6a2 2 0 012-2h12a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6z"/>
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 14a2 2 0 012-2h12a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2z"/>
            </svg>
        </div>
        <h3 class="text-lg font-bold text-slate-700 dark:text-slate-300">{{ __('Sélectionnez un professeur') }}</h3>
        <p class="mt-1 max-w-xs text-sm text-slate-400 dark:text-slate-500">
            {{ __('Choisissez un professeur dans la liste pour afficher le plan de sa classe.') }}
        </p>
    </div>

</div>{{-- x-data --}}

@endsection

@push('scripts')
@php
    $jsClasses = $classes->map(fn($c) => [
        'id' => $c->id,
        'name' => $c->group_label,
        'code' => $c->code,
        'formation' => $c->formation?->general_formation_name,
    ])->values();
    // Photo : la même URL que celle de « Détails de l'étudiant ». Le chemin est
    // stocké sur le disque privé, donc asset('storage/…') (disque public) produit
    // une image cassée. profilePhotoUrl() vérifie l'existence réelle du fichier et
    // sert la photo par la route autorisée students.profile-photo ; il renvoie null
    // sans photo ni fichier introuvable → avatar par défaut, jamais d'image cassée.
    $jsStudents = $students->map(fn($s) => [
        'id' => $s->id,
        'full_name' => trim($s->first_name . ' ' . $s->last_name),
        'first_name' => $s->first_name,
        'last_name' => $s->last_name,
        'profile_photo_url' => $s->profilePhotoUrl(),
    ])->values();
    $jsTeacherName = addslashes(($selectedTeacher?->last_name ?? '') . ' ' . ($selectedTeacher?->first_name ?? ''));
    $jsTeacherInitials = strtoupper(
        substr($selectedTeacher?->last_name ?? '', 0, 1) .
        substr($selectedTeacher?->first_name ?? '', 0, 1)
    );
    $jsFormationName = addslashes($selectedClass?->formation?->general_formation_name ?? '');
    $jsClassName = addslashes($selectedClass?->group_label ?? '');
@endphp
<script>
function classPlanApp() {
    return {
        // State
        selectedTeacherId: '{{ $selectedTeacher?->id ?? '' }}',
        selectedClassId:   '{{ $selectedClass?->id ?? '' }}',
        classes: @json($jsClasses),
        students: @json($jsStudents),

        // Meta
        teacherName: '{{ $jsTeacherName }}',
        teacherInitials: '{{ $jsTeacherInitials }}',
        formationName: '{{ $jsFormationName }}',
        className: '{{ $jsClassName }}',

        // Loading flags
        loadingClasses: false,
        loadingStudents: false,

        init() {
            // Si déjà un prof sélectionné (après reload), on charge les classes
            if (this.selectedTeacherId && this.classes.length === 0) {
                this.loadClasses(this.selectedTeacherId);
            }
            // Si déjà une classe, on charge les élèves
            if (this.selectedClassId && this.students.length === 0) {
                this.loadStudents(this.selectedClassId);
            }
        },

        onTeacherChange() {
            this.selectedClassId = '';
            this.classes = [];
            this.students = [];
            this.teacherName = '';
            this.teacherInitials = '';
            this.formationName = '';
            this.className = '';

            if (!this.selectedTeacherId) return;

            // Trouver le nom dans l'option selectionnée
            const sel = document.getElementById('select-teacher');
            if (sel) {
                const opt = sel.options[sel.selectedIndex];
                this.teacherName = opt ? opt.textContent.trim().split('·')[0].trim() : '';
                this.teacherInitials = this.teacherName.split(' ').map(w => w[0]).join('').toUpperCase().slice(0, 2);
            }

            this.loadClasses(this.selectedTeacherId);
        },

        onClassChange() {
            this.students = [];
            this.formationName = '';
            this.className = '';

            if (!this.selectedClassId) return;

            const cls = this.classes.find(c => String(c.id) === String(this.selectedClassId));
            if (cls) {
                this.className = cls.name;
                this.formationName = cls.formation || '';
            }

            this.loadStudents(this.selectedClassId);
        },

        async loadClasses(teacherId) {
            this.loadingClasses = true;
            try {
                const res = await fetch(`/api/class-plan/teachers/${teacherId}/classes`, {
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' }
                });
                const data = await res.json();
                this.classes = data.classes || [];

                // Auto-select si une seule classe
                if (this.classes.length === 1) {
                    this.selectedClassId = this.classes[0].id;
                    this.className = this.classes[0].name;
                    this.formationName = this.classes[0].formation || '';
                    this.loadStudents(this.selectedClassId);
                }
            } catch (e) {
                console.error('Erreur chargement classes:', e);
                this.classes = [];
            } finally {
                this.loadingClasses = false;
            }
        },

        async loadStudents(classId) {
            this.loadingStudents = true;
            this.students = [];
            try {
                const res = await fetch(`/api/class-plan/classes/${classId}/students`, {
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' }
                });
                const data = await res.json();
                this.students = data.students || [];
            } catch (e) {
                console.error('Erreur chargement élèves:', e);
                this.students = [];
            } finally {
                this.loadingStudents = false;
            }
        },
    };
}
</script>
@endpush
