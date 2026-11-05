@extends('layouts.app')

@section('title', __('Formation :name (:code)', ['name' => $formation->translated('name'), 'code' => $formation->code]) . ' — ' . config('app.name'))
@section('description', __('Fiche descriptive de la formation :name', ['name' => $formation->translated('name')]))

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('formations.index') }}">{{ __('Formations') }}</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ $formation->code }}</li>
@endsection

@section('content')
<div class="container-fluid px-4 py-4">

    {{-- En-tête de la fiche --}}
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div class="d-flex align-items-center gap-3 [direction:ltr]" dir="ltr">
            <x-ui.back-button fallback="{{ route('formations.index') }}" />
            <div class="d-flex align-items-center gap-3 {{ current_dir() === 'rtl' ? '[direction:rtl]' : '' }}">
                <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center shadow-sm shrink-0" style="width: 58px; height: 58px; font-size: 1.5rem;">
                    <i class="bi bi-journal-bookmark-fill"></i>
                </div>
                <div>
                    <div class="d-flex align-items-center gap-2">
                        <h1 class="h3 fw-bold mb-0">{{ $formation->translated('name') }}</h1>
                        @if($formation->isActive())
                            <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                                <i class="bi bi-check-circle-fill me-1"></i>{{ __('Actif') }}
                            </span>
                        @else
                            <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1">
                                <i class="bi bi-pause-circle-fill me-1"></i>{{ __('Inactif') }}
                            </span>
                        @endif
                    </div>
                    <div class="text-muted small mt-1 font-monospace">
                        {{ __('Code :') }} <strong class="text-primary">{{ $formation->code }}</strong>
                        @if($formation->center)
                            &bull; Centre : <strong>{{ $formation->center->name }} ({{ $formation->center->code }})</strong>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex gap-2">
            @can('update', $formation)
                <a href="{{ route('formations.edit', $formation) }}" class="btn btn-primary" id="btn-edit-formation">
                    <i class="bi bi-pencil me-1"></i>{{ __('Modifier') }}
                </a>
            @endcan
            @can('toggleStatus', $formation)
                <form method="POST" action="{{ route('formations.toggle-status', $formation) }}" class="d-inline">
                    @csrf
                    @method('PATCH')
                    <button type="submit"
                            class="btn btn-outline-{{ $formation->isActive() ? 'warning' : 'success' }}"
                            onclick="return confirm('{{ __('Confirmer le changement de statut ?') }}')"
                            id="btn-toggle-status">
                        <i class="bi bi-toggle-{{ $formation->isActive() ? 'on' : 'off' }} me-1"></i>
                        {{ $formation->isActive() ? __('Désactiver') : __('Activer') }}
                    </button>
                </form>
            @endcan
            @can('delete', $formation)
                <form method="POST" action="{{ route('formations.destroy', $formation) }}" class="d-inline">
                    @csrf
                    @method('DELETE')
                    <button type="submit"
                            class="btn btn-outline-danger"
                            onclick="return confirm('{{ __('Confirmer la suppression définitive de cette formation ?') }}')"
                            id="btn-delete-formation">
                        <i class="bi bi-trash me-1"></i>{{ __('Supprimer') }}
                    </button>
                </form>
            @endcan
        </div>
    </div>

    <div class="row g-4">
        {{-- Colonne gauche : Informations Générales & Pédagogiques --}}
        <div class="col-lg-7">

            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white py-3 border-bottom">
                    <h5 class="card-title mb-0 fw-bold text-primary">
                        <i class="bi bi-info-circle me-2"></i>{{ __('Informations générales') }}
                    </h5>
                </div>
                <div class="card-body p-0">
                    <table class="table table-striped table-hover mb-0">
                        <tbody>
                            <tr>
                                <th class="w-35 ps-4 text-secondary small">{{ __('Code de formation') }}</th>
                                <td class="font-monospace fw-bold text-primary fs-6">{{ $formation->code }}</td>
                            </tr>
                            <tr>
                                <th class="ps-4 text-secondary small">{{ __('Intitulé') }}</th>
                                <td class="fw-semibold">{{ $formation->translated('name') }}</td>
                            </tr>
                            <tr>
                                <th class="ps-4 text-secondary small">{{ __('Centre de formation') }}</th>
                                <td>
                                    @if($formation->center)
                                        <span class="fw-semibold">{{ $formation->center->name }}</span>
                                        <span class="badge bg-light text-dark border ms-1">{{ $formation->center->code }}</span>
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <th class="ps-4 text-secondary small">{{ __('Statut') }}</th>
                                <td>
                                    @if($formation->isActive())
                                        <span class="badge bg-success">{{ __('Actif') }}</span>
                                    @else
                                        <span class="badge bg-secondary">{{ __('Inactif') }}</span>
                                    @endif
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Description du programme --}}
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3 border-bottom">
                    <h5 class="card-title mb-0 fw-bold text-primary">
                        <i class="bi bi-journal-text me-2"></i>{{ __('Description & Objectifs pédagogiques') }}
                    </h5>
                </div>
                <div class="card-body">
                    @if($formation->description)
                        <p class="mb-0 text-secondary" style="white-space: pre-line;">{{ $formation->translated('description') }}</p>
                    @else
                        <span class="text-muted fst-italic">{{ __('Aucune description renseignée pour cette formation.') }}</span>
                    @endif
                </div>
            </div>

        </div>

        {{-- Colonne droite : Durée, Tarification & Administration --}}
        <div class="col-lg-5">

            {{-- Durée & Volume horaire --}}
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white py-3 border-bottom">
                    <h5 class="card-title mb-0 fw-bold text-primary">
                        <i class="bi bi-clock-history me-2"></i>{{ __('Volume horaire & Durée') }}
                    </h5>
                </div>
                <div class="card-body p-0">
                    <table class="table table-striped table-hover mb-0">
                        <tbody>
                            <tr>
                                <th class="w-45 ps-4 text-secondary small">{{ __('Volume horaire total') }}</th>
                                <td class="fw-semibold">
                                    {{ $formation->duration_hours ? $formation->duration_hours . ' heures' : '—' }}
                                </td>
                            </tr>
                            <tr>
                                <th class="ps-4 text-secondary small">{{ __('Durée en semaines') }}</th>
                                <td class="fw-semibold">
                                    {{ $formation->duration_weeks ? $formation->duration_weeks . ' semaines' : '—' }}
                                </td>
                            </tr>
                            <tr>
                                <th class="ps-4 text-secondary small">{{ __('Durée synthétique') }}</th>
                                <td>{{ $formation->formatted_duration }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Tarification --}}
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white py-3 border-bottom">
                    <h5 class="card-title mb-0 fw-bold text-primary">
                        <i class="bi bi-cash-stack me-2"></i>{{ __('Tarification') }}
                    </h5>
                </div>
                <div class="card-body p-0">
                    <table class="table table-striped table-hover mb-0">
                        <tbody>
                            <tr>
                                <th class="w-45 ps-4 text-secondary small">{{ __('Prix de la formation') }}</th>
                                <td class="fw-bold text-success fs-6">
                                    {{ $formation->formatted_price }}
                                </td>
                            </tr>
                            <tr>
                                <th class="ps-4 text-secondary small">{{ __('Devise') }}</th>
                                <td>{{ currency_symbol($formation->currency) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Administration & Horodatage --}}
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3 border-bottom">
                    <h5 class="card-title mb-0 fw-bold text-primary">
                        <i class="bi bi-shield-check me-2"></i>{{ __('Informations administratives') }}
                    </h5>
                </div>
                <div class="card-body p-0">
                    <table class="table table-striped table-hover mb-0">
                        <tbody>
                            <tr>
                                <th class="w-45 ps-4 text-secondary small">{{ __('Date de création') }}</th>
                                <td>{{ format_date($formation->created_at, 'd/m/Y H:i') }}</td>
                            </tr>
                            <tr>
                                <th class="ps-4 text-secondary small">{{ __('Dernière modification') }}</th>
                                <td>{{ format_date($formation->updated_at, 'd/m/Y H:i') }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>

</div>
@endsection
