@extends('layouts.app')

@section('title', "Modifier la classe {$class->name} ({$class->code}) — " . config('app.name'))
@section('description', "Modification de la classe {$class->name}")

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('classes.index') }}">{{ __('Classes') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('classes.show', $class) }}">{{ $class->code }}</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ __('Modifier') }}</li>
@endsection

@section('content')
<div class="container-fluid px-4 py-4">

    <div class="row justify-content-center">
        <div class="col-lg-9">

            {{-- En-tête --}}
            <div class="d-flex align-items-center justify-content-between mb-4">
                <div>
                    <h1 class="h3 fw-bold mb-0">
                        <i class="bi bi-pencil-square text-primary me-2"></i>{{ __('Modifier la Classe') }}
                    </h1>
                    <p class="text-muted small mb-0 mt-1">
                        {{ __('Code :') }} <strong class="font-monospace text-primary">{{ $class->code }}</strong>
                        &bull; Centre : <strong>{{ $class->center?->name }}</strong>
                    </p>
                </div>
                <div class="d-flex gap-2">
                    <a href="{{ route('classes.show', $class) }}" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left me-1"></i>{{ __('Retour') }}
                    </a>
                </div>
            </div>

            <form method="POST" action="{{ route('classes.update', $class) }}" id="form-edit-class">
                @csrf
                @method('PUT')

                <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                {{-- Bloc 1 : Formation rattachée --}}
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white py-3 border-bottom">
                        <h5 class="card-title mb-0 fw-bold text-primary">
                            <i class="bi bi-diagram-2 me-2"></i>{{ __('1. Formation rattachée') }}
                        </h5>
                    </div>
                    <div class="card-body p-4">
                        <div class="row g-3">
                            <div class="col-md-12">
                                <label for="formation_id" class="form-label fw-semibold">
                                    {{ __('Formation') }} <span class="text-danger">*</span>
                                </label>
                                <select id="formation_id" name="formation_id" class="form-select @error('formation_id') is-invalid @enderror" required>
                                    <option value="">{{ __('-- Sélectionner une formation --') }}</option>
                                    @foreach($formations as $formation)
                                        <option value="{{ $formation->id }}" {{ old('formation_id', $class->formation_id) == $formation->id ? 'selected' : '' }}>
                                            {{ $formation->code }} — {{ $formation->translated('name') }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('formation_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <div class="form-text">{{ __('Seules les formations du centre') }} <strong>{{ $class->center?->name }}</strong> {{ __('sont sélectionnables.') }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Bloc 2 : Informations générales --}}
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white py-3 border-bottom">
                        <h5 class="card-title mb-0 fw-bold text-primary">
                            <i class="bi bi-info-circle me-2"></i>{{ __('2. Informations de la classe') }}
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
                                       value="{{ old('name', $class->name) }}" required>
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
                                       value="{{ old('code', $class->code) }}" required>
                                @error('code')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <div class="form-text">{{ __('Unique au sein de ce centre.') }}</div>
                            </div>

                            <div class="col-md-12">
                                <label for="description" class="form-label fw-semibold">{{ __('Description / Remarques') }}</label>
                                <textarea id="description" name="description" rows="3"
                                          class="form-control @error('description') is-invalid @enderror">{{ old('description', $class->description) }}</textarea>
                                @error('description')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Bloc 3 : Capacité & Période --}}
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white py-3 border-bottom">
                        <h5 class="card-title mb-0 fw-bold text-primary">
                            <i class="bi bi-calendar-event me-2"></i>{{ __('3. Capacité & Période') }}
                        </h5>
                    </div>
                    <div class="card-body p-4">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label for="capacity" class="form-label fw-semibold">{{ __('Capacité maximale') }}</label>
                                <div class="input-group">
                                    <input type="number" id="capacity" name="capacity" min="1" step="1"
                                           class="form-control @error('capacity') is-invalid @enderror"
                                           value="{{ old('capacity', $class->capacity) }}">
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
                                       value="{{ old('start_date', optional($class->start_date)->format('Y-m-d')) }}">
                                @error('start_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-4">
                                <label for="end_date" class="form-label fw-semibold">{{ __('Date de fin') }}</label>
                                <input type="date" id="end_date" name="end_date"
                                       class="form-control @error('end_date') is-invalid @enderror"
                                       value="{{ old('end_date', optional($class->end_date)->format('Y-m-d')) }}">
                                @error('end_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-4">
                                <label for="status" class="form-label fw-semibold">
                                    {{ __('Statut') }} <span class="text-danger">*</span>
                                </label>
                                <select id="status" name="status" class="form-select @error('status') is-invalid @enderror" required>
                                    <option value="active"   {{ old('status', $class->status) === 'active'   ? 'selected' : '' }}>{{ __('Actif') }}</option>
                                    <option value="inactive" {{ old('status', $class->status) === 'inactive' ? 'selected' : '' }}>{{ __('Inactif') }}</option>
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
                    <a href="{{ route('classes.show', $class) }}" class="btn btn-outline-secondary px-4">{{ __('Annuler') }}</a>
                    <button type="submit" class="btn btn-primary px-5 shadow-sm" id="btn-update-class">
                        <i class="bi bi-save me-1"></i>{{ __('Enregistrer les modifications') }}
                    </button>
                </div>
                </div>
            </form>

        </div>
    </div>

</div>
@endsection
