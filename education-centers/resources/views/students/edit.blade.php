@extends('layouts.app')

@section('title', "Modifier le dossier élève {$student->student_number} — " . config('app.name'))
@section('description', "Modification du dossier administratif de {$student->full_name}")

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('students.index') }}">{{ __('Élèves') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('students.show', $student) }}">{{ $student->student_number }}</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ __('Modifier') }}</li>
@endsection

@section('content')
<div class="container-fluid px-4 py-4">

    <div class="row justify-content-center">
        <div class="col-12">

            {{-- En-tête --}}
            <div class="d-flex align-items-center justify-content-between mb-4">
                <div>
                    <h1 class="h3 fw-bold mb-0">
                        <i class="bi bi-pencil-square text-primary me-2"></i>{{ __('Modifier le Dossier Élève') }}
                    </h1>
                    <p class="text-muted small mb-0 mt-1">
                        {{ __('N° Étudiant :') }} <strong class="font-monospace">{{ $student->student_number }}</strong>
                        &bull; Centre : <strong>{{ $student->center?->name }}</strong>
                        &bull; Compte : <strong>{{ $student->user?->email }}</strong>
                    </p>
                </div>
                <div class="d-flex gap-2">
                    <a href="{{ route('students.show', $student) }}" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left me-1"></i>{{ __('Retour') }}
                    </a>
                </div>
            </div>

            <form method="POST" action="{{ route('students.update', $student) }}" id="form-edit-student">
                @csrf
                @method('PUT')

                <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">

                {{-- Bloc 1 : Informations personnelles --}}
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white py-3 border-bottom">
                        <h5 class="card-title mb-0 fw-bold text-primary">
                            <i class="bi bi-person-lines-fill me-2"></i>{{ __('1. Informations personnelles') }}
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
                                       value="{{ old('first_name', $student->first_name) }}" required>
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
                                       value="{{ old('last_name', $student->last_name) }}" required>
                                @error('last_name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-4">
                                <label for="date_of_birth" class="form-label fw-semibold">{{ __('Date de naissance') }}</label>
                                <input type="date" id="date_of_birth" name="date_of_birth"
                                       class="form-control @error('date_of_birth') is-invalid @enderror"
                                       value="{{ old('date_of_birth', optional($student->date_of_birth)->format('Y-m-d')) }}">
                                @error('date_of_birth')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-4">
                                <label for="place_of_birth" class="form-label fw-semibold">{{ __('Lieu de naissance') }}</label>
                                <input type="text" id="place_of_birth" name="place_of_birth"
                                       class="form-control @error('place_of_birth') is-invalid @enderror"
                                       value="{{ old('place_of_birth', $student->place_of_birth) }}" placeholder="{{ __('Ex: Berlin') }}">
                                @error('place_of_birth')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-4">
                                <label for="gender" class="form-label fw-semibold">{{ __('Genre') }}</label>
                                <select id="gender" name="gender" class="form-select @error('gender') is-invalid @enderror">
                                    <option value="">{{ __('Non précisé') }}</option>
                                    <option value="male"   {{ old('gender', $student->gender) === 'male'   ? 'selected' : '' }}>{{ __('Masculin') }}</option>
                                    <option value="female" {{ old('gender', $student->gender) === 'female' ? 'selected' : '' }}>{{ __('Féminin') }}</option>
                                    <option value="other"  {{ old('gender', $student->gender) === 'other'  ? 'selected' : '' }}>{{ __('Autre') }}</option>
                                </select>
                                @error('gender')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6">
                                <label for="nationality" class="form-label fw-semibold">{{ __('Nationalité') }}</label>
                                <input type="text" id="nationality" name="nationality"
                                       class="form-control @error('nationality') is-invalid @enderror"
                                       value="{{ old('nationality', $student->nationality) }}">
                                @error('nationality')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6">
                                <label for="phone" class="form-label fw-semibold">{{ __('Numéro de téléphone') }}</label>
                                <input type="text" id="phone" name="phone"
                                       class="form-control @error('phone') is-invalid @enderror"
                                       value="{{ old('phone', $student->phone) }}" placeholder="+212 XXXXXXXXX">
                                @error('phone')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Bloc 2 : Adresse & Coordonnées --}}
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white py-3 border-bottom">
                        <h5 class="card-title mb-0 fw-bold text-primary">
                            <i class="bi bi-geo-alt me-2"></i>{{ __('2. Coordonnées & Adresse') }}
                        </h5>
                    </div>
                    <div class="card-body p-4">
                        <div class="row g-3">
                            <div class="col-md-12">
                                <label for="address" class="form-label fw-semibold">{{ __('Adresse postale') }}</label>
                                <input type="text" id="address" name="address"
                                       class="form-control @error('address') is-invalid @enderror"
                                       value="{{ old('address', $student->address) }}" placeholder="{{ __('Ex: Friedrichstraße 42') }}">
                                @error('address')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-4">
                                <label for="postal_code" class="form-label fw-semibold">{{ __('Code postal') }}</label>
                                <input type="text" id="postal_code" name="postal_code"
                                       class="form-control @error('postal_code') is-invalid @enderror"
                                       value="{{ old('postal_code', $student->postal_code) }}" placeholder="{{ __('Ex: 10117') }}">
                                @error('postal_code')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-4">
                                <label for="city" class="form-label fw-semibold">{{ __('Ville') }}</label>
                                <input type="text" id="city" name="city"
                                       class="form-control @error('city') is-invalid @enderror"
                                       value="{{ old('city', $student->city) }}" placeholder="{{ __('Ex: Berlin') }}">
                                @error('city')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-4">
                                <label for="country" class="form-label fw-semibold">{{ __('Pays') }}</label>
                                <input type="text" id="country" name="country"
                                       class="form-control @error('country') is-invalid @enderror"
                                       value="{{ old('country', $student->country) }}">
                                @error('country')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Bloc 3 : Contact d'urgence & Notes --}}
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white py-3 border-bottom">
                        <h5 class="card-title mb-0 fw-bold text-primary">
                            <i class="bi bi-telephone-plus me-2"></i>{{ __('3. Contact d\'urgence & Statut') }}
                        </h5>
                    </div>
                    <div class="card-body p-4">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label for="emergency_contact_name" class="form-label fw-semibold">{{ __('Nom du contact') }}</label>
                                <input type="text" id="emergency_contact_name" name="emergency_contact_name"
                                       class="form-control @error('emergency_contact_name') is-invalid @enderror"
                                       value="{{ old('emergency_contact_name', $student->emergency_contact_name) }}">
                                @error('emergency_contact_name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-4">
                                <label for="emergency_contact_phone" class="form-label fw-semibold">{{ __('Téléphone d\'urgence') }}</label>
                                <input type="text" id="emergency_contact_phone" name="emergency_contact_phone"
                                       class="form-control @error('emergency_contact_phone') is-invalid @enderror"
                                       value="{{ old('emergency_contact_phone', $student->emergency_contact_phone) }}" placeholder="+212 XXXXXXXXX">
                                @error('emergency_contact_phone')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-4">
                                <label for="emergency_contact_relation" class="form-label fw-semibold">{{ __('Lien de parenté / relation') }}</label>
                                <input type="text" id="emergency_contact_relation" name="emergency_contact_relation"
                                       class="form-control @error('emergency_contact_relation') is-invalid @enderror"
                                       value="{{ old('emergency_contact_relation', $student->emergency_contact_relation) }}">
                                @error('emergency_contact_relation')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-12">
                                <label for="notes" class="form-label fw-semibold">{{ __('Notes & Observations administratives') }}</label>
                                <textarea id="notes" name="notes" rows="3"
                                          class="form-control @error('notes') is-invalid @enderror">{{ old('notes', $student->notes) }}</textarea>
                                @error('notes')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-4">
                                <label for="status" class="form-label fw-semibold">
                                    {{ __('Statut du dossier') }} <span class="text-danger">*</span>
                                </label>
                                <select id="status" name="status" class="form-select @error('status') is-invalid @enderror" required>
                                    <option value="active"   {{ old('status', $student->status) === 'active'   ? 'selected' : '' }}>{{ __('Actif') }}</option>
                                    <option value="inactive" {{ old('status', $student->status) === 'inactive' ? 'selected' : '' }}>{{ __('Inactif') }}</option>
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
                    <a href="{{ route('students.show', $student) }}" class="btn btn-outline-secondary px-4">{{ __('Annuler') }}</a>
                    <button type="submit" class="btn btn-primary px-5 shadow-sm" id="btn-update-student">
                        <i class="bi bi-save me-1"></i>{{ __('Enregistrer les modifications') }}
                    </button>
                </div>
                </div>
            </form>

        </div>
    </div>

</div>
@endsection
