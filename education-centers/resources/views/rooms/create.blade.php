@extends('layouts.app')

@section('title', __('Nouvelle Salle') . ' — ' . config('app.name'))
@section('description', __('Création d’une salle de cours'))

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('rooms.index') }}">{{ __('Salles') }}</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ __('Nouvelle salle') }}</li>
@endsection

@section('content')
<div class="container-fluid px-4 py-4">

    <div class="row justify-content-center">
        <div class="col-lg-9">

            {{-- En-tête --}}
            <div class="d-flex align-items-center justify-content-between mb-4">
                <div>
                    <h1 class="h3 fw-bold mb-0">
                        <i class="bi bi-door-open-fill text-primary me-2"></i>{{ __('Nouvelle Salle de Formation') }}
                    </h1>
                    <p class="text-muted small mb-0 mt-1">
                        @if(auth()->user()->isCenterAdmin())
                            {{ __('Centre :') }} <strong>{{ auth()->user()->center?->name }}</strong>
                        @else
                            {{ __('Ajouter une salle de cours dans un centre éducatif.') }}
                        @endif
                    </p>
                </div>
                <a href="{{ route('rooms.index') }}" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i>{{ __('Retour') }}
                </a>
            </div>

            <form method="POST" action="{{ route('rooms.store') }}" id="form-create-room">
                @csrf

                {{-- Bloc 1 : Centre de rattachement (Super Admin seulement) --}}
                @if(auth()->user()->isSuperAdmin())
                    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                    <div class="card shadow-sm border-0 mb-4">
                        <div class="card-header bg-white py-3 border-bottom">
                            <h5 class="card-title mb-0 fw-bold text-primary">
                                <i class="bi bi-building me-2"></i>{{ __('1. Centre de rattachement') }}
                            </h5>
                        </div>
                        <div class="card-body p-4">
                            <div class="row">
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
                                    <div class="form-text">{{ __('Le code de salle doit être unique au sein du centre choisi.') }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                {{-- Bloc 2 : Informations générales --}}
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white py-3 border-bottom">
                        <h5 class="card-title mb-0 fw-bold text-primary">
                            <i class="bi bi-info-circle me-2"></i>{{ auth()->user()->isSuperAdmin() ? '2.' : '1.' }} {{ __('Informations générales') }}
                        </h5>
                    </div>
                    <div class="card-body p-4">
                        <div class="row g-3">
                            <div class="col-md-8">
                                <label for="name" class="form-label fw-semibold">
                                    {{ __('Nom de la salle') }} <span class="text-danger">*</span>
                                </label>
                                <input type="text" id="name" name="name"
                                       class="form-control @error('name') is-invalid @enderror"
                                       value="{{ old('name') }}" placeholder="{{ __('Ex: Salle Berlin 01') }}" required>
                                @error('name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-4">
                                <label for="code" class="form-label fw-semibold">
                                    {{ __('Code de salle') }} <span class="text-danger">*</span>
                                </label>
                                <input type="text" id="code" name="code"
                                       class="form-control @error('code') is-invalid @enderror text-uppercase"
                                       value="{{ old('code') }}" placeholder="{{ __('Ex: ROOM-01') }}" required>
                                @error('code')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <div class="form-text">{{ __('Unique au sein du centre.') }}</div>
                            </div>

                            <div class="col-md-4">
                                <label for="capacity" class="form-label fw-semibold">
                                    {{ __('Capacité d\'accueil') }} <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <input type="number" id="capacity" name="capacity" min="1" step="1"
                                           class="form-control @error('capacity') is-invalid @enderror"
                                           value="{{ old('capacity', 20) }}" required>
                                    <span class="input-group-text">{{ __('places') }}</span>
                                </div>
                                @error('capacity')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-4">
                                <label for="building" class="form-label fw-semibold">{{ __('Bâtiment') }}</label>
                                <input type="text" id="building" name="building"
                                       class="form-control @error('building') is-invalid @enderror"
                                       value="{{ old('building') }}" placeholder="{{ __('Ex: Bâtiment A') }}">
                                @error('building')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-4">
                                <label for="floor" class="form-label fw-semibold">{{ __('Étage') }}</label>
                                <input type="text" id="floor" name="floor"
                                       class="form-control @error('floor') is-invalid @enderror"
                                       value="{{ old('floor') }}" placeholder="{{ __('Ex: 2ème étage') }}">
                                @error('floor')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-12">
                                <label for="equipment" class="form-label fw-semibold">{{ __('Équipements & Matériel') }}</label>
                                <input type="text" id="equipment" name="equipment"
                                       class="form-control @error('equipment') is-invalid @enderror"
                                       value="{{ old('equipment') }}" placeholder="{{ __('Ex: Vidéoprojecteur, 15 PC, Tableau blanc interactif...') }}">
                                @error('equipment')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-12">
                                <label for="description" class="form-label fw-semibold">{{ __('Description / Remarques') }}</label>
                                <textarea id="description" name="description" rows="3"
                                          class="form-control @error('description') is-invalid @enderror"
                                          placeholder="{{ __('Caractéristiques complémentaires, accès PMR...') }}">{{ old('description') }}</textarea>
                                @error('description')
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
                    <a href="{{ route('rooms.index') }}" class="btn btn-outline-secondary px-4">{{ __('Annuler') }}</a>
                    <button type="submit" class="btn btn-primary px-5 shadow-sm" id="btn-submit-room">
                        <i class="bi bi-check-circle-fill me-1"></i>{{ __('Enregistrer la salle') }}
                    </button>
                </div>
                    </div>
            </form>

        </div>
    </div>

</div>
@endsection
