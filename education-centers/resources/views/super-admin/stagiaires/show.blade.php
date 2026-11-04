@extends('layouts.app')

@section('title', __('Dossier Stagiaire') . ' — ' . $stagiaire->first_name . ' ' . $stagiaire->last_name)

@section('content')
<div class="space-y-6">
    {{-- Fil d'Ariane & Actions --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <nav class="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400 mb-1">
                <a href="{{ route('super-admin.domaines') }}" class="hover:text-slate-800 dark:hover:text-slate-200">{{ __('Portail') }}</a>
                <span>/</span>
                <a href="{{ route('stagiaires.index') }}" class="hover:text-slate-800 dark:hover:text-slate-200">{{ __('Stagiaires') }}</a>
                <span>/</span>
                <a href="{{ route('stagiaires.index') }}" class="hover:text-slate-800 dark:hover:text-slate-200">{{ __('Liste') }}</a>
                <span>/</span>
                <span class="text-slate-700 font-semibold dark:text-slate-300">{{ __('Dossier') }}</span>
            </nav>
            <div class="flex items-center gap-3">
                @if($stagiaire->profile_photo_path)
                    <img src="{{ route('stagiaires.profile-photo', $stagiaire) }}"
                         alt="{{ __('Photo de profil') }}"
                         class="h-14 w-14 shrink-0 rounded-full border-2 border-slate-200 object-cover dark:border-slate-700">
                @endif
                <h1 class="text-2xl font-black tracking-tight text-slate-900 dark:text-white">
                    {{ $stagiaire->first_name }} {{ $stagiaire->last_name }}
                </h1>
                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold
                    @if($stagiaire->status === 'en_cours') bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300
                    @elseif($stagiaire->status === 'termine') bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300
                    @elseif($stagiaire->status === 'nouveau') bg-purple-100 text-purple-800 dark:bg-purple-900/40 dark:text-purple-300
                    @elseif($stagiaire->status === 'annule') bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300
                    @else bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300
                    @endif">
                    {{ $stagiaire->status_label }}
                </span>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('stagiaires.index') }}"
               class="inline-flex items-center gap-1.5 rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-xs font-semibold text-slate-700 shadow-2xs hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800 transition">
                <x-icon name="chevron-left" class="h-4 w-4" />
                <span>{{ __('Retour à la liste') }}</span>
            </a>
            <a href="{{ route('stagiaires.edit', $stagiaire) }}"
               class="inline-flex items-center gap-1.5 rounded-xl bg-purple-600 px-4 py-2 text-xs font-semibold text-white shadow-sm hover:bg-purple-700 transition">
                <x-icon name="pencil" class="h-4 w-4" />
                <span>{{ __('Modifier le dossier') }}</span>
            </a>
            <form action="{{ route('stagiaires.destroy', $stagiaire) }}" method="POST"
                  onsubmit="return confirm('{{ __('Confirmez-vous la suppression de ce stagiaire et de tous ses documents ?') }}');"
                  class="inline-block">
                @csrf
                @method('DELETE')
                <button type="submit"
                        class="inline-flex items-center gap-1.5 rounded-xl border border-rose-300 bg-rose-50 px-3.5 py-2 text-xs font-semibold text-rose-700 shadow-2xs hover:bg-rose-100 dark:border-rose-900/60 dark:bg-rose-950/40 dark:text-rose-300 dark:hover:bg-rose-900/50 transition">
                    <x-icon name="trash" class="h-4 w-4" />
                    <span>{{ __('Supprimer') }}</span>
                </button>
            </form>
        </div>
    </div>

    {{-- Highlight Banner : Prix du Stage & Infos Clés --}}
    <div class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-purple-700 via-indigo-700 to-purple-900 p-6 text-white shadow-xl">
        <div class="relative z-10 flex flex-col md:flex-row md:items-center md:justify-between gap-6">
            <div class="space-y-1">
                <div class="inline-flex items-center gap-2 rounded-full bg-white/15 px-3 py-1 text-xs font-semibold text-white backdrop-blur-xs">
                    <span>{{ __('🧑‍💼 Dossier Stagiaire Indépendant') }}</span>
                    <span class="opacity-60">•</span>
                    <span>{{ __('Centre :') }} {{ $stagiaire->center?->name ?? __('Non assigné') }}</span>
                </div>
                <h2 class="text-xl md:text-2xl font-black">
                    {{ $stagiaire->stage_type ? $stagiaire->stage_type . ' — ' : '' }}{{ $stagiaire->first_name }} {{ $stagiaire->last_name }}
                </h2>
                <p class="text-xs md:text-sm text-purple-100">
                    {{ __('Période du stage :') }}
                    <span class="font-semibold text-white">
                        {{ $stagiaire->stage_start_date ? format_date($stagiaire->stage_start_date, 'd/m/Y') : __('Non définie') }}
                    </span>
                    {{ __('au') }}
                    <span class="font-semibold text-white">
                        {{ $stagiaire->stage_end_date ? format_date($stagiaire->stage_end_date, 'd/m/Y') : __('Non définie') }}
                    </span>
                </p>
            </div>

            {{-- Carte PRIX DU STAGE --}}
            <div class="flex flex-col items-start md:items-end rounded-xl bg-white/10 p-4 backdrop-blur-md border border-white/20">
                <span class="text-xs uppercase tracking-wider font-bold text-purple-200">{{ __('Prix du stage convenu') }}</span>
                <div class="mt-1 flex items-baseline gap-2">
                    <span class="text-3xl md:text-4xl font-black tracking-tight text-white">
                        {{ format_number((float) $stagiaire->stage_price, 2) }}
                    </span>
                    <span class="text-lg font-bold text-purple-200">{{ currency_symbol($stagiaire->currency ?? default_currency()) }}</span>
                </div>
                <span class="mt-1 text-xs text-purple-200">{{ __('Montant convenu pour la totalité du stage') }}</span>
            </div>
        </div>
    </div>

    {{-- Grille principale --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Colonne gauche : Infos perso & CIN (2 cols) --}}
        <div class="lg:col-span-2 space-y-6">
            {{-- 1. Informations Personnelles --}}
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-xs dark:border-slate-800 dark:bg-slate-900">
                <h3 class="text-base font-bold text-slate-900 dark:text-white flex items-center gap-2 mb-4">
                    <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-purple-100 text-purple-700 dark:bg-purple-950 dark:text-purple-300">
                        <x-icon name="user" class="h-4 w-4" />
                    </span>
                    {{ __('Informations personnelles') }}
                </h3>

                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                    <div>
                        <dt class="font-medium text-slate-500 dark:text-slate-400">{{ __('Nom complet') }}</dt>
                        <dd class="mt-1 text-sm font-semibold text-slate-900 dark:text-white">
                            {{ $stagiaire->last_name }} {{ $stagiaire->first_name }}
                        </dd>
                    </div>

                    <div>
                        <dt class="font-medium text-slate-500 dark:text-slate-400">{{ __('Genre') }}</dt>
                        <dd class="mt-1 text-sm font-semibold text-slate-900 dark:text-white">
                            {{ $stagiaire->gender === 'homme' ? __('Homme') : ($stagiaire->gender === 'femme' ? __('Femme') : __('Non spécifié')) }}
                        </dd>
                    </div>

                    <div>
                        <dt class="font-medium text-slate-500 dark:text-slate-400">{{ __('Date de naissance') }}</dt>
                        <dd class="mt-1 text-sm font-semibold text-slate-900 dark:text-white">
                            {{ $stagiaire->birth_date ? format_date($stagiaire->birth_date, 'd/m/Y') : '—' }}
                        </dd>
                    </div>

                    <div>
                        <dt class="font-medium text-slate-500 dark:text-slate-400">{{ __('Lieu de naissance') }}</dt>
                        <dd class="mt-1 text-sm font-semibold text-slate-900 dark:text-white">
                            {{ $stagiaire->birth_place ?? '—' }}
                        </dd>
                    </div>

                    <div>
                        <dt class="font-medium text-slate-500 dark:text-slate-400">{{ __('Nationalité') }}</dt>
                        <dd class="mt-1 text-sm font-semibold text-slate-900 dark:text-white">
                            {{ $stagiaire->nationality ?? '—' }}
                        </dd>
                    </div>

                    <div>
                        <dt class="font-medium text-slate-500 dark:text-slate-400">{{ __('Téléphone') }}</dt>
                        <dd class="mt-1 text-sm font-semibold text-slate-900 dark:text-white">
                            <bdi dir="ltr">{{ $stagiaire->phone ? $stagiaire->phone : '-' }}</bdi>
                        </dd>
                    </div>

                    <div>
                        <dt class="font-medium text-slate-500 dark:text-slate-400">{{ __('Numéro WhatsApp') }}</dt>
                        <dd class="mt-1 text-sm font-semibold text-slate-900 dark:text-white">
                            <bdi dir="ltr">{{ $stagiaire->whatsapp ?: '—' }}</bdi>
                        </dd>
                    </div>

                    <div>
                        <dt class="font-medium text-slate-500 dark:text-slate-400">{{ __('Numéro de téléphone d\'urgence') }}</dt>
                        <dd class="mt-1 text-sm font-semibold text-slate-900 dark:text-white">
                            <bdi dir="ltr">{{ $stagiaire->emergency_contact_phone ?: '—' }}</bdi>
                        </dd>
                    </div>

                    <div>
                        <dt class="font-medium text-slate-500 dark:text-slate-400">{{ __('Email') }}</dt>
                        <dd class="mt-1 text-sm font-semibold text-slate-900 dark:text-white">
                            @if($stagiaire->email)
                                <a href="mailto:{{ $stagiaire->email }}" class="text-purple-600 hover:underline dark:text-purple-400">
                                    {{ $stagiaire->email }}
                                </a>
                            @else
                                —
                            @endif
                        </dd>
                    </div>

                    <div class="sm:col-span-2">
                        <dt class="font-medium text-slate-500 dark:text-slate-400">{{ __('Adresse résidentielle') }}</dt>
                        <dd class="mt-1 text-sm font-semibold text-slate-900 dark:text-white">
                            {{ $stagiaire->address ?? '—' }}
                        </dd>
                    </div>
                </dl>
            </div>

            {{-- 1 bis. Entreprise où le stagiaire effectuera son stage --}}
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-xs dark:border-slate-800 dark:bg-slate-900">
                <h3 class="text-base font-bold text-slate-900 dark:text-white flex items-center gap-2 mb-4">
                    <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-brand-100 text-brand-700 dark:bg-brand-950 dark:text-brand-300">
                        <x-icon name="building" class="h-4 w-4" />
                    </span>
                    {{ __('Entreprise où le stagiaire effectuera son stage') }}
                </h3>

                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                    <div>
                        <dt class="font-medium text-slate-500 dark:text-slate-400">{{ __('Nom de l\'entreprise') }}</dt>
                        <dd class="mt-1 text-sm font-semibold text-slate-900 dark:text-white">
                            {{ $stagiaire->company_name ?: __('Non renseigné') }}
                        </dd>
                    </div>

                    <div>
                        <dt class="font-medium text-slate-500 dark:text-slate-400">{{ __('Numéro de téléphone de l\'entreprise') }}</dt>
                        <dd class="mt-1 text-sm font-semibold text-slate-900 dark:text-white">
                            <bdi dir="ltr">{{ $stagiaire->company_phone ?: '—' }}</bdi>
                        </dd>
                    </div>

                    <div class="sm:col-span-2">
                        <dt class="font-medium text-slate-500 dark:text-slate-400">{{ __('Lieu de l\'entreprise') }}</dt>
                        <dd class="mt-1 text-sm font-semibold text-slate-900 dark:text-white">
                            {{ $stagiaire->company_location ?: '—' }}
                        </dd>
                    </div>
                </dl>
            </div>

            {{-- 2. Carte Nationale d'Identité (CIN) --}}
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-xs dark:border-slate-800 dark:bg-slate-900">
                <h3 class="text-base font-bold text-slate-900 dark:text-white flex items-center gap-2 mb-4">
                    <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-indigo-100 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300">
                        <x-icon name="shield" class="h-4 w-4" />
                    </span>
                    {{ __('Pièce d\'identité (CIN)') }}
                </h3>

                <dl class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-xs mb-6">
                    <div>
                        <dt class="font-medium text-slate-500 dark:text-slate-400">{{ __('Numéro CIN') }}</dt>
                        <dd class="mt-1 text-sm font-bold text-slate-900 dark:text-white font-mono">
                            {{ $stagiaire->cin_number ?? __('Non renseigné') }}
                        </dd>
                    </div>
                    <div>
                        <dt class="font-medium text-slate-500 dark:text-slate-400">{{ __('Date de délivrance') }}</dt>
                        <dd class="mt-1 text-sm font-semibold text-slate-900 dark:text-white">
                            {{ $stagiaire->cin_delivered_at ? format_date($stagiaire->cin_delivered_at, 'd/m/Y') : '—' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="font-medium text-slate-500 dark:text-slate-400">{{ __('Lieu de délivrance') }}</dt>
                        <dd class="mt-1 text-sm font-semibold text-slate-900 dark:text-white">
                            {{ $stagiaire->cin_delivered_place ?? '—' }}
                        </dd>
                    </div>
                </dl>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-4 border-t border-slate-100 dark:border-slate-800">
                    {{-- CIN Recto --}}
                    <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-4 dark:border-slate-700/60 dark:bg-slate-800/40">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-xs font-bold text-slate-800 dark:text-slate-200">{{ __('CIN — Recto') }}</span>
                            @if($stagiaire->cin_recto_path)
                                <span class="rounded bg-emerald-100 px-1.5 py-0.5 text-2xs font-bold text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">{{ __('Fichier présent') }}</span>
                            @else
                                <span class="rounded bg-slate-200 px-1.5 py-0.5 text-2xs font-bold text-slate-600 dark:bg-slate-700 dark:text-slate-400">{{ __('Non fourni') }}</span>
                            @endif
                        </div>
                        @if($stagiaire->cin_recto_path)
                            <a href="{{ route('stagiaires.cin-download', [$stagiaire, 'recto']) }}"
                               class="mt-2 inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white shadow-2xs hover:bg-indigo-700 transition">
                                <x-icon name="download" class="h-3.5 w-3.5" />
                                <span>{{ __('Télécharger CIN Recto') }}</span>
                            </a>
                        @endif
                    </div>

                    {{-- CIN Verso --}}
                    <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-4 dark:border-slate-700/60 dark:bg-slate-800/40">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-xs font-bold text-slate-800 dark:text-slate-200">{{ __('CIN — Verso') }}</span>
                            @if($stagiaire->cin_verso_path)
                                <span class="rounded bg-emerald-100 px-1.5 py-0.5 text-2xs font-bold text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">{{ __('Fichier présent') }}</span>
                            @else
                                <span class="rounded bg-slate-200 px-1.5 py-0.5 text-2xs font-bold text-slate-600 dark:bg-slate-700 dark:text-slate-400">{{ __('Non fourni') }}</span>
                            @endif
                        </div>
                        @if($stagiaire->cin_verso_path)
                            <a href="{{ route('stagiaires.cin-download', [$stagiaire, 'verso']) }}"
                               class="mt-2 inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white shadow-2xs hover:bg-indigo-700 transition">
                                <x-icon name="download" class="h-3.5 w-3.5" />
                                <span>{{ __('Télécharger CIN Verso') }}</span>
                            </a>
                        @endif
                    </div>
                </div>
            </div>

            {{-- 3. Diplômes --}}
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-xs dark:border-slate-800 dark:bg-slate-900">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-base font-bold text-slate-900 dark:text-white flex items-center gap-2">
                        <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300">
                            <x-icon name="certificate" class="h-4 w-4" />
                        </span>
                        {{ __('Diplômes enregistrés (') }}{{ $stagiaire->diplomas->count() }})
                    </h3>
                </div>

                @if($stagiaire->diplomas->isEmpty())
                    <p class="text-xs text-slate-500 italic py-3 dark:text-slate-400">{{ __('Aucun diplôme enregistré pour ce stagiaire.') }}</p>
                @else
                    <div class="space-y-3">
                        @foreach($stagiaire->diplomas as $diploma)
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 rounded-xl border border-slate-200 p-3.5 dark:border-slate-700/60 dark:bg-slate-800/30">
                                <div class="space-y-0.5">
                                    <h4 class="text-sm font-bold text-slate-900 dark:text-white">{{ $diploma->title }}</h4>
                                    <p class="text-xs text-slate-600 dark:text-slate-400">
                                        {{ $diploma->institution ?? __('Établissement non précisé') }}
                                        @if($diploma->year)
                                            <span class="text-slate-400 dark:text-slate-500">{{ __('• Promotion') }} {{ $diploma->year }}</span>
                                        @endif
                                    </p>
                                </div>
                                <div class="flex items-center gap-2">
                                    @if($diploma->file_path)
                                        <a href="{{ route('stagiaires.diploma-download', [$stagiaire, $diploma]) }}"
                                           class="inline-flex items-center gap-1.5 rounded-lg bg-amber-600 px-3 py-1.5 text-xs font-semibold text-white shadow-2xs hover:bg-amber-700 transition">
                                            <x-icon name="download" class="h-3.5 w-3.5" />
                                            <span>{{ __('Consulter le diplôme') }}</span>
                                        </a>
                                    @else
                                        <span class="text-xs text-slate-400 italic">{{ __('Sans justificatif') }}</span>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- 4. Autres Documents --}}
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-xs dark:border-slate-800 dark:bg-slate-900">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-base font-bold text-slate-900 dark:text-white flex items-center gap-2">
                        <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-teal-100 text-teal-700 dark:bg-teal-950 dark:text-teal-300">
                            <x-icon name="document" class="h-4 w-4" />
                        </span>
                        {{ __('Autres documents administratifs (') }}{{ $stagiaire->documents->count() }})
                    </h3>
                </div>

                @if($stagiaire->documents->isEmpty())
                    <p class="text-xs text-slate-500 italic py-3 dark:text-slate-400">{{ __('Aucun document annexe enregistré.') }}</p>
                @else
                    <div class="space-y-3">
                        @foreach($stagiaire->documents as $doc)
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 rounded-xl border border-slate-200 p-3.5 dark:border-slate-700/60 dark:bg-slate-800/30">
                                <div class="space-y-0.5">
                                    <div class="flex items-center gap-2">
                                        <h4 class="text-sm font-bold text-slate-900 dark:text-white">{{ $doc->title }}</h4>
                                        <span class="rounded bg-teal-100 px-1.5 py-0.5 text-2xs font-semibold text-teal-800 dark:bg-teal-950 dark:text-teal-300">
                                            {{ ucfirst($doc->type) }}
                                        </span>
                                    </div>
                                    <p class="text-xs text-slate-500 dark:text-slate-400">
                                        {{ __('Déposé le') }} {{ format_date($doc->created_at, 'd/m/Y H:i') }}
                                    </p>
                                </div>
                                <div class="flex items-center gap-2">
                                    <a href="{{ route('stagiaires.document-download', [$stagiaire, $doc]) }}"
                                       class="inline-flex items-center gap-1.5 rounded-lg bg-teal-600 px-3 py-1.5 text-xs font-semibold text-white shadow-2xs hover:bg-teal-700 transition">
                                        <x-icon name="download" class="h-3.5 w-3.5" />
                                        <span>{{ __('Télécharger') }}</span>
                                    </a>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        {{-- Colonne droite : Stage & Détails administratifs --}}
        <div class="space-y-6">
            {{-- Détails du Stage --}}
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-xs dark:border-slate-800 dark:bg-slate-900">
                <h3 class="text-base font-bold text-slate-900 dark:text-white flex items-center gap-2 mb-4">
                    <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-purple-100 text-purple-700 dark:bg-purple-950 dark:text-purple-300">
                        <x-icon name="briefcase" class="h-4 w-4" />
                    </span>
                    {{ __('Informations du stage') }}
                </h3>

                <dl class="space-y-3.5 text-xs">
                    <div>
                        <dt class="font-medium text-slate-500 dark:text-slate-400">{{ __('Statut du stage') }}</dt>
                        <dd class="mt-1">
                            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold
                                @if($stagiaire->status === 'en_cours') bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300
                                @elseif($stagiaire->status === 'termine') bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300
                                @elseif($stagiaire->status === 'nouveau') bg-purple-100 text-purple-800 dark:bg-purple-900/40 dark:text-purple-300
                                @elseif($stagiaire->status === 'annule') bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300
                                @else bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300
                                @endif">
                                {{ $stagiaire->status_label }}
                            </span>
                        </dd>
                    </div>

                    <div>
                        <dt class="font-medium text-slate-500 dark:text-slate-400">{{ __('Type de stage') }}</dt>
                        <dd class="mt-1 text-sm font-semibold text-slate-900 dark:text-white">
                            {{ $stagiaire->stage_type ?? __('Non spécifié') }}
                        </dd>
                    </div>

                    <div>
                        <dt class="font-medium text-slate-500 dark:text-slate-400">{{ __('Date de début') }}</dt>
                        <dd class="mt-1 text-sm font-semibold text-slate-900 dark:text-white">
                            {{ $stagiaire->stage_start_date ? format_date($stagiaire->stage_start_date, 'd/m/Y') : __('Non renseignée') }}
                        </dd>
                    </div>

                    <div>
                        <dt class="font-medium text-slate-500 dark:text-slate-400">{{ __('Date de fin prévue') }}</dt>
                        <dd class="mt-1 text-sm font-semibold text-slate-900 dark:text-white">
                            {{ $stagiaire->stage_end_date ? format_date($stagiaire->stage_end_date, 'd/m/Y') : __('Non renseignée') }}
                        </dd>
                    </div>

                    <div class="pt-3 border-t border-slate-100 dark:border-slate-800">
                        <dt class="font-medium text-slate-500 dark:text-slate-400">{{ __('Centre d\'accueil') }}</dt>
                        <dd class="mt-1 text-sm font-semibold text-slate-900 dark:text-white">
                            {{ $stagiaire->center?->name ?? __('Aucun centre') }}
                        </dd>
                    </div>

                    @if($stagiaire->notes)
                        <div class="pt-3 border-t border-slate-100 dark:border-slate-800">
                            <dt class="font-medium text-slate-500 dark:text-slate-400">{{ __('Notes & observations') }}</dt>
                            <dd class="mt-1 whitespace-pre-line text-xs text-slate-700 dark:text-slate-300">
                                {{ $stagiaire->notes }}
                            </dd>
                        </div>
                    @endif
                </dl>
            </div>

            {{-- Rappel d'isolation & Sécurité --}}
            <div class="rounded-2xl border border-purple-200 bg-purple-50/60 p-5 dark:border-purple-900/40 dark:bg-purple-950/20">
                <div class="flex items-start gap-3">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-purple-600 text-white shadow-2xs">
                        <x-icon name="shield" class="h-4 w-4" />
                    </span>
                    <div class="text-xs space-y-1">
                        <h4 class="font-bold text-purple-900 dark:text-purple-200">{{ __('Espace Privé & Sécurisé') }}</h4>
                        <p class="text-purple-700 dark:text-purple-300 leading-relaxed">
                            {{ __('Ce dossier et les documents rattachés sont stockés dans un espace local privé. Les téléchargements sont protégés par la policy de sécurité.') }}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
