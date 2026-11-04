@extends('layouts.app')

@section('title', __('Modifier l\'inscription'))

@section('content')
<div class="container-fluid px-4 py-4">

    {{-- En-tête --}}
    <div class="mb-4">
        <nav aria-label="{{ __('breadcrumb') }}">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Tableau de bord') }}</a></li>
                <li class="breadcrumb-item"><a href="{{ route('enrollments.index') }}">{{ __('Inscriptions') }}</a></li>
                <li class="breadcrumb-item"><a href="{{ route('enrollments.show', $enrollment) }}">{{ $enrollment->registration_number }}</a></li>
                <li class="breadcrumb-item active" aria-current="page">{{ __('Modifier') }}</li>
            </ol>
        </nav>
        <h1 class="h3 fw-bold mb-0 text-gray-800">
            <i class="bi bi-pencil-square text-primary me-2"></i>{{ __('Modifier l\'inscription') }} {{ $enrollment->registration_number }}
        </h1>
    </div>

    {{-- Alertes erreurs globales --}}
    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <div class="d-flex align-items-center gap-2 mb-2">
                <i class="bi bi-exclamation-triangle-fill fs-5"></i>
                <h6 class="mb-0 fw-bold">{{ __('Impossible de mettre à jour l\'inscription :') }}</h6>
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
                    <form action="{{ route('enrollments.update', $enrollment) }}" method="POST">
                        @csrf
                        @method('PUT')

                        {{-- Section Centre --}}
                        <div class="row mb-4 pb-3 border-bottom">
                            <div class="col-12">
                                <h5 class="fw-bold text-dark mb-3">
                                    <i class="bi bi-geo-alt text-primary me-2"></i>{{ __('Centre de rattachement') }}
                                </h5>

                                <div class="p-3 bg-light rounded border d-flex align-items-center gap-2">
                                    <i class="bi bi-building text-primary fs-5"></i>
                                    <div>
                                        <strong>{{ $enrollment->center->name }}</strong>
                                        <span class="badge bg-secondary ms-2">{{ $enrollment->center->code }}</span>
                                        <small class="text-muted d-block mt-1">{{ __('Le centre de l\'inscription est immuable pour préserver l\'intégrité des dossiers.') }}</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Section Élève & Formation --}}
                        <div class="row mb-4 pb-3 border-bottom g-3">
                            <div class="col-12">
                                <h5 class="fw-bold text-dark mb-1">
                                    <i class="bi bi-mortarboard text-primary me-2"></i>{{ __('Élève, Formation et Classe') }}
                                </h5>
                            </div>

                            {{-- Élève --}}
                            <div class="col-md-6">
                                <label for="student_id" class="form-label fw-semibold">{{ __('Élève') }} <span class="text-danger">*</span></label>
                                <select name="student_id" id="student_id" class="form-select @error('student_id') is-invalid @enderror" required>
                                    @foreach($students as $st)
                                        <option value="{{ $st->id }}" {{ old('student_id', $enrollment->student_id) == $st->id ? 'selected' : '' }}>
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
                                <label for="formation_id" class="form-label fw-semibold">{{ __('Formation') }} <span class="text-danger">*</span></label>
                                <select name="formation_id" id="formation_id" class="form-select @error('formation_id') is-invalid @enderror" required>
                                    @foreach($formations as $f)
                                        <option value="{{ $f->id }}" {{ old('formation_id', $selectedFormationId) == $f->id ? 'selected' : '' }}>
                                            {{ $f->translated('name') }} ({{ $f->code }})
                                        </option>
                                    @endforeach
                                </select>
                                @error('formation_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            {{-- Classe --}}
                            <div class="col-md-6">
                                <label for="class_id" class="form-label fw-semibold">{{ __('Classe affectée') }}</label>
                                <select name="class_id" id="class_id" class="form-select @error('class_id') is-invalid @enderror">
                                    <option value="">{{ __('-- Aucune affectation --') }}</option>
                                    @foreach($classes as $cl)
                                        <option value="{{ $cl->id }}" {{ old('class_id', $enrollment->class_id) == $cl->id ? 'selected' : '' }}>
                                            {{ $cl->perimeter_label }} {{ __('(Capacité:') }} {{ $cl->capacity ?? __('Illimitée') }})
                                        </option>
                                    @endforeach
                                </select>
                                @error('class_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            {{-- Date de candidature --}}
                            <div class="col-md-6">
                                <label for="application_date" class="form-label fw-semibold">{{ __('Date de candidature') }} <span class="text-danger">*</span></label>
                                <input type="date" name="application_date" id="application_date" 
                                       class="form-control @error('application_date') is-invalid @enderror" 
                                       value="{{ old('application_date', $enrollment->application_date->format('Y-m-d')) }}" required>
                                @error('application_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        {{-- Section Notes & Observations --}}
                        <div class="row mb-4 g-3">
                            <div class="col-md-6">
                                <label for="notes" class="form-label fw-semibold">{{ __('Notes / Projet de l\'élève') }}</label>
                                <textarea name="notes" id="notes" rows="4" class="form-control @error('notes') is-invalid @enderror">{{ old('notes', $enrollment->notes) }}</textarea>
                                @error('notes')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6">
                                <label for="decision_notes" class="form-label fw-semibold">{{ __('Commentaires de décision administrative') }}</label>
                                <textarea name="decision_notes" id="decision_notes" rows="4" class="form-control @error('decision_notes') is-invalid @enderror">{{ old('decision_notes', $enrollment->decision_notes) }}</textarea>
                                @error('decision_notes')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        {{-- Boutons d'action --}}
                        <div class="d-flex justify-content-between align-items-center pt-3 border-top">
                            <a href="{{ route('enrollments.show', $enrollment) }}" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-left me-1"></i>{{ __('Annuler') }}
                            </a>
                            <button type="submit" class="btn btn-primary px-4">
                                <i class="bi bi-check-lg me-1"></i>{{ __('Enregistrer les modifications') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
