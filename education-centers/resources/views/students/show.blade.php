@extends(!empty($isStandalone) ? 'layouts.student-dossier' : 'layouts.app')
@section('title', __('Détails de l\'étudiant') . ' — ' . $student->full_name)

@section('content')
@php
    $center = $student->center;
    $enrollments = $student->enrollments;
    $documents = $student->documents;
    $payments = $student->payments()->latest('payment_date')->get();

    // URL de retour au plan de classe avec préservation des filtres (professeur et classe)
    $hasReturnContext = !empty($returnTeacherId) || !empty($returnClassId);
    $backToClassPlanUrl = route('class-plan.index', array_filter([
        'teacher_id' => $returnTeacherId,
        'class_id'   => $returnClassId,
    ]));

    // Documents spécifiques CIN
    $cinFront = $documents->first(fn($d) => in_array($d->type, ['cin_front', 'cin_recto']) || str_contains(strtolower($d->title), 'recto'));
    $cinBack  = $documents->first(fn($d) => in_array($d->type, ['cin_back', 'cin_verso']) || str_contains(strtolower($d->title), 'verso'));
    $otherDocs = $documents->filter(fn($d) => $d->id !== $cinFront?->id && $d->id !== $cinBack?->id);

    // Photo : URL uniquement si le fichier existe réellement sur son disque
    // (photo privée servie par la route students.profile-photo). Sans photo
    // valide, l'avatar par défaut est affiché — jamais une image cassée.
    $photoUrl = $student->profilePhotoUrl();

    // Initiales si pas de photo
    $initials = mb_substr($student->first_name, 0, 1) . mb_substr($student->last_name, 0, 1);
@endphp

<div class="mx-auto max-w-7xl space-y-6 pb-12">

    {{-- BARRE DE NAVIGATION SUPÉRIEURE --}}
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div class="flex items-center gap-3.5 [direction:ltr]" dir="ltr">
            <x-ui.back-button :fallback="$hasReturnContext ? $backToClassPlanUrl : route('students.index')" />
            @if($hasReturnContext)
                <span class="rounded-md bg-brand-50 px-2.5 py-1 text-xs font-bold text-brand-600 dark:bg-brand-900/40 dark:text-brand-300">
                    {{ __('Filtres conservés') }}
                </span>
            @endif
        </div>

        <div class="flex items-center gap-3">
            @can('update', $student)
                <a href="{{ route('students.edit', $student) }}"
                   class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-xs transition hover:bg-slate-50 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300">
                    <svg class="h-4 w-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10"/>
                    </svg>
                    <span>{{ __('Modifier') }}</span>
                </a>
            @endcan
        </div>
    </div>

    {{-- SECTION A — EN-TÊTE DU DOSSIER : GRANDE CARTE D'IDENTITÉ --}}
    <div class="relative overflow-hidden rounded-3xl border border-slate-200/80 bg-white p-6 shadow-sm transition dark:border-slate-800 dark:bg-slate-900 md:p-8">
        {{-- Décoration de fond --}}
        <div class="pointer-events-none absolute -end-20 -top-20 h-64 w-64 rounded-full bg-brand-500/5 blur-3xl"></div>

        <div class="flex flex-col gap-6 sm:flex-row sm:items-center">
            {{-- Photo ou initiales --}}
            <div class="relative shrink-0">
                @if($photoUrl)
                    <img src="{{ $photoUrl }}"
                         alt="{{ $student->full_name }}"
                         class="h-28 w-28 rounded-2xl border-4 border-white object-cover shadow-md dark:border-slate-800 md:h-32 md:w-32">
                @else
                    <div class="flex h-28 w-28 items-center justify-center rounded-2xl bg-gradient-to-br from-brand-600 to-indigo-700 text-3xl font-extrabold text-white shadow-md md:h-32 md:w-32">
                        {{ strtoupper($initials) }}
                    </div>
                @endif
                <span class="absolute -bottom-1.5 -end-1.5 flex h-7 w-7 items-center justify-center rounded-full border-2 border-white bg-emerald-500 text-white shadow-xs dark:border-slate-900" title="{{ __('Statut actif') }}">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/>
                    </svg>
                </span>
            </div>

            {{-- Titre et informations principales --}}
            <div class="flex-1 space-y-3">
                <div class="flex flex-wrap items-center gap-3">
                    <span class="rounded-full bg-brand-50 px-3 py-1 text-xs font-bold uppercase tracking-wider text-brand-700 dark:bg-brand-950/60 dark:text-brand-300">
                        {{ __('Détails de l\'étudiant') }}
                    </span>
                    <span class="font-mono text-xs font-semibold text-slate-400 dark:text-slate-500">
                        N° {{ $student->student_number }}
                    </span>
                </div>

                <h1 class="text-2xl font-black tracking-tight text-slate-900 dark:text-white md:text-3xl">
                    {{ $student->full_name }}
                </h1>

                <div class="flex flex-wrap items-center gap-x-6 gap-y-2 text-sm text-slate-600 dark:text-slate-300">
                    {{-- Centre --}}
                    <div class="flex items-center gap-2">
                        <svg class="h-4 w-4 text-brand-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 21v-8.25M15.75 21v-8.25M8.25 21v-8.25M3 9l9-6 9 6m-1.5 12V10.333A2.25 2.25 0 0018.75 8.25H5.25A2.25 2.25 0 003 10.333V21"/>
                        </svg>
                        <span class="font-semibold text-slate-900 dark:text-white">{{ $center?->name ?? __('Non renseigné') }}</span>
                    </div>

                    {{-- Formations actuelles --}}
                    @if($enrollments->isNotEmpty())
                        @php $firstEnrollment = $enrollments->first(); @endphp
                        <div class="flex items-center gap-2">
                            <svg class="h-4 w-4 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.26 10.147a60.436 60.436 0 00-.491 6.347A48.627 48.627 0 0112 20.904a48.627 48.627 0 018.232-4.41 60.46 60.46 0 00-.491-6.347m-15.482 0a50.57 50.57 0 00-2.658-.813A59.905 59.905 0 0112 3.493a59.902 59.902 0 0110.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.697 50.697 0 0112 13.489a50.702 50.702 0 017.74-3.342M6.75 15a.75.75 0 100-1.5.75.75 0 000 1.5zm0 0v-3.675A55.378 55.378 0 0112 8.443m-7.007 11.55A5.981 5.981 0 006.75 15.75v-1.5"/>
                            </svg>
                            <span>{{ $firstEnrollment->formation?->general_formation_name }}</span>
                            @if($firstEnrollment->classRoom)
                                <span class="rounded-md bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-300">
                                    {{ $firstEnrollment->classRoom->perimeter_label }}
                                </span>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- CORPS DU DOSSIER : 3 CARTES AU MILIEU --}}
    <div class="grid gap-6 lg:grid-cols-3">

        {{-- SECTION B — INFORMATIONS PERSONNELLES --}}
        <div class="rounded-3xl border border-slate-200/80 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="mb-5 flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-sky-50 text-sky-600 dark:bg-sky-950/50 dark:text-sky-400">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/>
                    </svg>
                </div>
                <div>
                    <h2 class="text-base font-bold text-slate-900 dark:text-white">{{ __('Informations personnelles') }}</h2>
                    <p class="text-xs text-slate-400">{{ __('Identité & coordonnées') }}</p>
                </div>
            </div>

            <dl class="space-y-3.5 text-sm">
                <div class="flex justify-between border-b border-slate-100 pb-2 dark:border-slate-800/80">
                    <dt class="text-slate-500">{{ __('Prénom') }}</dt>
                    <dd class="font-semibold text-slate-800 dark:text-slate-200">{{ $student->first_name ?: __('Non renseigné') }}</dd>
                </div>
                <div class="flex justify-between border-b border-slate-100 pb-2 dark:border-slate-800/80">
                    <dt class="text-slate-500">{{ __('Nom') }}</dt>
                    <dd class="font-semibold text-slate-800 dark:text-slate-200">{{ $student->last_name ?: __('Non renseigné') }}</dd>
                </div>
                <div class="flex justify-between border-b border-slate-100 pb-2 dark:border-slate-800/80">
                    <dt class="text-slate-500">{{ __('Date de naissance') }}</dt>
                    <dd class="font-medium text-slate-800 dark:text-slate-200">
                        {{ $student->date_of_birth ? format_date($student->date_of_birth, 'd/m/Y') : __('Non renseigné') }}
                    </dd>
                </div>
                <div class="flex justify-between border-b border-slate-100 pb-2 dark:border-slate-800/80">
                    <dt class="text-slate-500">{{ __('Nationalité') }}</dt>
                    <dd class="font-medium text-slate-800 dark:text-slate-200">{{ $student->nationality ?: __('Non renseigné') }}</dd>
                </div>
                <div class="flex justify-between border-b border-slate-100 pb-2 dark:border-slate-800/80">
                    <dt class="text-slate-500">{{ __('Téléphone') }}</dt>
                    <dd class="font-medium text-slate-800 dark:text-slate-200">
                        @if($student->phone)
                            <a href="tel:{{ $student->phone }}" class="text-brand-600 hover:underline dark:text-brand-400"><bdi dir="ltr">{{ $student->phone }}</bdi></a>
                        @else
                            <span class="text-slate-400">{{ __('Non renseigné') }}</span>
                        @endif
                    </dd>
                </div>
                <div class="flex justify-between border-b border-slate-100 pb-2 dark:border-slate-800/80">
                    <dt class="text-slate-500">{{ __('WhatsApp') }}</dt>
                    <dd class="font-medium text-slate-800 dark:text-slate-200">
                        @if($student->whatsapp)
                            <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $student->whatsapp) }}" target="_blank" class="inline-flex items-center gap-1 text-emerald-600 hover:underline dark:text-emerald-400">
                                <bdi dir="ltr">{{ $student->whatsapp }}</bdi>
                            </a>
                        @else
                            <span class="text-slate-400">{{ __('Non renseigné') }}</span>
                        @endif
                    </dd>
                </div>
                <div class="flex justify-between border-b border-slate-100 pb-2 dark:border-slate-800/80">
                    <dt class="text-slate-500">{{ __('Contact d\'urgence') }}</dt>
                    <dd class="font-medium text-slate-800 dark:text-slate-200">
                        @if($student->emergency_contact_phone)
                            <bdi dir="ltr">{{ $student->emergency_contact_phone }}</bdi>
                            @if($student->emergency_contact_name)
                                <span class="text-xs text-slate-400">({{ $student->emergency_contact_name }})</span>
                            @endif
                        @else
                            <span class="text-slate-400">{{ __('Non renseigné') }}</span>
                        @endif
                    </dd>
                </div>
                <div class="flex justify-between pt-1">
                    <dt class="text-slate-500">{{ __('Ville & Adresse') }}</dt>
                    <dd class="text-end font-medium text-slate-800 dark:text-slate-200">
                        {{ $student->city ? $student->city . ($student->address ? ' — ' . $student->address : '') : ($student->address ?: __('Non renseigné')) }}
                    </dd>
                </div>
            </dl>
        </div>

        {{-- SECTION D — INFORMATIONS PÉDAGOGIQUES --}}
        <div class="rounded-3xl border border-slate-200/80 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="mb-5 flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-purple-50 text-purple-600 dark:bg-purple-950/50 dark:text-purple-400">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25"/>
                    </svg>
                </div>
                <div>
                    <h2 class="text-base font-bold text-slate-900 dark:text-white">{{ __('Informations pédagogiques') }}</h2>
                    <p class="text-xs text-slate-400">{{ __('Inscriptions & classes') }}</p>
                </div>
            </div>

            @if($enrollments->isEmpty())
                <div class="flex flex-col items-center justify-center py-10 text-center">
                    <svg class="h-10 w-10 text-slate-300 dark:text-slate-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/>
                    </svg>
                    <p class="mt-2 text-sm font-semibold text-slate-500">{{ __('Aucune inscription active') }}</p>
                </div>
            @else
                <div class="space-y-4">
                    @foreach($enrollments as $enrollment)
                        <div class="rounded-2xl border border-slate-100 bg-slate-50/70 p-4 dark:border-slate-800 dark:bg-slate-800/40">
                            <div class="flex items-center justify-between gap-2">
                                <h3 class="text-sm font-bold text-slate-900 dark:text-white">
                                    {{ $enrollment->formation?->general_formation_name ?? __('Formation inconnue') }}
                                </h3>
                                <span class="rounded-md bg-emerald-100 px-2 py-0.5 text-xs font-bold text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300">
                                    {{ ucfirst($enrollment->status) }}
                                </span>
                            </div>

                            <dl class="mt-3 space-y-1.5 text-xs text-slate-600 dark:text-slate-300">
                                <div class="flex justify-between">
                                    <dt class="text-slate-400">{{ __('Formation générale') }} :</dt>
                                    <dd class="font-medium capitalize">{{ $enrollment->formation?->domain ?: '—' }}</dd>
                                </div>
                                <div class="flex justify-between">
                                    <dt class="text-slate-400">{{ __('Classe') }} :</dt>
                                    <dd class="font-semibold text-slate-800 dark:text-slate-200">
                                        {{ $enrollment->classRoom?->perimeter_label ?? __('Non affectée') }}
                                    </dd>
                                </div>
                                <div class="flex justify-between">
                                    <dt class="text-slate-400">{{ __('Professeur') }} :</dt>
                                    <dd class="font-medium text-brand-600 dark:text-brand-400">
                                        {{ $enrollment->classRoom?->teachers?->first()?->user?->name ?? __('Non assigné') }}
                                    </dd>
                                </div>
                                <div class="flex justify-between">
                                    <dt class="text-slate-400">{{ __('Date d\'inscription') }} :</dt>
                                    <dd>{{ $enrollment->application_date ? format_date($enrollment->application_date, 'd/m/Y') : '—' }}</dd>
                                </div>
                            </dl>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- SECTION C — DOCUMENTS --}}
        <div class="rounded-3xl border border-slate-200/80 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="mb-5 flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-50 text-amber-600 dark:bg-amber-950/50 dark:text-amber-400">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/>
                    </svg>
                </div>
                <div>
                    <h2 class="text-base font-bold text-slate-900 dark:text-white">{{ __('Documents associés') }}</h2>
                    <p class="text-xs text-slate-400">{{ __('Pièces justificatives & fichiers') }}</p>
                </div>
            </div>

            <div class="space-y-3">
                {{-- Carte CIN Recto / Verso --}}
                <div class="grid grid-cols-2 gap-2">
                    <div class="rounded-2xl border border-slate-100 bg-slate-50 p-3 text-center dark:border-slate-800 dark:bg-slate-800/40">
                        <p class="text-xs font-bold text-slate-700 dark:text-slate-300">{{ __('CIN Recto') }}</p>
                        @if($cinFront)
                            <a href="{{ route('documents.download', $cinFront) }}" class="mt-2 inline-flex items-center gap-1 text-xs font-semibold text-brand-600 hover:underline">
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                                {{ __('Télécharger') }}
                            </a>
                        @else
                            <span class="mt-2 block text-xs text-slate-400">{{ __('Non fourni') }}</span>
                        @endif
                    </div>
                    <div class="rounded-2xl border border-slate-100 bg-slate-50 p-3 text-center dark:border-slate-800 dark:bg-slate-800/40">
                        <p class="text-xs font-bold text-slate-700 dark:text-slate-300">{{ __('CIN Verso') }}</p>
                        @if($cinBack)
                            <a href="{{ route('documents.download', $cinBack) }}" class="mt-2 inline-flex items-center gap-1 text-xs font-semibold text-brand-600 hover:underline">
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                                {{ __('Télécharger') }}
                            </a>
                        @else
                            <span class="mt-2 block text-xs text-slate-400">{{ __('Non fourni') }}</span>
                        @endif
                    </div>
                </div>

                {{-- Autres documents --}}
                @if($otherDocs->isNotEmpty())
                    <div class="space-y-2 pt-2">
                        <p class="text-xs font-bold uppercase tracking-wider text-slate-400">{{ __('Autres documents') }}</p>
                        @foreach($otherDocs as $doc)
                            <div class="flex items-center justify-between rounded-xl border border-slate-100 bg-slate-50/50 p-2.5 text-xs dark:border-slate-800 dark:bg-slate-800/30">
                                <span class="truncate font-medium text-slate-700 dark:text-slate-300" title="{{ $doc->title }}">{{ $doc->title }}</span>
                                <a href="{{ route('documents.download', $doc) }}" class="shrink-0 font-bold text-brand-600 hover:underline dark:text-brand-400">
                                    {{ __('Ouvrir') }}
                                </a>
                            </div>
                        @endforeach
                    </div>
                @elseif(!$cinFront && !$cinBack)
                    <p class="py-6 text-center text-xs text-slate-400">{{ __('Aucun document archivé pour cet élève.') }}</p>
                @endif
            </div>
        </div>

    </div>

    {{-- SECTION E — INFORMATIONS FINANCIÈRES & HISTORIQUE DES PAIEMENTS --}}
    <div class="rounded-3xl border border-slate-200/80 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900 md:p-8">
        <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-600 dark:bg-emerald-950/50 dark:text-emerald-400">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div>
                    <h2 class="text-xl font-bold text-slate-900 dark:text-white">{{ __('Situation financière') }}</h2>
                    <p class="text-xs text-slate-400">{{ __('Données financières consolidées (MAD / DH)') }}</p>
                </div>
            </div>

            <div class="flex items-center gap-2">
                @php
                    $finStatus = $financeSituation['status'] ?? 'pending';
                    $statusBadge = match($finStatus) {
                        'settled', 'paid' => ['bg' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300', 'label' => __('Soldé / Payé')],
                        'partial'         => ['bg' => 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300', 'label' => __('Partiellement payé (Avance)')],
                        default           => ['bg' => 'bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-300', 'label' => __('En attente de paiement')],
                    };
                @endphp
                <span class="rounded-full px-4 py-1.5 text-xs font-bold {{ $statusBadge['bg'] }}">
                    {{ $statusBadge['label'] }}
                </span>
            </div>
        </div>

        {{-- 3 Compteurs financiers --}}
        <div class="grid gap-4 sm:grid-cols-3">
            <div class="rounded-2xl border border-slate-100 bg-slate-50/80 p-5 dark:border-slate-800 dark:bg-slate-800/40">
                <p class="text-xs font-bold uppercase tracking-wider text-slate-400">{{ __('Montant total attendu') }}</p>
                <p class="mt-2 text-2xl font-black text-slate-900 dark:text-white">
                    {{ number_format($financeSituation['total_expected'] ?? 0, 2, ',', ' ') }} <span class="text-sm font-semibold text-slate-500">DH</span>
                </p>
            </div>
            <div class="rounded-2xl border border-emerald-100 bg-emerald-50/50 p-5 dark:border-emerald-900/40 dark:bg-emerald-950/20">
                <p class="text-xs font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400">{{ __('Montant payé (Avances incluses)') }}</p>
                <p class="mt-2 text-2xl font-black text-emerald-600 dark:text-emerald-400">
                    {{ number_format($financeSituation['total_paid'] ?? 0, 2, ',', ' ') }} <span class="text-sm font-semibold text-emerald-600/70">DH</span>
                </p>
            </div>
            <div class="rounded-2xl border border-rose-100 bg-rose-50/50 p-5 dark:border-rose-900/40 dark:bg-rose-950/20">
                <p class="text-xs font-bold uppercase tracking-wider text-rose-600 dark:text-rose-400">{{ __('Reste à payer') }}</p>
                <p class="mt-2 text-2xl font-black text-rose-600 dark:text-rose-400">
                    {{ number_format($financeSituation['total_remaining'] ?? 0, 2, ',', ' ') }} <span class="text-sm font-semibold text-rose-600/70">DH</span>
                </p>
            </div>
        </div>

        {{-- Historique des paiements --}}
        <div class="mt-8">
            <h3 class="mb-4 text-sm font-bold text-slate-900 dark:text-white">{{ __('Historique des transactions réelles') }}</h3>
            @if($payments->isEmpty())
                <p class="py-6 text-center text-sm text-slate-400">{{ __('Aucun paiement enregistré pour le moment.') }}</p>
            @else
                <div class="overflow-x-auto rounded-2xl border border-slate-100 dark:border-slate-800">
                    <table class="w-full text-start text-sm">
                        <thead class="bg-slate-50 text-xs font-bold uppercase tracking-wider text-slate-500 dark:bg-slate-800/60 dark:text-slate-400">
                            <tr>
                                <th class="px-4 py-3">{{ __('Référence') }}</th>
                                <th class="px-4 py-3">{{ __('Date') }}</th>
                                <th class="px-4 py-3">{{ __('Méthode') }}</th>
                                <th class="px-4 py-3">{{ __('Montant') }}</th>
                                <th class="px-4 py-3">{{ __('Statut') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @foreach($payments as $payment)
                                <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30">
                                    <td class="px-4 py-3 font-mono text-xs font-semibold text-slate-700 dark:text-slate-300">{{ $payment->reference ?: '—' }}</td>
                                    <td class="px-4 py-3 text-slate-600 dark:text-slate-400">{{ $payment->payment_date ? format_date($payment->payment_date, 'd/m/Y') : '—' }}</td>
                                    <td class="px-4 py-3 capitalize text-slate-600 dark:text-slate-400">{{ $payment->method ?: 'Cash' }}</td>
                                    <td class="px-4 py-3 font-bold text-slate-900 dark:text-white">{{ number_format($payment->amount, 2, ',', ' ') }} DH</td>
                                    <td class="px-4 py-3">
                                        <span class="rounded-md bg-emerald-100 px-2 py-0.5 text-xs font-bold text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300">
                                            {{ ucfirst($payment->status) }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

</div>
@endsection
