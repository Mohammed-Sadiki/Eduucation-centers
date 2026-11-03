@extends('layouts.app')

@section('title', __('Nouveau dossier professeur') . ' — ' . config('app.name'))
@section('description', __('Création d’un dossier administratif professeur'))

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('teachers.index') }}">{{ __('Professeurs') }}</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ __('Nouveau dossier') }}</li>
@endsection

@section('content')
<div class="container-fluid px-4 py-4">

    <div class="row justify-content-center">
        <div class="col-12">

            {{-- En-tête --}}
            <div class="d-flex align-items-center justify-content-between mb-4">
                <div>
                    <h1 class="h3 fw-bold mb-0">
                        <i class="bi bi-person-plus-fill text-primary me-2"></i>{{ __('Nouveau Dossier Professeur') }}
                    </h1>
                    <p class="text-muted small mb-0 mt-1">
                        {{ __('Associez un compte utilisateur enseignant à un dossier administratif.') }}
                    </p>
                </div>
                <a href="{{ route('teachers.index') }}" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i>{{ __('Retour') }}
                </a>
            </div>

            @if($availableUsers->isEmpty())
                <div class="alert alert-warning border-0 shadow-sm mb-4">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-exclamation-triangle-fill fs-3 me-3 text-warning"></i>
                        <div>
                            <h5 class="alert-heading mb-1 fw-bold">{{ __('Aucun compte professeur disponible') }}</h5>
                            <p class="mb-0 small">
                                {{ __('Tous les comptes ayant le rôle') }} <em>{{ __('Professeur (teacher)') }}</em> {{ __('possèdent déjà un dossier professeur, ou aucun compte enseignant n\'a été créé dans votre périmètre.') }}
                            </p>
                            @can('create', \App\Models\User::class)
                                <a href="{{ route('users.create') }}" class="btn btn-warning btn-sm mt-2 fw-semibold">
                                    <i class="bi bi-person-plus-fill me-1"></i>{{ __('Créer un compte utilisateur d\'abord') }}
                                </a>
                            @endcan
                        </div>
                    </div>
                </div>
            @endif

            <form method="POST" action="{{ route('teachers.store') }}" id="form-create-teacher">
                @csrf

                <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">

                {{-- Bloc 1 : Rattachement Compte Utilisateur & Centre --}}
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white py-3 border-bottom">
                        <h5 class="card-title mb-0 fw-bold text-primary">
                            <i class="bi bi-person-badge me-2"></i>{{ __('1. Rattachement du compte') }}
                        </h5>
                    </div>
                    <div class="card-body p-4">
                        <div class="row g-3">
                            {{-- Sélection du compte enseignant --}}
                            <div class="col-md-{{ auth()->user()->isSuperAdmin() ? '6' : '12' }}">
                                <label for="user_id" class="form-label fw-semibold">
                                    {{ __('Compte utilisateur professeur') }} <span class="text-danger">*</span>
                                </label>
                                <select id="user_id" name="user_id" class="form-select @error('user_id') is-invalid @enderror" required>
                                    <option value="">{{ __('-- Sélectionner un compte professeur --') }}</option>
                                    @foreach($availableUsers as $user)
                                        <option value="{{ $user->id }}"
                                                data-center-id="{{ $user->center_id }}"
                                                data-name="{{ $user->name }}"
                                                data-phone="{{ $user->phone }}"
                                                {{ old('user_id') == $user->id ? 'selected' : '' }}>
                                            {{ $user->name }} ({{ $user->email }})
                                            @if(auth()->user()->isSuperAdmin() && $user->center)
                                                — [{{ $user->center->name }}]
                                            @endif
                                        </option>
                                    @endforeach
                                </select>
                                @error('user_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <div class="form-text">
                                    {{ __('Seuls les utilisateurs avec le rôle') }} <em>{{ __('Professeur') }}</em> {{ __('sans dossier existant sont listés.') }}
                                </div>
                            </div>

                            {{-- Super Admin : sélection du centre d'affectation --}}
                            @if(auth()->user()->isSuperAdmin())
                                <div class="col-md-6">
                                    <label for="center_id" class="form-label fw-semibold">
                                        {{ __('Centre éducatif') }} <span class="text-danger">*</span>
                                    </label>
                                    <select id="center_id" name="center_id" class="form-select @error('center_id') is-invalid @enderror" required>
                                        <option value="">{{ __('-- Sélectionner le centre --') }}</option>
                                        @foreach($centers as $center)
                                            <option value="{{ $center->id }}" {{ old('center_id') == $center->id ? 'selected' : '' }}>
                                                {{ $center->translated('name') }} ({{ $center->code }})
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('center_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    <div class="form-text">
                                        {{ __('L\'identifiant professeur sera automatiquement préfixé (ex: BER-T-000001).') }}
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Bloc 2 : Informations personnelles --}}
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white py-3 border-bottom">
                        <h5 class="card-title mb-0 fw-bold text-primary">
                            <i class="bi bi-person-lines-fill me-2"></i>{{ __('2. Informations personnelles') }}
                        </h5>
                    </div>
                    <div class="card-body p-4">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="first_name" class="form-label fw-semibold">
                                    {{ __('Prénom') }} <span class="text-danger">*</span>
                                </label>
                                <input type="text" id="first_name" name="first_name"
                                       class="form-control @error('first_name') is-invalid @enderror"
                                       value="{{ old('first_name') }}" required>
                                @error('first_name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6">
                                <label for="last_name" class="form-label fw-semibold">
                                    {{ __('Nom') }} <span class="text-danger">*</span>
                                </label>
                                <input type="text" id="last_name" name="last_name"
                                       class="form-control @error('last_name') is-invalid @enderror"
                                       value="{{ old('last_name') }}" required>
                                @error('last_name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-4">
                                <label for="date_of_birth" class="form-label fw-semibold">{{ __('Date de naissance') }}</label>
                                <input type="date" id="date_of_birth" name="date_of_birth"
                                       class="form-control @error('date_of_birth') is-invalid @enderror"
                                       value="{{ old('date_of_birth') }}">
                                @error('date_of_birth')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-4">
                                <label for="place_of_birth" class="form-label fw-semibold">{{ __('Lieu de naissance') }}</label>
                                <input type="text" id="place_of_birth" name="place_of_birth"
                                       class="form-control @error('place_of_birth') is-invalid @enderror"
                                       value="{{ old('place_of_birth') }}" placeholder="{{ __('Ex: Munich') }}">
                                @error('place_of_birth')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-4">
                                <label for="gender" class="form-label fw-semibold">{{ __('Genre') }}</label>
                                <select id="gender" name="gender" class="form-select @error('gender') is-invalid @enderror">
                                    <option value="">{{ __('Non précisé') }}</option>
                                    <option value="male"   {{ old('gender') === 'male'   ? 'selected' : '' }}>{{ __('Masculin') }}</option>
                                    <option value="female" {{ old('gender') === 'female' ? 'selected' : '' }}>{{ __('Féminin') }}</option>
                                    <option value="other"  {{ old('gender') === 'other'  ? 'selected' : '' }}>{{ __('Autre') }}</option>
                                </select>
                                @error('gender')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6">
                                <label for="nationality" class="form-label fw-semibold">{{ __('Nationalité') }}</label>
                                <input type="text" id="nationality" name="nationality"
                                       class="form-control @error('nationality') is-invalid @enderror"
                                       value="{{ old('nationality', 'Allemande') }}">
                                @error('nationality')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6">
                                <label for="phone" class="form-label fw-semibold">{{ __('Numéro de téléphone') }}</label>
                                <input type="text" id="phone" name="phone"
                                       class="form-control @error('phone') is-invalid @enderror"
                                       value="{{ old('phone') }}" placeholder="+212 XXXXXXXXX">
                                @error('phone')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Bloc 3 : Informations professionnelles --}}
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white py-3 border-bottom">
                        <h5 class="card-title mb-0 fw-bold text-primary">
                            <i class="bi bi-briefcase me-2"></i>{{ __('3. Informations professionnelles') }}
                        </h5>
                    </div>
                    <div class="card-body p-4">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label for="specialization" class="form-label fw-semibold">{{ __('Spécialisation / Discipline') }}</label>
                                <input type="text" id="specialization" name="specialization"
                                       class="form-control @error('specialization') is-invalid @enderror"
                                       value="{{ old('specialization') }}" placeholder="{{ __('Ex: Mathématiques, Informatique') }}">
                                @error('specialization')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-4">
                                <label for="qualification" class="form-label fw-semibold">{{ __('Diplôme / Qualification') }}</label>
                                <input type="text" id="qualification" name="qualification"
                                       class="form-control @error('qualification') is-invalid @enderror"
                                       value="{{ old('qualification') }}" placeholder="{{ __('Ex: Master en Pédagogie') }}">
                                @error('qualification')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-4">
                                <label for="hire_date" class="form-label fw-semibold">{{ __('Date d\'embauche') }}</label>
                                <input type="date" id="hire_date" name="hire_date"
                                       class="form-control @error('hire_date') is-invalid @enderror"
                                       value="{{ old('hire_date') }}">
                                @error('hire_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Bloc 4 : Coordonnées postales & Statut --}}
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white py-3 border-bottom">
                        <h5 class="card-title mb-0 fw-bold text-primary">
                            <i class="bi bi-geo-alt me-2"></i>{{ __('4. Coordonnées & Statut') }}
                        </h5>
                    </div>
                    <div class="card-body p-4">
                        <div class="row g-3">
                            <div class="col-md-12">
                                <label for="address" class="form-label fw-semibold">{{ __('Adresse postale') }}</label>
                                <input type="text" id="address" name="address"
                                       class="form-control @error('address') is-invalid @enderror"
                                       value="{{ old('address') }}" placeholder="{{ __('Ex: Unter den Linden 10') }}">
                                @error('address')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-4">
                                <label for="postal_code" class="form-label fw-semibold">{{ __('Code postal') }}</label>
                                <input type="text" id="postal_code" name="postal_code"
                                       class="form-control @error('postal_code') is-invalid @enderror"
                                       value="{{ old('postal_code') }}" placeholder="{{ __('Ex: 10117') }}">
                                @error('postal_code')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-4">
                                <label for="city" class="form-label fw-semibold">{{ __('Ville') }}</label>
                                <input type="text" id="city" name="city"
                                       class="form-control @error('city') is-invalid @enderror"
                                       value="{{ old('city') }}" placeholder="{{ __('Ex: Berlin') }}">
                                @error('city')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-4">
                                <label for="country" class="form-label fw-semibold">{{ __('Pays') }}</label>
                                <input type="text" id="country" name="country"
                                       class="form-control @error('country') is-invalid @enderror"
                                       value="{{ old('country', 'Germany') }}">
                                @error('country')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-12">
                                <label for="notes" class="form-label fw-semibold">{{ __('Notes & Observations administratives') }}</label>
                                <textarea id="notes" name="notes" rows="3"
                                          class="form-control @error('notes') is-invalid @enderror"
                                          placeholder="{{ __('Notes internes éventuelles...') }}">{{ old('notes') }}</textarea>
                                @error('notes')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-4">
                                <label for="status" class="form-label fw-semibold">
                                    {{ __('Statut initial du dossier') }} <span class="text-danger">*</span>
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

                {{-- Boutons d'action --}}
                <div class="d-flex justify-content-end gap-3 mb-5 lg:col-span-2">
                    <a href="{{ route('teachers.index') }}" class="btn btn-outline-secondary px-4">{{ __('Annuler') }}</a>
                    <button type="submit" class="btn btn-primary px-5 shadow-sm" id="btn-submit-teacher">
                        <i class="bi bi-check-circle-fill me-1"></i>{{ __('Enregistrer le dossier professeur') }}
                    </button>
                </div>
                </div>
            </form>

        </div>
    </div>

</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const userSelect = document.getElementById('user_id');
        const centerSelect = document.getElementById('center_id');
        const firstNameInput = document.getElementById('first_name');
        const lastNameInput = document.getElementById('last_name');
        const phoneInput = document.getElementById('phone');

        userSelect.addEventListener('change', function () {
            const selected = userSelect.options[userSelect.selectedIndex];
            if (!selected || !selected.value) return;

            const fullName = selected.getAttribute('data-name') || '';
            const userPhone = selected.getAttribute('data-phone') || '';
            const centerId = selected.getAttribute('data-center-id');

            if (fullName && (!firstNameInput.value && !lastNameInput.value)) {
                const parts = fullName.trim().split(' ');
                if (parts.length > 1) {
                    firstNameInput.value = parts[0];
                    lastNameInput.value = parts.slice(1).join(' ');
                } else {
                    firstNameInput.value = parts[0];
                }
            }

            if (userPhone && !phoneInput.value) {
                phoneInput.value = userPhone;
            }

            if (centerSelect && centerId) {
                centerSelect.value = centerId;
            }
        });
    });
</script>
@endpush
@endsection
