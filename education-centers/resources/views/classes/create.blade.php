@extends('layouts.app')

@section('title', __('Nouvelle Classe') . ' — ' . config('app.name'))
@section('description', __('Création d’une classe pédagogique'))

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('classes.index') }}">{{ __('Classes') }}</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ __('Nouvelle classe') }}</li>
@endsection

@section('content')
<div class="container-fluid px-4 py-4">

    <div class="row justify-content-center">
        <div class="col-lg-9">

            {{-- En-tête --}}
            <div class="d-flex align-items-center justify-content-between mb-4">
                <div class="d-flex align-items-center gap-3 [direction:ltr]" dir="ltr">
                    <x-ui.back-button fallback="{{ route('classes.index') }}" />
                    <div class="{{ current_dir() === 'rtl' ? '[direction:rtl] text-end' : '' }}">
                        <h1 class="h3 fw-bold mb-0">
                            <i class="bi bi-diagram-3-fill text-primary me-2"></i>{{ __('Nouvelle Classe') }}
                        </h1>
                        <p class="text-muted small mb-0 mt-1">
                            @if(auth()->user()->isCenterAdmin())
                                {{ __('Centre :') }} <strong>{{ auth()->user()->center?->name }}</strong>
                            @else
                                {{ __('Rattacher un groupe classe à une formation et un centre.') }}
                            @endif
                        </p>
                    </div>
                </div>
            </div>

            <form method="POST" action="{{ route('classes.store') }}" id="form-create-class">
                @csrf

                <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                {{-- Bloc 1 : Rattachement Centre & Formation --}}
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white py-3 border-bottom">
                        <h5 class="card-title mb-0 fw-bold text-primary">
                            <i class="bi bi-diagram-2 me-2"></i>{{ __('1. Rattachement pédagogique') }}
                        </h5>
                    </div>
                    <div class="card-body p-4">
                        <div class="row g-3">
                            {{-- Sélection du centre pour Super Admin --}}
                            @if(auth()->user()->isSuperAdmin())
                                <div class="col-md-6">
                                    <label for="center_id" class="form-label fw-semibold">
                                        {{ __('Centre éducatif') }} <span class="text-danger">*</span>
                                    </label>
                                    <select id="center_id" name="center_id" class="form-select @error('center_id') is-invalid @enderror" required>
                                        <option value="">{{ __('-- Sélectionner un centre --') }}</option>
                                        @foreach($centers as $center)
                                            <option value="{{ $center->id }}" {{ old('center_id') == $center->id ? 'selected' : '' }}>
                                                {{ $center->translated('name') }} ({{ $center->code }})
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('center_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    <div class="form-text">{{ __('Seules les formations de ce centre seront utilisables.') }}</div>
                                </div>
                            @endif

                            {{-- Sélection de la formation --}}
                            <div class="col-md-{{ auth()->user()->isSuperAdmin() ? '6' : '12' }}">
                                <label for="formation_id" class="form-label fw-semibold">
                                    {{ __('Formation rattachée') }} <span class="text-danger">*</span>
                                </label>
                                <select id="formation_id" name="formation_id" class="form-select @error('formation_id') is-invalid @enderror" required>
                                    <option value="">{{ __('-- Sélectionner une formation --') }}</option>
                                    @foreach($formations as $formation)
                                        <option value="{{ $formation->id }}"
                                                data-center-id="{{ $formation->center_id }}"
                                                {{ old('formation_id') == $formation->id ? 'selected' : '' }}>
                                            {{ $formation->code }} — {{ $formation->translated('name') }}
                                            @if(auth()->user()->isSuperAdmin() && $formation->center)
                                                [{{ $formation->center->name }}]
                                            @endif
                                        </option>
                                    @endforeach
                                </select>
                                @error('formation_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Bloc 2 : Informations de la classe --}}
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white py-3 border-bottom">
                        <h5 class="card-title mb-0 fw-bold text-primary">
                            <i class="bi bi-info-circle me-2"></i>{{ __('2. Informations générales') }}
                        </h5>
                    </div>
                    <div class="card-body p-4">
                        <div class="row g-3">
                            <div class="col-md-8">
                                <label for="name" class="form-label fw-semibold">
                                    {{ __('Intitulé de la classe') }} <span class="text-danger">*</span>
                                </label>
                                <input type="text" id="name" name="name"
                                       class="form-control @error('name') is-invalid @enderror"
                                       value="{{ old('name') }}" placeholder="{{ __('Ex: Groupe A1 — Matin') }}" required>
                                @error('name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-4">
                                <label for="code" class="form-label fw-semibold">
                                    {{ __('Code classe') }} <span class="text-danger">*</span>
                                </label>
                                <input type="text" id="code" name="code"
                                       class="form-control @error('code') is-invalid @enderror text-uppercase"
                                       value="{{ old('code') }}" placeholder="{{ __('Ex: A1-01') }}" required>
                                @error('code')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <div class="form-text">{{ __('Unique au sein de ce centre.') }}</div>
                            </div>

                            <div class="col-md-12">
                                <label for="description" class="form-label fw-semibold">{{ __('Description / Remarques') }}</label>
                                <textarea id="description" name="description" rows="3"
                                          class="form-control @error('description') is-invalid @enderror"
                                          placeholder="{{ __('Horaires indicatifs, rythme de cours...') }}">{{ old('description') }}</textarea>
                                @error('description')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Bloc 3 : Capacité & Calendrier --}}
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white py-3 border-bottom">
                        <h5 class="card-title mb-0 fw-bold text-primary">
                            <i class="bi bi-calendar-event me-2"></i>{{ __('3. Capacité & Période') }}
                        </h5>
                    </div>
                    <div class="card-body p-4">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label for="capacity" class="form-label fw-semibold">{{ __('Capacité maximale (élèves)') }}</label>
                                <div class="input-group">
                                    <input type="number" id="capacity" name="capacity" min="1" step="1"
                                           class="form-control @error('capacity') is-invalid @enderror"
                                           value="{{ old('capacity', 20) }}" placeholder="{{ __('Ex: 20') }}">
                                    <span class="input-group-text">{{ __('places') }}</span>
                                </div>
                                @error('capacity')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-4">
                                <label for="start_date" class="form-label fw-semibold">{{ __('Date de début') }}</label>
                                <input type="date" id="start_date" name="start_date"
                                       class="form-control @error('start_date') is-invalid @enderror"
                                       value="{{ old('start_date') }}">
                                @error('start_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-4">
                                <label for="end_date" class="form-label fw-semibold">{{ __('Date de fin') }}</label>
                                <input type="date" id="end_date" name="end_date"
                                       class="form-control @error('end_date') is-invalid @enderror"
                                       value="{{ old('end_date') }}">
                                @error('end_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-4">
                                <label for="status" class="form-label fw-semibold">
                                    {{ __('Statut') }} <span class="text-danger">*</span>
                                </label>
                                <select id="status" name="status" class="form-select @error('status') is-invalid @enderror" required>
                                    <option value="active"   {{ old('status', 'active') === 'active'   ? 'selected' : '' }}>{{ __('Actif') }}</option>
                                    <option value="inactive" {{ old('status') === 'inactive' ? 'selected' : '' }}>{{ __('Inactif') }}</option>
                                </select>
                                @error('status')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Actions --}}
                <div class="d-flex justify-content-end gap-3 mb-5 lg:col-span-2">
                    <a href="{{ route('classes.index') }}" class="btn btn-outline-secondary px-4">{{ __('Annuler') }}</a>
                    <button type="submit" class="btn btn-primary px-5 shadow-sm" id="btn-submit-class">
                        <i class="bi bi-check-circle-fill me-1"></i>{{ __('Enregistrer la classe') }}
                    </button>
                </div>
                </div>
            </form>

        </div>
    </div>

</div>

@if(auth()->user()->isSuperAdmin())
@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const centerSelect = document.getElementById('center_id');
        const formationSelect = document.getElementById('formation_id');

        function filterFormations() {
            const selectedCenter = centerSelect.value;
            for (let i = 0; i < formationSelect.options.length; i++) {
                const opt = formationSelect.options[i];
                if (!opt.value) continue;
                const optCenter = opt.getAttribute('data-center-id');
                if (!selectedCenter || !optCenter || optCenter === selectedCenter) {
                    opt.style.display = '';
                } else {
                    opt.style.display = 'none';
                    if (opt.selected) {
                        formationSelect.value = '';
                    }
                }
            }
        }

        centerSelect.addEventListener('change', filterFormations);
        if (centerSelect.value) {
            filterFormations();
        }
    });
</script>
@endpush
@endif
@endsection
