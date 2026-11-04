@extends('layouts.app')

@section('title', __('Traductions du contenu'))

@section('content')
<x-ui.page-header
    :title="__('Traductions du contenu')"
    :description="__('Corrigez ou générez les traductions arabes du contenu saisi en base de données.')" />

@if(session('success'))
    <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 dark:border-emerald-900/60 dark:bg-emerald-950/40 dark:text-emerald-300">
        {{ session('success') }}
    </div>
@endif

@if(session('error'))
    <div class="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-800 dark:border-rose-900/60 dark:bg-rose-950/40 dark:text-rose-300">
        {{ session('error') }}
    </div>
@endif

<div class="ec-card mb-4 p-4">
    <form method="GET" class="grid gap-3 md:grid-cols-4">
        <div class="md:col-span-2">
            <label class="ec-label" for="type">{{ __('Type de contenu') }}</label>
            <select name="type" id="type" class="ec-select">
                @foreach($types as $class => $label)
                    <option value="{{ $class }}" @selected($type === $class)>{{ __($label) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="ec-label" for="q">{{ __('Recherche') }}</label>
            <input type="search" name="q" id="q" value="{{ $search }}" class="ec-input" placeholder="{{ __('Rechercher…') }}">
        </div>
        <div class="flex items-end">
            <button class="ec-btn-secondary w-full">{{ __('Filtrer') }}</button>
        </div>
    </form>
</div>

<div class="space-y-4">
    @forelse($records as $record)
        <div class="ec-card p-4">
            <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                <div class="text-xs font-semibold uppercase tracking-wider text-slate-400">
                    {{ __($types[$type]) }} #{{ $record->getKey() }}
                </div>
                <a href="#" class="text-xs font-semibold text-[#0DAD98] hover:underline" onclick="return false;">
                    {{ __('Contenu original en français') }}
                </a>
            </div>

            <div class="space-y-4">
                @foreach($fields as $field)
                    @php
                        $original = (string) ($record->getAttribute($field) ?? '');
                        $fieldTranslations = collect($locales)->mapWithKeys(function (string $locale) use ($translations, $record, $field) {
                            return [$locale => $translations->get($record->getKey().'|'.$field.'|'.$locale)];
                        });
                    @endphp

                    @if($original !== '')
                        <div class="rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] p-3 dark:border-slate-700 dark:bg-slate-800/50">
                            <p class="mb-1 text-xs font-bold uppercase tracking-wider text-slate-400">{{ __($field) }}</p>
                            <p class="whitespace-pre-line text-sm text-slate-700 dark:text-slate-200">{{ $original }}</p>

                            <div class="mt-3 space-y-3">
                                @foreach($fieldTranslations as $locale => $translation)
                                    <form method="POST" action="{{ route('super-admin.translations.store') }}" class="space-y-2">
                                        @csrf
                                        <input type="hidden" name="type" value="{{ $type }}">
                                        <input type="hidden" name="id" value="{{ $record->getKey() }}">
                                        <input type="hidden" name="field" value="{{ $field }}">
                                        <input type="hidden" name="locale" value="{{ $locale }}">

                                        <div class="flex items-center justify-between gap-2">
                                            <label class="text-xs font-bold text-slate-600 dark:text-slate-300">
                                                {{ strtoupper($locale) }}
                                                @if($translation)
                                                    <span class="ms-1 rounded-full px-1.5 py-0.5 text-2xs font-bold {{ $translation->isManual() ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300' : 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300' }}">
                                                        {{ $translation->isManual() ? __('Manuelle') : __('Automatique') }}
                                                    </span>
                                                @else
                                                    <span class="ms-1 rounded-full bg-slate-100 px-1.5 py-0.5 text-2xs font-bold text-slate-500 dark:bg-slate-700 dark:text-slate-300">{{ __('Manquante') }}</span>
                                                @endif
                                            </label>
                                        </div>

                                        <textarea name="value" rows="{{ $field === 'content' || $field === 'description' ? 4 : 2 }}"
                                                  class="ec-textarea"
                                                  dir="{{ $locale === 'ar' ? 'rtl' : 'ltr' }}"
                                                  placeholder="{{ __('Saisir la traduction…') }}">{{ $translation?->value }}</textarea>

                                        <div class="flex flex-wrap gap-2">
                                            <button class="ec-btn-primary ec-btn-sm">{{ __('Enregistrer') }}</button>
                                        </div>
                                    </form>

                                    <form method="POST" action="{{ route('super-admin.translations.auto') }}">
                                        @csrf
                                        <input type="hidden" name="type" value="{{ $type }}">
                                        <input type="hidden" name="id" value="{{ $record->getKey() }}">
                                        <input type="hidden" name="field" value="{{ $field }}">
                                        <input type="hidden" name="locale" value="{{ $locale }}">
                                        <button class="ec-btn-secondary ec-btn-sm">{{ __('Traduire automatiquement') }}</button>
                                    </form>
                                @endforeach
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>
        </div>
    @empty
        <x-ui.card>
            <x-ui.empty :title="__('Aucun contenu à traduire')" />
        </x-ui.card>
    @endforelse
</div>

@if($records->hasPages())
    <div class="mt-4">{{ $records->links() }}</div>
@endif
@endsection
