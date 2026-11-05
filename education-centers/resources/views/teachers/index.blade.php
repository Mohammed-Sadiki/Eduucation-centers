@extends('layouts.app')

@section('title', __('Dossiers Professeurs') . ' — ' . config('app.name'))
@section('description', __('Gestion des dossiers administratifs du corps professoral'))

@section('breadcrumb')
    <li class="breadcrumb-item active" aria-current="page">{{ __('Professeurs') }}</li>
@endsection

@section('content')
<div class="container-fluid px-4 py-4">

    {{-- En-tête --}}
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div class="d-flex align-items-center gap-3 [direction:ltr]" dir="ltr">
            <x-ui.back-button />
            <div class="{{ current_dir() === 'rtl' ? '[direction:rtl] text-end' : '' }}">
                <h1 class="h3 fw-bold mb-0">
                    <i class="bi bi-person-workspace text-primary me-2"></i>{{ __('Dossiers Professeurs') }}
                </h1>
                <p class="text-muted small mb-0 mt-1">
                    @if(auth()->user()->isSuperAdmin())
                        {{ __('Tous les centres —') }} {{ $teachers->total() }} {{ __('dossier(s) professeur(s) trouvé(s)') }}
                    @else
                        {{ __('Centre :') }} <strong>{{ auth()->user()->center?->name }}</strong> — {{ $teachers->total() }} {{ __('dossier(s) trouvé(s)') }}
                    @endif
                </p>
            </div>
        </div>
        @can('create', \App\Models\Teacher::class)
            <a href="{{ route('teachers.create') }}" class="btn btn-primary shadow-sm" id="btn-create-teacher">
                <i class="bi bi-person-plus-fill me-1"></i>{{ __('Nouveau dossier professeur') }}
            </a>
        @endcan
    </div>

    {{-- Filtres --}}
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body py-3">
            <form method="GET" action="{{ route('teachers.index') }}" id="form-filter-teachers">
                <div class="row g-3 align-items-end">
                    {{-- Numéro professeur --}}
                    <div class="col-md-2">
                        <label for="teacher_number" class="form-label small fw-semibold text-secondary mb-1">
                            <i class="bi bi-hash me-1"></i>{{ __('N° Professeur') }}
                        </label>
                        <input type="text" id="teacher_number" name="teacher_number" class="form-control"
                               placeholder="{{ __('Ex: BER-T-000001') }}" value="{{ request('teacher_number') }}">
                    </div>

                    {{-- Recherche nom / prénom --}}
                    <div class="col-md-3">
                        <label for="search" class="form-label small fw-semibold text-secondary mb-1">
                            <i class="bi bi-search me-1"></i>{{ __('Nom ou prénom') }}
                        </label>
                        <input type="text" id="search" name="search" class="form-control"
                               placeholder="{{ __('Rechercher par nom...') }}" value="{{ request('search') }}">
                    </div>

                    {{-- Filtre email --}}
                    <div class="col-md-2">
                        <label for="email" class="form-label small fw-semibold text-secondary mb-1">
                            <i class="bi bi-envelope me-1"></i>{{ __('Email') }}
                        </label>
                        <input type="text" id="email" name="email" class="form-control"
                               placeholder="email@domaine.de" value="{{ request('email') }}">
                    </div>

                    {{-- Filtre statut --}}
                    <div class="col-md-2">
                        <label for="status" class="form-label small fw-semibold text-secondary mb-1">{{ __('Statut') }}</label>
                        <select id="status" name="status" class="form-select">
                            <option value="">{{ __('Tous les statuts') }}</option>
                            <option value="active"   {{ request('status') === 'active'   ? 'selected' : '' }}>{{ __('Actif') }}</option>
                            <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>{{ __('Inactif') }}</option>
                        </select>
                    </div>

                    {{-- Filtre centre (Super Admin seulement) --}}
                    @if(auth()->user()->isSuperAdmin())
                        <div class="col-md-2">
                            <label for="center_id" class="form-label small fw-semibold text-secondary mb-1">
                                <i class="bi bi-building me-1"></i>{{ __('Centre') }}
                            </label>
                            <select id="center_id" name="center_id" class="form-select">
                                <option value="">{{ __('Tous les centres') }}</option>
                                @foreach($centers as $center)
                                    <option value="{{ $center->id }}" {{ request('center_id') == $center->id ? 'selected' : '' }}>
                                        {{ $center->translated('name') }} ({{ $center->code }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    {{-- Actions filtre --}}
                    <div class="col-md-1 d-flex gap-2">
                        <button type="submit" class="btn btn-outline-primary w-100" title="{{ __('Filtrer') }}">
                            <i class="bi bi-funnel"></i>
                        </button>
                        @if(request()->hasAny(['teacher_number', 'search', 'email', 'status', 'center_id']))
                            <a href="{{ route('teachers.index') }}" class="btn btn-outline-secondary" title="{{ __('Réinitialiser') }}">
                                <i class="bi bi-x-lg"></i>
                            </a>
                        @endif
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- Liste des professeurs --}}
    <div class="card shadow-sm border-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">{{ __('N° Professeur') }}</th>
                        <th>{{ __('Nom & Prénom') }}</th>
                        <th>{{ __('Spécialisation') }}</th>
                        <th>{{ __('Centre') }}</th>
                        <th>{{ __('Compte Utilisateur') }}</th>
                        <th>{{ __('Téléphone') }}</th>
                        <th>{{ __('Statut') }}</th>
                        <th class="text-end pe-4">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($teachers as $teacher)
                        <tr>
                            <td class="ps-4">
                                <a href="{{ route('teachers.show', $teacher) }}" class="fw-bold text-decoration-none font-monospace">
                                    {{ $teacher->teacher_number }}
                                </a>
                            </td>
                            <td>
                                <div class="fw-semibold text-dark">{{ $teacher->full_name }}</div>
                                @if($teacher->qualification)
                                    <small class="text-muted">{{ $teacher->qualification }}</small>
                                @endif
                            </td>
                            <td>
                                @if($teacher->specialization)
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
                                        {{ $teacher->specialization }}
                                    </span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>
                                @if($teacher->center)
                                    <span class="badge bg-light text-dark border">
                                        <i class="bi bi-building me-1"></i>{{ $teacher->center->name }}
                                    </span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>
                                @if($teacher->user)
                                    <div><i class="bi bi-person me-1 text-secondary"></i>{{ $teacher->user->name }}</div>
                                    <small class="text-muted">{{ $teacher->user->email }}</small>
                                @else
                                    <span class="text-danger small"><i class="bi bi-exclamation-triangle me-1"></i>{{ __('Sans compte') }}</span>
                                @endif
                            </td>
                            <td>
                                <bdi dir="ltr">{{ $teacher->phone ?? '-' }}</bdi>
                            </td>
                            <td>
                                @if($teacher->isActive())
                                    <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                                        <i class="bi bi-check-circle-fill me-1"></i>{{ __('Actif') }}
                                    </span>
                                @else
                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1">
                                        <i class="bi bi-pause-circle-fill me-1"></i>{{ __('Inactif') }}
                                    </span>
                                @endif
                            </td>
                            <td class="text-end pe-4">
                                <div class="btn-group btn-group-sm" role="group">
                                    {{-- Consulter --}}
                                    @can('view', $teacher)
                                        <a href="{{ route('teachers.show', $teacher) }}"
                                           class="btn btn-outline-secondary"
                                           title="{{ __('Consulter le dossier') }}"
                                           id="btn-show-teacher-{{ $teacher->id }}">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                    @endcan

                                    {{-- Modifier --}}
                                    @can('update', $teacher)
                                        <a href="{{ route('teachers.edit', $teacher) }}"
                                           class="btn btn-outline-primary"
                                           title="{{ __('Modifier le dossier') }}"
                                           id="btn-edit-teacher-{{ $teacher->id }}">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                    @endcan

                                    {{-- Activer / Désactiver --}}
                                    @can('toggleStatus', $teacher)
                                        <form method="POST" action="{{ route('teachers.toggle-status', $teacher) }}" class="d-inline">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit"
                                                    class="btn btn-outline-{{ $teacher->isActive() ? 'warning' : 'success' }}"
                                                    title="{{ $teacher->isActive() ? __('Désactiver') : __('Activer') }}"
                                                    onclick="return confirm('{{ __('Confirmer le changement de statut ?') }}')"
                                                    id="btn-toggle-teacher-{{ $teacher->id }}">
                                                <i class="bi bi-toggle-{{ $teacher->isActive() ? 'on' : 'off' }}"></i>
                                            </button>
                                        </form>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                <i class="bi bi-folder-x fs-1 d-block mb-2 text-secondary"></i>
                                {{ __('Aucun dossier professeur trouvé.') }}
                                @can('create', \App\Models\Teacher::class)
                                    <div class="mt-2">
                                        <a href="{{ route('teachers.create') }}" class="btn btn-sm btn-primary">
                                            {{ __('Créer un dossier professeur') }}
                                        </a>
                                    </div>
                                @endcan
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($teachers->hasPages())
            <div class="card-footer bg-white border-0 py-3">
                {{ $teachers->links() }}
            </div>
        @endif
    </div>

</div>
@endsection
