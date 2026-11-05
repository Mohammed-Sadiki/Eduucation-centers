@extends('layouts.app')

@section('title', __('Modifier') . ' ' . $user->name . ' — ' . config('app.name'))

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('users.index') }}" class="text-decoration-none">{{ __('Utilisateurs') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('users.show', $user) }}" class="text-decoration-none">{{ $user->name }}</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ __('Modifier') }}</li>
@endsection

@section('content')
<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-8">

            <div class="card shadow-sm border-0">
                <div class="card-header bg-white border-bottom py-3">
                    <div class="d-flex align-items-center gap-3 [direction:ltr]" dir="ltr">
                        <x-ui.back-button :fallback="route('users.show', $user)" />
                        <div class="{{ current_dir() === 'rtl' ? '[direction:rtl] text-end' : '' }}">
                            <h4 class="fw-bold mb-0">
                                <i class="bi bi-person-gear text-primary me-2"></i>{{ __('Modifier') }} {{ $user->name }}
                            </h4>
                            @if(auth()->user()->isCenterAdmin())
                                <p class="text-muted small mb-0 mt-1">
                                    {{ __('Centre :') }} <strong>{{ auth()->user()->center?->name }}</strong>
                                </p>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="card-body p-4">
                    <form method="POST" action="{{ route('users.update', $user) }}" id="form-edit-user" novalidate>
                        @csrf @method('PUT')

                        <div class="row g-3">

                            {{-- Nom --}}
                            <div class="col-md-6">
                                <label for="name" class="form-label fw-semibold small text-secondary">
                                    <i class="bi bi-person me-1"></i>{{ __('Nom complet') }} <span class="text-danger">*</span>
                                </label>
                                <input type="text" id="name" name="name"
                                       class="form-control @error('name') is-invalid @enderror"
                                       value="{{ old('name', $user->name) }}" required>
                                @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            {{-- Email --}}
                            <div class="col-md-6">
                                <label for="email" class="form-label fw-semibold small text-secondary">
                                    <i class="bi bi-envelope me-1"></i>{{ __('Adresse e-mail') }} <span class="text-danger">*</span>
                                </label>
                                <input type="email" id="email" name="email"
                                       class="form-control @error('email') is-invalid @enderror"
                                       value="{{ old('email', $user->email) }}" required>
                                @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            {{-- Téléphone --}}
                            <div class="col-md-6">
                                <label for="phone" class="form-label fw-semibold small text-secondary">
                                    <i class="bi bi-telephone me-1"></i>{{ __('Téléphone') }}
                                </label>
                                <input type="text" id="phone" name="phone"
                                       class="form-control @error('phone') is-invalid @enderror"
                                       value="{{ old('phone', $user->phone) }}" placeholder="+212 XXXXXXXXX">
                                @error('phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            {{-- Rôle --}}
                            <div class="col-md-6">
                                <label for="role" class="form-label fw-semibold small text-secondary">
                                    <i class="bi bi-shield me-1"></i>{{ __('Rôle') }} <span class="text-danger">*</span>
                                </label>
                                <select id="role" name="role"
                                        class="form-select @error('role') is-invalid @enderror" required>
                                    @foreach($allowedRoles as $value => $label)
                                        <option value="{{ $value }}" {{ old('role', $user->role) === $value ? 'selected' : '' }}>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('role') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            {{-- Centre (Super Admin uniquement) --}}
                            @if(auth()->user()->isSuperAdmin())
                                <div class="col-md-6">
                                    <label for="center_id" class="form-label fw-semibold small text-secondary">
                                        <i class="bi bi-building me-1"></i>{{ __('Centre') }}
                                    </label>
                                    <select id="center_id" name="center_id"
                                            class="form-select @error('center_id') is-invalid @enderror">
                                        <option value="">{{ __('— Sans centre (Super Admin) —') }}</option>
                                        @foreach($centers as $center)
                                            <option value="{{ $center->id }}"
                                                {{ old('center_id', $user->center_id) == $center->id ? 'selected' : '' }}>
                                                {{ $center->translated('name') }} ({{ $center->city }})
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('center_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            @else
                                {{-- Admin Centre voit son propre centre en lecture seule --}}
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold small text-secondary">
                                        <i class="bi bi-building me-1"></i>{{ __('Centre') }}
                                    </label>
                                    <input type="text" class="form-control bg-light"
                                           value="{{ auth()->user()->center?->name }}" readonly>
                                </div>
                            @endif

                            {{-- Statut --}}
                            <div class="col-md-6">
                                <label for="status" class="form-label fw-semibold small text-secondary">
                                    <i class="bi bi-toggle-on me-1"></i>{{ __('Statut') }} <span class="text-danger">*</span>
                                </label>
                                <select id="status" name="status"
                                        class="form-select @error('status') is-invalid @enderror" required>
                                    <option value="active"   {{ old('status', $user->status) === 'active'   ? 'selected' : '' }}>{{ __('Actif') }}</option>
                                    <option value="inactive" {{ old('status', $user->status) === 'inactive' ? 'selected' : '' }}>{{ __('Inactif') }}</option>
                                </select>
                                @error('status') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            {{-- Formation enseignée (sélection directe, sans domaine) --}}
                            <div class="col-md-6">
                                <label for="edit_formation_id" class="form-label fw-semibold small text-secondary">
                                    <i class="bi bi-book me-1"></i>{{ __('Formation') }}
                                </label>
                                <select id="edit_formation_id" name="formation_id" class="form-select @error('formation_id') is-invalid @enderror">
                                    <option value="">{{ __('— Sélectionner la formation —') }}</option>
                                    @php
                                        $userFormationId = old('formation_id', $user->formation_id ?: $user->teacher?->formation_id);
                                    @endphp
                                    @foreach($formationsData as $formation)
                                        <option value="{{ $formation['id'] }}"
                                                data-center-id="{{ $formation['center_id'] }}"
                                                @selected($userFormationId == $formation['id'])>
                                            {{ $formation['name'] }} ({{ $formation['code'] }})
                                        </option>
                                    @endforeach
                                </select>
                                @error('formation_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-12"><hr class="my-1"></div>

                            {{-- Mot de passe (facultatif) --}}
                            <div class="col-12">
                                <div class="alert alert-info small py-2 mb-2">
                                    <i class="bi bi-info-circle me-1"></i>
                                    {{ __('Laissez le mot de passe vide pour conserver l\'actuel.') }}
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label for="password" class="form-label fw-semibold small text-secondary">
                                    <i class="bi bi-lock me-1"></i>{{ __('Nouveau mot de passe') }}
                                </label>
                                <input type="password" id="password" name="password"
                                       class="form-control @error('password') is-invalid @enderror"
                                       placeholder="{{ __('Laisser vide = conserver l\'actuel') }}" autocomplete="new-password">
                                @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-6">
                                <label for="password_confirmation" class="form-label fw-semibold small text-secondary">
                                    <i class="bi bi-lock-fill me-1"></i>{{ __('Confirmer le mot de passe') }}
                                </label>
                                <input type="password" id="password_confirmation" name="password_confirmation"
                                       class="form-control" placeholder="{{ __('Répéter si nouveau mot de passe') }}">
                            </div>

                        </div>

                        <div class="d-flex gap-2 mt-4 pt-2 border-top">
                            <button type="submit" class="btn btn-primary shadow-sm">
                                <i class="bi bi-check-lg me-1"></i>{{ __('Enregistrer les modifications') }}
                            </button>
                            <a href="{{ route('users.show', $user) }}" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-left me-1"></i>{{ __('Annuler') }}
                            </a>
                        </div>

                    </form>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const formationSelect = document.getElementById('edit_formation_id');
    const centerSelect = document.getElementById('center_id');

    // Filtre par centre uniquement. Aucune étape Domaine : le catalogue est
    // limité aux six formations générales officielles du centre.
    function applyEditCascade(isInitial = false) {
        if (!formationSelect) return;

        const currentCenter = centerSelect ? centerSelect.value : '{{ $user->center_id }}';
        let count = 0;
        let selectedFound = false;

        formationSelect.querySelectorAll('option[data-center-id]').forEach(function (option) {
            const match = !currentCenter || option.dataset.centerId === currentCenter;

            option.hidden = !match;
            option.disabled = !match;

            if (match) {
                count++;
                if (option.selected) {
                    selectedFound = true;
                }
            }
        });

        if (!isInitial && !selectedFound) {
            formationSelect.value = '';
        }
    }

    if (centerSelect) {
        centerSelect.addEventListener('change', function () { applyEditCascade(false); });
    }

    applyEditCascade(true);
});
</script>
@endsection
