@extends('layouts.app')

@section('title', __('Classes') . ' — ' . config('app.name'))
@section('description', __('Gestion des classes et groupes pédagogiques'))

@section('breadcrumb')
    <li class="breadcrumb-item active" aria-current="page">{{ __('Classes') }}</li>
@endsection

@section('content')
<div class="container-fluid px-4 py-4">

    {{-- En-tête --}}
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div class="d-flex align-items-center gap-3 [direction:ltr]" dir="ltr">
            <x-ui.back-button />
            <div class="{{ current_dir() === 'rtl' ? '[direction:rtl] text-end' : '' }}">
                <h1 class="h3 fw-bold mb-0">
                    <i class="bi bi-diagram-3-fill text-primary me-2"></i>{{ __('Gestion des Classes') }}
                </h1>
                <p class="text-muted small mb-0 mt-1">
                    @if(auth()->user()->isSuperAdmin())
                        {{ __('Tous les centres —') }} {{ $classes->total() }} {{ __('classe(s) trouvée(s)') }}
                    @else
                        {{ __('Centre :') }} <strong>{{ auth()->user()->center?->name }}</strong> — {{ $classes->total() }} {{ __('classe(s) trouvée(s)') }}
                    @endif
                </p>
            </div>
        </div>
        @can('create', \App\Models\ClassRoom::class)
            <a href="{{ route('classes.create') }}" class="btn btn-primary shadow-sm" id="btn-create-class">
                <i class="bi bi-plus-circle-fill me-1"></i>{{ __('Nouvelle classe') }}
            </a>
        @endcan
    </div>

    {{-- Filtres --}}
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body py-3">
            <form method="GET" action="{{ route('classes.index') }}" id="form-filter-classes">
                <div class="row g-3 align-items-end">
                    {{-- Recherche par nom --}}
                    <div class="col-md-3">
                        <label for="search" class="form-label small fw-semibold text-secondary mb-1">
                            <i class="bi bi-search me-1"></i>{{ __('Nom de classe') }}
                        </label>
                        <input type="text" id="search" name="search" class="form-control"
                               placeholder="{{ __('Ex: Groupe Matin...') }}" value="{{ request('search') }}">
                    </div>

                    {{-- Code classe --}}
                    <div class="col-md-2">
                        <label for="code" class="form-label small fw-semibold text-secondary mb-1">
                            <i class="bi bi-hash me-1"></i>{{ __('Code') }}
                        </label>
                        <input type="text" id="code" name="code" class="form-control"
                               placeholder="{{ __('Ex: A1-01') }}" value="{{ request('code') }}">
                    </div>

                    {{-- Filtre formation --}}
                    <div class="col-md-2">
                        <label for="formation_id" class="form-label small fw-semibold text-secondary mb-1">{{ __('Formation') }}</label>
                        <select id="formation_id" name="formation_id" class="form-select">
                            <option value="">{{ __('Toutes les formations') }}</option>
                            @foreach($formations as $formation)
                                <option value="{{ $formation->id }}" {{ request('formation_id') == $formation->id ? 'selected' : '' }}>
                                    {{ $formation->code }} — {{ $formation->translated('name') }}
                                </option>
                            @endforeach
                        </select>
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
                        @if(request()->hasAny(['search', 'code', 'formation_id', 'status', 'center_id']))
                            <a href="{{ route('classes.index') }}" class="btn btn-outline-secondary" title="{{ __('Réinitialiser') }}">
                                <i class="bi bi-x-lg"></i>
                            </a>
                        @endif
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- Liste des classes --}}
    <div class="card shadow-sm border-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">{{ __('Code') }}</th>
                        <th>{{ __('Intitulé de la classe') }}</th>
                        <th>{{ __('Formation') }}</th>
                        @if(auth()->user()->isSuperAdmin())
                            <th>{{ __('Centre') }}</th>
                        @endif
                        <th>{{ __('Capacité') }}</th>
                        <th>{{ __('Période') }}</th>
                        <th>{{ __('Statut') }}</th>
                        <th class="text-end pe-4">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($classes as $class)
                        <tr>
                            <td class="ps-4">
                                <a href="{{ route('classes.show', $class) }}" class="fw-bold text-decoration-none font-monospace">
                                    {{ $class->code }}
                                </a>
                            </td>
                            <td>
                                <div class="fw-semibold text-dark">{{ $class->perimeter_label }}</div>
                                @if($class->description)
                                    <small class="text-muted text-truncate d-inline-block" style="max-width: 250px;">
                                        {{ Str::limit($class->description, 50) }}
                                    </small>
                                @endif
                            </td>
                            <td>
                                @if($class->formation)
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
                                        {{ $class->formation->code }} — {{ $class->formation->general_formation_name }}
                                    </span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            @if(auth()->user()->isSuperAdmin())
                                <td>
                                    @if($class->center)
                                        <span class="badge bg-light text-dark border">
                                            <i class="bi bi-building me-1"></i>{{ $class->center->name }}
                                        </span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                            @endif
                            <td>
                                {{ $class->capacity ? $class->capacity . ' ' . __('élèves') : __(__('Illimitée')) }}
                            </td>
                            <td>
                                <small class="text-muted">{{ $class->formatted_period }}</small>
                            </td>
                            <td>
                                @if($class->isActive())
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
                                    @can('view', $class)
                                        <a href="{{ route('classes.show', $class) }}"
                                           class="btn btn-outline-secondary"
                                           title="{{ __('Consulter') }}"
                                           id="btn-show-class-{{ $class->id }}">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                    @endcan

                                    {{-- Modifier --}}
                                    @can('update', $class)
                                        <a href="{{ route('classes.edit', $class) }}"
                                           class="btn btn-outline-primary"
                                           title="{{ __('Modifier') }}"
                                           id="btn-edit-class-{{ $class->id }}">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                    @endcan

                                    {{-- Activer / Désactiver --}}
                                    @can('toggleStatus', $class)
                                        <form method="POST" action="{{ route('classes.toggle-status', $class) }}" class="d-inline">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit"
                                                    class="btn btn-outline-{{ $class->isActive() ? 'warning' : 'success' }}"
                                                    title="{{ $class->isActive() ? __('Désactiver') : __('Activer') }}"
                                                    onclick="return confirm('{{ __('Confirmer le changement de statut ?') }}')"
                                                    id="btn-toggle-class-{{ $class->id }}">
                                                <i class="bi bi-toggle-{{ $class->isActive() ? 'on' : 'off' }}"></i>
                                            </button>
                                        </form>
                                    @endcan

                                    {{-- Supprimer --}}
                                    @can('delete', $class)
                                        <form method="POST" action="{{ route('classes.destroy', $class) }}" class="d-inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                    class="btn btn-outline-danger"
                                                    title="{{ __('Supprimer la classe') }}"
                                                    onclick="return confirm('{{ __('Êtes-vous sûr de vouloir supprimer cette classe ?') }}')"
                                                    id="btn-delete-class-{{ $class->id }}">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ auth()->user()->isSuperAdmin() ? '8' : '7' }}" class="text-center py-5 text-muted">
                                <i class="bi bi-diagram-3 fs-1 d-block mb-2 text-secondary"></i>
                                {{ __('Aucune classe trouvée.') }}
                                @can('create', \App\Models\ClassRoom::class)
                                    <div class="mt-2">
                                        <a href="{{ route('classes.create') }}" class="btn btn-sm btn-primary">
                                            {{ __('Créer une classe') }}
                                        </a>
                                    </div>
                                @endcan
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($classes->hasPages())
            <div class="card-footer bg-white border-0 py-3">
                {{ $classes->links() }}
            </div>
        @endif
    </div>

</div>
@endsection
