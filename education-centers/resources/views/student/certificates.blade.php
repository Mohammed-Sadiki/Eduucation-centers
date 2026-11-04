@extends('layouts.app')

@section('title', __('Mes certificats'))

@section('content')
    <x-ui.page-header
        :title="__('Mes certificats')"
        :description="__('Vos certificats et attestations émis par votre centre. Les fichiers PDF sont les documents officiels.').' '.($student?->center?->name ?? auth()->user()->center?->name)"
    />

    <form method="GET" action="{{ route('student.certificates') }}" class="ec-card mb-5 p-4 sm:p-5">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 lg:items-end">
            <label class="block sm:col-span-2">
                <span class="ec-label">{{ __('Type de certificat') }}</span>
                <select name="type" class="ec-select">
                    <option value="">{{ __('Tous les types') }}</option>
                    @foreach(['enrollment' => __('Attestation d’inscription'), 'completion' => __('Certificat de formation'), 'attendance' => __('Attestation de présence'), 'grades_report' => __('Relevé de notes'), 'receipt' => __('Reçu de paiement'), 'other' => __('Autre')] as $value => $label)
                        <option value="{{ $value }}" @selected(request('type') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <div class="flex flex-wrap gap-2">
                <button type="submit" class="ec-btn-primary">
                    <x-icon name="filter" class="h-4 w-4" /> {{ __('Filtrer') }}
                </button>
                @if(request()->filled('type'))
                    <a href="{{ route('student.certificates') }}" class="ec-btn-ghost">{{ __('Réinitialiser') }}</a>
                @endif
            </div>
        </div>
    </form>

    @if($certificates->isEmpty())
        <x-ui.card>
            <x-ui.empty
                :title="__('Aucun certificat')"
                :description="__('Aucun certificat émis n’est disponible dans votre espace pour le moment.')"
            />
        </x-ui.card>
    @else
        <div class="grid gap-5 xl:grid-cols-2">
            @foreach($certificates as $certificate)
                <article class="ec-panel overflow-hidden">
                    <div class="flex items-start gap-3 border-b border-slate-100 p-5 dark:border-slate-800">
                        <span class="ec-icon-chip shrink-0 bg-violet-50 text-violet-600 dark:bg-violet-500/15 dark:text-violet-300">
                            <x-icon name="certificate" class="h-5 w-5" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="font-mono text-xs font-semibold text-brand-600 dark:text-brand-300">{{ $certificate->certificate_number }}</p>
                            <h2 class="mt-0.5 text-lg font-bold text-slate-900 dark:text-white">{{ $certificate->title ?: $certificate->type_label }}</h2>
                            <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">{{ $certificate->type_label }}</p>
                        </div>
                        <x-status-badge type="certificate" :status="$certificate->status" />
                    </div>

                    <dl class="grid gap-x-6 gap-y-4 px-5 py-4 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ __('Formation') }}</dt>
                            <dd class="mt-0.5 text-sm font-medium text-slate-800 dark:text-slate-100">
                                {{ $certificate->formation?->general_formation_name ?? '—' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ __('Date d’émission') }}</dt>
                            <dd class="mt-0.5 text-sm text-slate-800 dark:text-slate-100">{{ $certificate->issued_date ? format_date($certificate->issued_date, 'd/m/Y') : '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ __('Valable jusqu’au') }}</dt>
                            <dd class="mt-0.5 text-sm text-slate-800 dark:text-slate-100">{{ $certificate->valid_until ? format_date($certificate->valid_until, 'd/m/Y') : __('Sans limite') }}</dd>
                        </div>
                    </dl>

                    <div class="flex flex-wrap items-center gap-2 border-t border-slate-100 bg-slate-50/60 px-5 py-3 dark:border-slate-800 dark:bg-slate-800/30">
                        <a href="{{ route('student.certificates.print', $certificate) }}" target="_blank" rel="noopener" class="ec-btn-secondary ec-btn-sm">
                            <x-icon name="printer" class="h-3.5 w-3.5" /> {{ __('Imprimer') }}
                        </a>
                        <a href="{{ route('student.certificates.download', $certificate) }}" class="ec-btn-ghost ec-btn-sm">
                            <x-icon name="download" class="h-3.5 w-3.5" /> {{ __('Télécharger le PDF') }}
                        </a>
                    </div>
                </article>
            @endforeach
        </div>

        @if($certificates->hasPages())
            <div class="mt-4">{{ $certificates->links() }}</div>
        @endif
    @endif
@endsection
