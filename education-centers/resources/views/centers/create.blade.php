@extends('layouts.app')

@section('title', __('Nouveau centre') . ' — ' . config('app.name'))

@section('breadcrumb')
    <li class="breadcrumb-item">
        <a href="{{ route('centers.index') }}" class="text-decoration-none">{{ __('Centres') }}</a>
    </li>
    <li class="breadcrumb-item active">{{ __('Nouveau centre') }}</li>
@endsection

@section('content')
<div class="container-fluid px-4">
    <div class="row justify-content-center">
        <div class="col-12">

            {{-- En-tête --}}
            <div class="d-flex align-items-center gap-3 mb-4 [direction:ltr]" dir="ltr">
                <x-ui.back-button fallback="{{ route('centers.index') }}" />
                <div class="{{ current_dir() === 'rtl' ? '[direction:rtl] text-end' : '' }}">
                    <h1 class="h3 fw-bold mb-0">
                        <i class="bi bi-plus-circle me-2 text-primary"></i>{{ __('Nouveau centre') }}
                    </h1>
                    <p class="text-muted small mb-0">{{ __('Créez un nouveau centre de formation') }}</p>
                </div>
            </div>

            <form action="{{ route('centers.store') }}" method="POST" enctype="multipart/form-data" novalidate>
                @csrf

                <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                {{-- ── Informations générales ── --}}
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white border-bottom py-3">
                        <h6 class="mb-0 fw-semibold">
                            <i class="bi bi-info-circle me-2 text-primary"></i>{{ __('Informations générales') }}
                        </h6>
                    </div>
                    <div class="card-body p-4">
                        <div class="row g-3">
                            {{-- Nom --}}
                            <div class="col-md-8">
                                <label for="name" class="form-label fw-semibold">
                                    {{ __('Nom du centre') }} <span class="text-danger">*</span>
                                </label>
                                <input type="text"
                                       id="name"
                                       name="name"
                                       value="{{ old('name') }}"
                                       class="form-control @error('name') is-invalid @enderror"
                                       placeholder="{{ __('ex : Centre de Formation Berlin') }}"
                                       required>
                                @error('name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            {{-- Code --}}
                            <div class="col-md-4">
                                <label for="code" class="form-label fw-semibold">
                                    {{ __('Code unique') }} <span class="text-danger">*</span>
                                </label>
                                <input type="text"
                                       id="code"
                                       name="code"
                                       value="{{ old('code') }}"
                                       class="form-control font-monospace text-uppercase @error('code') is-invalid @enderror"
                                       placeholder="{{ __('ex : BERLIN') }}"
                                       maxlength="20"
                                       style="text-transform: uppercase;">
                                @error('code')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @else
                                    <div class="form-text">{{ __('Majuscules, chiffres, - et _ uniquement') }}</div>
                                @enderror
                            </div>

                            {{-- Statut --}}
                            <div class="col-md-4">
                                <label for="status" class="form-label fw-semibold">
                                    {{ __('Statut') }} <span class="text-danger">*</span>
                                </label>
                                <select id="status" name="status"
                                        class="form-select @error('status') is-invalid @enderror">
                                    <option value="active"   {{ old('status', 'active') === 'active'   ? 'selected' : '' }}>{{ __('Actif') }}</option>
                                    <option value="inactive" {{ old('status') === 'inactive' ? 'selected' : '' }}>{{ __('Inactif') }}</option>
                                </select>
                                @error('status')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            {{-- Email --}}
                            <div class="col-md-8">
                                <label for="email" class="form-label fw-semibold">{{ __('Email') }}</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                    <input type="email"
                                           id="email"
                                           name="email"
                                           value="{{ old('email') }}"
                                           class="form-control @error('email') is-invalid @enderror"
                                           placeholder="contact@centre.de">
                                    @error('email')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            {{-- Téléphone --}}
                            <div class="col-md-4">
                                <label for="phone" class="form-label fw-semibold">{{ __('Téléphone') }}</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-telephone"></i></span>
                                    <input type="text"
                                           id="phone"
                                           name="phone"
                                           value="{{ old('phone') }}"
                                           class="form-control @error('phone') is-invalid @enderror"
                                           placeholder="+212 XXXXXXXXX">
                                    @error('phone')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ── Adresse ── --}}
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white border-bottom py-3">
                        <h6 class="mb-0 fw-semibold">
                            <i class="bi bi-geo-alt me-2 text-primary"></i>{{ __('Adresse') }}
                        </h6>
                    </div>
                    <div class="card-body p-4">
                        <div class="row g-3">
                            {{-- Adresse --}}
                            <div class="col-12">
                                <label for="address" class="form-label fw-semibold">{{ __('Adresse') }}</label>
                                <input type="text"
                                       id="address"
                                       name="address"
                                       value="{{ old('address') }}"
                                       class="form-control @error('address') is-invalid @enderror"
                                       placeholder="{{ __('Numéro et nom de rue') }}">
                                @error('address')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            {{-- Code postal --}}
                            <div class="col-md-3">
                                <label for="postal_code" class="form-label fw-semibold">{{ __('Code postal') }}</label>
                                <input type="text"
                                       id="postal_code"
                                       name="postal_code"
                                       value="{{ old('postal_code') }}"
                                       class="form-control @error('postal_code') is-invalid @enderror"
                                       placeholder="10117">
                                @error('postal_code')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            {{-- Ville --}}
                            <div class="col-md-5">
                                <label for="city" class="form-label fw-semibold">{{ __('Ville') }}</label>
                                <input type="text"
                                       id="city"
                                       name="city"
                                       value="{{ old('city') }}"
                                       class="form-control @error('city') is-invalid @enderror"
                                       placeholder="{{ __('Berlin') }}">
                                @error('city')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            {{-- Pays --}}
                            <div class="col-md-4">
                                <label for="country" class="form-label fw-semibold">{{ __('Pays') }}</label>
                                <input type="text"
                                       id="country"
                                       name="country"
                                       value="{{ old('country', 'Germany') }}"
                                       class="form-control @error('country') is-invalid @enderror"
                                       placeholder="{{ __('Germany') }}">
                                @error('country')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ── Logo ── --}}
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white border-bottom py-3">
                        <h6 class="mb-0 fw-semibold">
                            <i class="bi bi-image me-2 text-primary"></i>{{ __('Logo du centre') }}
                        </h6>
                    </div>
                    <div class="card-body p-4">
                        <div class="row g-3 align-items-center">
                            <div class="col-md-8">
                                <label for="logo" class="form-label fw-semibold">{{ __('Image logo') }}</label>
                                <input type="file"
                                       id="logo"
                                       name="logo"
                                       class="form-control @error('logo') is-invalid @enderror"
                                       accept="image/jpeg,image/png,image/webp">
                                @error('logo')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @else
                                    <div class="form-text">
                                        <i class="bi bi-info-circle me-1"></i>
                                        {{ __('Formats acceptés : JPG, PNG, WebP. Taille maximale : 2 Mo.') }}
                                    </div>
                                @enderror
                            </div>
                            <div class="col-md-4 text-center">
                                <div id="logoPreview"
                                     class="border rounded-3 d-flex align-items-center justify-content-center bg-light"
                                     style="height: 100px; width: 100px; margin: auto;">
                                    <i class="bi bi-building text-muted fs-2"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ── Boutons ── --}}
                <div class="d-flex gap-2 justify-content-end mb-5 lg:col-span-2">
                    <a href="{{ route('centers.index') }}" class="btn btn-outline-secondary px-4">
                        {{ __('Annuler') }}
                    </a>
                    <button type="submit" class="btn btn-primary px-4">
                        <i class="bi bi-floppy-fill me-2"></i>{{ __('Enregistrer le centre') }}
                    </button>
                </div>

                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
// Prévisualisation du logo avant upload
document.getElementById('logo').addEventListener('change', function(e) {
    const file = e.target.files[0];
    const preview = document.getElementById('logoPreview');
    if (file && file.type.startsWith('image/')) {
        const reader = new FileReader();
        reader.onload = function(ev) {
            preview.innerHTML = `<img src="${ev.target.result}" alt="${@js(__('Aperçu'))}" style="max-height:96px;max-width:96px;object-fit:contain;border-radius:.375rem;">`;
        };
        reader.readAsDataURL(file);
    } else {
        preview.innerHTML = '<i class="bi bi-building text-muted fs-2"></i>';
    }
});

// Force uppercase sur le code
document.getElementById('code').addEventListener('input', function() {
    const pos = this.selectionStart;
    this.value = this.value.toUpperCase();
    this.setSelectionRange(pos, pos);
});
</script>
@endpush
