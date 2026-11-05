@extends('layouts.app')

@section('title', $center->translated('name') . ' — Education Centers')

@section('breadcrumb')
    <li class="breadcrumb-item">
        <a href="{{ route('centers.index') }}" class="text-decoration-none">{{ __('Centres') }}</a>
    </li>
    <li class="breadcrumb-item active">{{ $center->translated('name') }}</li>
@endsection

@section('content')
<div class="container-fluid px-4">

    {{-- En-tête --}}
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div class="d-flex align-items-center gap-3 [direction:ltr]" dir="ltr">
            <x-ui.back-button fallback="{{ route('centers.index') }}" />
            @if($center->logo_url)
                <img src="{{ $center->logo_url }}" alt="{{ $center->translated('name') }}" class="avatar-center shadow-sm" style="width: 56px; height: 56px;">
            @else
                <div class="avatar-center bg-primary bg-opacity-10 d-flex align-items-center justify-content-center text-primary fw-bold rounded-2 fs-4" style="width: 56px; height: 56px;">
                    {{ strtoupper(substr($center->name, 0, 2)) }}
                </div>
            @endif
            <div>
                <div class="d-flex align-items-center gap-2">
                    <h1 class="h3 fw-bold mb-0">{{ $center->translated('name') }}</h1>
                    <code class="bg-light px-2 py-1 rounded small">{{ $center->code }}</code>
                    @if($center->isActive())
                        <span class="badge badge-status-active rounded-pill px-3">{{ __('Actif') }}</span>
                    @else
                        <span class="badge badge-status-inactive rounded-pill px-3">{{ __('Inactif') }}</span>
                    @endif
                </div>
                <p class="text-muted small mb-0">
                    <i class="bi bi-geo-alt me-1"></i>{{ $center->city ?? __('Ville non précisée') }}, {{ $center->country }}
                </p>
            </div>
        </div>

        {{-- Actions rapides --}}
        <div class="d-flex align-items-center gap-2">
            {{-- Basculer statut --}}
            <form action="{{ route('centers.toggle-status', $center) }}" method="POST" class="d-inline">
                @csrf
                @method('PATCH')
                <button type="submit" class="btn btn-outline-secondary btn-sm" title="{{ __('Activer / Désactiver') }}">
                    <i class="bi {{ $center->isActive() ? 'bi-toggle-on text-success' : 'bi-toggle-off text-muted' }} me-1 fs-6"></i>
                    {{ $center->isActive() ? __('Désactiver') : __('Activer') }}
                </button>
            </form>

            <a href="{{ route('centers.edit', $center) }}" class="btn btn-primary btn-sm">
                <i class="bi bi-pencil me-1"></i>{{ __('Modifier') }}
            </a>

            <button type="button"
                    class="btn btn-outline-danger btn-sm"
                    data-bs-toggle="modal"
                    data-bs-target="#deleteModal">
                <i class="bi bi-trash3 me-1"></i>{{ __('Supprimer') }}
            </button>
        </div>
    </div>

    <div class="row g-4">
        {{-- Colonne Principale: Coordonnées et détails --}}
        <div class="col-lg-8">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white border-bottom py-3">
                    <h6 class="mb-0 fw-semibold">
                        <i class="bi bi-info-circle me-2 text-primary"></i>{{ __('Coordonnées & Informations') }}
                    </h6>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <span class="text-muted small d-block">{{ __('Email de contact') }}</span>
                            @if($center->email)
                                <a href="mailto:{{ $center->email }}" class="text-decoration-none fw-medium">
                                    <i class="bi bi-envelope me-1 text-muted"></i>{{ $center->email }}
                                </a>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </div>

                        <div class="col-sm-6">
                            <span class="text-muted small d-block">{{ __('Téléphone') }}</span>
                            @if($center->phone)
                                <a href="tel:{{ $center->phone }}" class="text-decoration-none fw-medium text-body">
                                    <i class="bi bi-telephone me-1 text-muted"></i><bdi dir="ltr">{{ $center->phone }}</bdi>
                                </a>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </div>

                        <div class="col-12"><hr class="my-1"></div>

                        <div class="col-sm-6">
                            <span class="text-muted small d-block">{{ __('Adresse') }}</span>
                            <span class="fw-medium">{{ $center->address ?? '—' }}</span>
                        </div>

                        <div class="col-sm-6">
                            <span class="text-muted small d-block">{{ __('Code postal & Ville') }}</span>
                            <span class="fw-medium">
                                {{ $center->postal_code ? $center->postal_code . ' ' : '' }}{{ $center->city ?? '—' }}
                            </span>
                        </div>

                        <div class="col-sm-6">
                            <span class="text-muted small d-block">{{ __('Pays') }}</span>
                            <span class="fw-medium">{{ $center->country }}</span>
                        </div>

                        <div class="col-sm-6">
                            <span class="text-muted small d-block">{{ __('Date de création') }}</span>
                            <span class="fw-medium">{{ $center->created_at?->format('d/m/Y H:i') ?? '—' }}</span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Aperçu des données rattachées (préparation des futures étapes) --}}
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-semibold">
                        <i class="bi bi-database me-2 text-primary"></i>{{ __('Données rattachées') }}
                    </h6>
                    <span class="badge bg-secondary-subtle text-secondary small">{{ __('Architecture multi-centres') }}</span>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3 text-center">
                        <div class="col-sm-3 col-6">
                            <div class="p-3 bg-light rounded-3">
                                <div class="fs-4 fw-bold text-primary">{{ $usersCount }}</div>
                                <div class="text-muted small">{{ __('Utilisateurs') }}</div>
                            </div>
                        </div>
                        <div class="col-sm-3 col-6">
                            <div class="p-3 bg-light rounded-3 opacity-75">
                                <div class="fs-4 fw-bold text-muted">—</div>
                                <div class="text-muted small">{{ __('Élèves (Étape future)') }}</div>
                            </div>
                        </div>
                        <div class="col-sm-3 col-6">
                            <div class="p-3 bg-light rounded-3 opacity-75">
                                <div class="fs-4 fw-bold text-muted">—</div>
                                <div class="text-muted small">{{ __('Professeurs (Étape future)') }}</div>
                            </div>
                        </div>
                        <div class="col-sm-3 col-6">
                            <div class="p-3 bg-light rounded-3 opacity-75">
                                <div class="fs-4 fw-bold text-muted">—</div>
                                <div class="text-muted small">{{ __('Formations (Étape future)') }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Colonne Latérale: Statut et résumé --}}
        <div class="col-lg-4">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white border-bottom py-3">
                    <h6 class="mb-0 fw-semibold">
                        <i class="bi bi-shield-check me-2 text-primary"></i>{{ __('Statut & Sécurité') }}
                    </h6>
                </div>
                <div class="card-body p-4">
                    <ul class="list-group list-group-flush small">
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <span>{{ __('Statut opérationnel') }}</span>
                            @if($center->isActive())
                                <span class="badge bg-success">{{ __('Actif') }}</span>
                            @else
                                <span class="badge bg-secondary">{{ __('Inactif') }}</span>
                            @endif
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <span>{{ __('Code identifiant') }}</span>
                            <span class="font-monospace fw-bold">{{ $center->code }}</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <span>{{ __('Suppression protégée') }}</span>
                            <span class="text-success"><i class="bi bi-check-circle-fill me-1"></i>{{ __('Oui') }}</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Modal de suppression --}}
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-danger text-white border-0">
                <h5 class="modal-title" id="deleteModalLabel">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>{{ __('Supprimer ce centre') }}
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body py-4">
                <p class="mb-1">{{ __('Voulez-vous supprimer définitivement :') }}</p>
                <p class="fw-bold fs-5 mb-0 text-danger">{{ $center->translated('name') }}</p>
                <div class="alert alert-warning mt-3 mb-0 small">
                    <i class="bi bi-info-circle me-1"></i>
                    {{ __('Si des utilisateurs y sont rattachés (actuellement :') }} <strong>{{ $usersCount }}</strong>{{ __('), la suppression sera bloquée.') }}
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Annuler') }}</button>
                <form action="{{ route('centers.destroy', $center) }}" method="POST">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-trash3-fill me-1"></i>{{ __('Supprimer définitivement') }}
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
