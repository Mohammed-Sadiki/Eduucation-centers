@extends('layouts.app')

@section('title', "Modifier la salle {$room->name} ({$room->code}) — " . config('app.name'))
@section('description', "Modification de la salle {$room->name}")

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('rooms.index') }}">{{ __('Salles') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('rooms.show', $room) }}">{{ $room->code }}</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ __('Modifier') }}</li>
@endsection

@section('content')
<div class="container-fluid px-4 py-4">

    <div class="row justify-content-center">
        <div class="col-lg-9">

            {{-- En-tête --}}
            <div class="d-flex align-items-center justify-content-between mb-4">
                <div class="d-flex align-items-center gap-3 [direction:ltr]" dir="ltr">
                    <x-ui.back-button :fallback="route('rooms.show', $room)" />
                    <div class="{{ current_dir() === 'rtl' ? '[direction:rtl] text-end' : '' }}">
                        <h1 class="h3 fw-bold mb-0">
                            <i class="bi bi-pencil-square text-primary me-2"></i>{{ __('Modifier la Salle') }}
                        </h1>
                        <p class="text-muted small mb-0 mt-1">
                            {{ __('Code :') }} <strong class="font-monospace text-primary">{{ $room->code }}</strong>
                            &bull; {{ __('Centre :') }} <strong>{{ $room->center?->name }}</strong>
                        </p>
                    </div>
                </div>
            </div>

            <form method="POST" action="{{ route('rooms.update', $room) }}" id="form-edit-room">
                @csrf
                @method('PUT')

                {{-- Bloc : Informations générales --}}
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white py-3 border-bottom">
                        <h5 class="card-title mb-0 fw-bold text-primary">
                            <i class="bi bi-info-circle me-2"></i>{{ __('Informations de la salle') }}
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
                                       value="{{ old('name', $room->name) }}" required>
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
                                       value="{{ old('code', $room->code) }}" required>
                                @error('code')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <div class="form-text">{{ __('Unique au sein de ce centre.') }}</div>
                            </div>

                            <div class="col-md-4">
                                <label for="capacity" class="form-label fw-semibold">
                                    {{ __('Capacité d\'accueil') }} <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <input type="number" id="capacity" name="capacity" min="1" step="1"
                                           class="form-control @error('capacity') is-invalid @enderror"
                                           value="{{ old('capacity', $room->capacity) }}" required>
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
                                       value="{{ old('building', $room->building) }}">
                                @error('building')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-4">
                                <label for="floor" class="form-label fw-semibold">{{ __('Étage') }}</label>
                                <input type="text" id="floor" name="floor"
                                       class="form-control @error('floor') is-invalid @enderror"
                                       value="{{ old('floor', $room->floor) }}">
                                @error('floor')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-12">
                                <label for="equipment" class="form-label fw-semibold">{{ __('Équipements & Matériel') }}</label>
                                <input type="text" id="equipment" name="equipment"
                                       class="form-control @error('equipment') is-invalid @enderror"
                                       value="{{ old('equipment', $room->equipment) }}">
                                @error('equipment')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-12">
                                <label for="description" class="form-label fw-semibold">{{ __('Description / Remarques') }}</label>
                                <textarea id="description" name="description" rows="3"
                                          class="form-control @error('description') is-invalid @enderror">{{ old('description', $room->description) }}</textarea>
                                @error('description')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-4">
                                <label for="status" class="form-label fw-semibold">
                                    {{ __('Statut') }} <span class="text-danger">*</span>
                                </label>
                                <select id="status" name="status" class="form-select @error('status') is-invalid @enderror" required>
                                    <option value="active"   {{ old('status', $room->status) === 'active'   ? 'selected' : '' }}>{{ __('Actif') }}</option>
                                    <option value="inactive" {{ old('status', $room->status) === 'inactive' ? 'selected' : '' }}>{{ __('Inactif') }}</option>
                                </select>
                                @error('status')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Actions --}}
                <div class="d-flex justify-content-end gap-3 mb-5">
                    <a href="{{ route('rooms.show', $room) }}" class="btn btn-outline-secondary px-4">{{ __('Annuler') }}</a>
                    <button type="submit" class="btn btn-primary px-5 shadow-sm" id="btn-update-room">
                        <i class="bi bi-save me-1"></i>{{ __('Enregistrer les modifications') }}
                    </button>
                </div>
            </form>

        </div>
    </div>

</div>
@endsection
