@extends('layouts.app')

@section('title', __('Nouvelle inscription'))

@section('content')
<div class="container-fluid px-4 py-4">

    {{-- En-tête --}}
    <div class="mb-4">
        <nav aria-label="{{ __('breadcrumb') }}">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Tableau de bord') }}</a></li>
                <li class="breadcrumb-item"><a href="{{ route('enrollments.index') }}">{{ __('Inscriptions') }}</a></li>
                <li class="breadcrumb-item active" aria-current="page">{{ __('Création') }}</li>
            </ol>
        </nav>
        <h1 class="h3 fw-bold mb-0 text-gray-800">
            <i class="bi bi-card-plus text-primary me-2"></i>{{ __('Enregistrer une nouvelle inscription') }}
        </h1>
        <p class="text-muted small mb-0">{{ __('L\'inscription sera automatiquement initialisée avec le statut') }} <strong>{{ __('En attente') }}</strong>.</p>
    </div>

    {{-- Alertes erreurs globales --}}
    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <div class="d-flex align-items-center gap-2 mb-2">
                <i class="bi bi-exclamation-triangle-fill fs-5"></i>
                <h6 class="mb-0 fw-bold">{{ __('Impossible d\'enregistrer l\'inscription :') }}</h6>
            </div>
            <ul class="mb-0 ps-3">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="row justify-content-center">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <form action="{{ route('enrollments.store') }}" method="POST">
                        @csrf

                        {{-- Section Centre --}}
                        <div class="row mb-4 pb-3 border-bottom">
                            <div class="col-12">
                                <h5 class="fw-bold text-dark mb-3">
                                    <i class="bi bi-geo-alt text-primary me-2"></i>{{ __('Centre de rattachement') }}
                                </h5>

                                @if(auth()->user()->isSuperAdmin())
                                    <div class="col-md-6">
                                        <label for="center_id" class="form-label fw-semibold">{{ __('Centre') }} <span class="text-danger">*</span></label>
                                        <select name="center_id" id="center_id" class="form-select @error('center_id') is-invalid @enderror" required onchange="this.form.submit()">
                                            <option value="">{{ __('Sélectionner un centre...') }}</option>
                                            @foreach($centers as $c)
                                                <option value="{{ $c->id }}" {{ old('center_id', $selectedCenterId) == $c->id ? 'selected' : '' }}>
                                                    {{ $c->translated('name') }} ({{ $c->code }})
                                                </option>
                                            @endforeach
                                        </select>
                                        <small class="text-muted">{{ __('Changer de centre rechargera les élèves, formations et classes associées.') }}</small>
                                        @error('center_id')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                @else
                                    <div class="p-3 bg-light rounded border d-flex align-items-center gap-2">
                                        <i class="bi bi-building text-primary fs-5"></i>
                                        <div>
                                            <strong>{{ auth()->user()->center->name ?? __('Centre') }}</strong>
                                            <span class="text-muted ms-2">{{ __('(Centre imposé selon votre compte administrateur)') }}</span>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>

                        {{-- Section Élève & Formation --}}
                        <div class="row mb-4 pb-3 border-bottom g-3">
                            <div class="col-12">
                                <h5 class="fw-bold text-dark mb-1">
                                    <i class="bi bi-mortarboard text-primary me-2"></i>{{ __('Élève et Formation postulée') }}
                                </h5>
                                <p class="text-muted small mb-3">{{ __('L\'élève et la formation doivent impérativement être actifs et appartenir à ce centre.') }}</p>
                            </div>

                            {{-- Élève --}}
                            <div class="col-md-6">
                                <label for="student_id" class="form-label fw-semibold">{{ __('Élève candidat') }} <span class="text-danger">*</span></label>
                                <select name="student_id" id="student_id" class="form-select @error('student_id') is-invalid @enderror" required>
                                    <option value="">{{ __('Sélectionner un élève...') }}</option>
                                    @foreach($students as $st)
                                        <option value="{{ $st->id }}" {{ old('student_id', $preselectedStudentId) == $st->id ? 'selected' : '' }}>
                                            {{ $st->full_name }} ({{ $st->student_number }})
                                        </option>
                                    @endforeach
                                </select>
                                @error('student_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            {{-- Formation --}}
                            <div class="col-md-6">
                                <label for="formation_id" class="form-label fw-semibold">{{ __('Formation souhaitée') }} <span class="text-danger">*</span></label>
                                <select name="formation_id" id="formation_id" class="form-select @error('formation_id') is-invalid @enderror" required>
                                    <option value="">{{ __('Sélectionner une formation...') }}</option>
                                    @foreach($formations as $f)
                                        <option value="{{ $f->id }}" {{ old('formation_id', $preselectedFormationId) == $f->id ? 'selected' : '' }}>
                                            {{ $f->translated('name') }} ({{ $f->code }} - {{ format_price($f->price, $f->currency) }})
                                        </option>
                                    @endforeach
                                </select>
                                @error('formation_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            {{-- Classe (optionnelle au départ) --}}
                            <div class="col-md-6">
                                <label for="class_id" class="form-label fw-semibold">
                                    {{ __('Classe cible') }} <span class="text-muted fw-normal">{{ __('(Facultatif à la candidature)') }}</span>
                                </label>
                                <select name="class_id" id="class_id" class="form-select @error('class_id') is-invalid @enderror">
                                    <option value="">{{ __('-- Aucune affectation immédiate --') }}</option>
                                    @foreach($classes as $cl)
                                        <option value="{{ $cl->id }}" 
                                            data-formation-id="{{ $cl->general_formation_id }}"
                                            {{ old('class_id', $preselectedClassId) == $cl->id ? 'selected' : '' }}>
                                            {{ $cl->perimeter_label }} {{ __('— Capacité:') }} {{ $cl->capacity ?? __('Illimitée') }}
                                        </option>
                                    @endforeach
                                </select>
                                <small class="text-muted">{{ __('La classe peut être affectée ultérieurement lors de l\'acceptation de l\'inscription.') }}</small>
                                @error('class_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            {{-- Date de candidature --}}
                            <div class="col-md-6">
                                <label for="application_date" class="form-label fw-semibold">{{ __('Date de candidature') }} <span class="text-danger">*</span></label>
                                <input type="date" name="application_date" id="application_date" 
                                       class="form-control @error('application_date') is-invalid @enderror" 
                                       value="{{ old('application_date', date('Y-m-d')) }}" required>
                                @error('application_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        {{-- Section Notes & Observations --}}
                        <div class="row mb-4 g-3">
                            <div class="col-12">
                                <label for="notes" class="form-label fw-semibold">{{ __('Notes / Projet de l\'élève') }}</label>
                                <textarea name="notes" id="notes" rows="3" class="form-control @error('notes') is-invalid @enderror" 
                                          placeholder="{{ __('Prérequis, motivations de l\'élève, financement prévu, remarques d\'admission...') }}">{{ old('notes') }}</textarea>
                                @error('notes')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        {{-- Boutons d'action --}}
                        <div class="d-flex justify-content-between align-items-center pt-3 border-top">
                            <a href="{{ route('enrollments.index') }}" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-left me-1"></i>{{ __('Retour') }}
                            </a>
                            <button type="submit" class="btn btn-primary px-4">
                                <i class="bi bi-check-lg me-1"></i>{{ __('Enregistrer la candidature') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Script pour filtrer dynamiquement les classes selon la formation choisie --}}
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const formationSelect = document.getElementById('formation_id');
    const classSelect = document.getElementById('class_id');

    if (formationSelect && classSelect) {
        function filterClasses() {
            const selectedFormationId = formationSelect.value;

            Array.from(classSelect.options).forEach(opt => {
                if (!opt.value) return; // Keep placeholder
                const formId = opt.getAttribute('data-formation-id');
                if (!selectedFormationId || formId === selectedFormationId) {
                    opt.style.display = '';
                } else {
                    opt.style.display = 'none';
                    if (opt.selected) {
                        classSelect.value = '';
                    }
                }
            });
        }

        formationSelect.addEventListener('change', filterClasses);
        if (formationSelect.value) {
            filterClasses();
        }
    }
});
</script>
@endpush
@endsection
