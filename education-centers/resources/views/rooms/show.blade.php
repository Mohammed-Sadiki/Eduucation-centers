@extends('layouts.app')

@section('title', "Salle {$room->code} — " . config('app.name'))
@section('description', "Fiche descriptive de la salle {$room->name}")

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('rooms.index') }}">{{ __('Salles') }}</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ $room->code }}</li>
@endsection

@section('content')
<div class="container-fluid px-4 py-4">

    {{-- En-tête de la fiche --}}
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div class="d-flex align-items-center gap-3 [direction:ltr]" dir="ltr">
            <x-ui.back-button :fallback="route('rooms.index')" />
            <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center shadow-sm" style="width: 58px; height: 58px; font-size: 1.5rem;">
                <i class="bi bi-door-open-fill"></i>
            </div>
            <div>
                <div class="d-flex align-items-center gap-2">
                    <h1 class="h3 fw-bold mb-0">{{ $room->name }}</h1>
                    @if($room->isActive())
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
                    {{ __('Code :') }} <strong class="text-primary">{{ $room->code }}</strong>
                    @if($room->center)
                        &bull; Centre : <strong>{{ $room->center->name }} ({{ $room->center->code }})</strong>
                    @endif
                </div>
            </div>
        </div>

        <div class="d-flex gap-2">
            @can('update', $room)
                <a href="{{ route('rooms.edit', $room) }}" class="btn btn-primary" id="btn-edit-room">
                    <i class="bi bi-pencil me-1"></i>{{ __('Modifier') }}
                </a>
            @endcan
            @can('toggleStatus', $room)
                <form method="POST" action="{{ route('rooms.toggle-status', $room) }}" class="d-inline">
                    @csrf
                    @method('PATCH')
                    <button type="submit"
                            class="btn btn-outline-{{ $room->isActive() ? 'warning' : 'success' }}"
                            onclick="return confirm('{{ __('Confirmer le changement de statut ?') }}')"
                            id="btn-toggle-status">
                        <i class="bi bi-toggle-{{ $room->isActive() ? 'on' : 'off' }} me-1"></i>
                        {{ $room->isActive() ? __('Désactiver') : __('Activer') }}
                    </button>
                </form>
            @endcan
            @can('delete', $room)
                <form method="POST" action="{{ route('rooms.destroy', $room) }}" class="d-inline">
                    @csrf
                    @method('DELETE')
                    <button type="submit"
                            class="btn btn-outline-danger"
                            onclick="return confirm('{{ __('Confirmer la suppression définitive de cette salle ?') }}')"
                            id="btn-delete-room">
                        <i class="bi bi-trash me-1"></i>{{ __('Supprimer') }}
                    </button>
                </form>
            @endcan
        </div>
    </div>

    <div class="row g-4">
        {{-- Colonne gauche : Informations Générales & Équipements --}}
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
                                <th class="w-35 ps-4 text-secondary small">{{ __('Code de salle') }}</th>
                                <td class="font-monospace fw-bold text-primary fs-6">{{ $room->code }}</td>
                            </tr>
                            <tr>
                                <th class="ps-4 text-secondary small">{{ __('Nom') }}</th>
                                <td class="fw-semibold">{{ $room->name }}</td>
                            </tr>
                            <tr>
                                <th class="ps-4 text-secondary small">{{ __('Centre de rattachement') }}</th>
                                <td>
                                    @if($room->center)
                                        <span class="fw-semibold">{{ $room->center->name }}</span>
                                        <span class="badge bg-light text-dark border ms-1">{{ $room->center->code }}</span>
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <th class="ps-4 text-secondary small">{{ __('Statut') }}</th>
                                <td>
                                    @if($room->isActive())
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

            {{-- Équipements & Matériel --}}
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white py-3 border-bottom">
                    <h5 class="card-title mb-0 fw-bold text-primary">
                        <i class="bi bi-tools me-2"></i>{{ __('Équipements & Matériel disponible') }}
                    </h5>
                </div>
                <div class="card-body">
                    @if($room->equipment)
                        <p class="mb-0 text-dark">{{ $room->equipment }}</p>
                    @else
                        <span class="text-muted fst-italic">{{ __('Aucun équipement spécifique renseigné.') }}</span>
                    @endif
                </div>
            </div>

            {{-- Description --}}
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white py-3 border-bottom">
                    <h5 class="card-title mb-0 fw-bold text-primary">
                        <i class="bi bi-journal-text me-2"></i>{{ __('Description & Remarques') }}
                    </h5>
                </div>
                <div class="card-body">
                    @if($room->description)
                        <p class="mb-0 text-secondary" style="white-space: pre-line;">{{ $room->description }}</p>
                    @else
                        <span class="text-muted fst-italic">{{ __('Aucune description renseignée pour cette salle.') }}</span>
                    @endif
                </div>
            </div>

        </div>

        {{-- Colonne droite : Capacité, Localisation, Administration & Utilisation future --}}
        <div class="col-lg-5">

            {{-- Capacité & Localisation --}}
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white py-3 border-bottom">
                    <h5 class="card-title mb-0 fw-bold text-primary">
                        <i class="bi bi-geo-alt me-2"></i>{{ __('Capacité & Emplacement') }}
                    </h5>
                </div>
                <div class="card-body p-0">
                    <table class="table table-striped table-hover mb-0">
                        <tbody>
                            <tr>
                                <th class="w-45 ps-4 text-secondary small">{{ __('Capacité d’accueil') }}</th>
                                <td class="fw-bold text-primary">
                                    <i class="bi bi-people-fill me-1"></i>{{ $room->capacity }} {{ __('places') }}
                                </td>
                            </tr>
                            <tr>
                                <th class="ps-4 text-secondary small">{{ __('Bâtiment') }}</th>
                                <td>{{ $room->building ?? '—' }}</td>
                            </tr>
                            <tr>
                                <th class="ps-4 text-secondary small">{{ __('Étage') }}</th>
                                <td>{{ $room->floor ?? '—' }}</td>
                            </tr>
                            <tr>
                                <th class="ps-4 text-secondary small">{{ __('Emplacement synthétique') }}</th>
                                <td>{{ $room->location }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Section : Planning de la salle --}}
            <div class="card shadow-sm border-0 mb-4 border-start border-primary border-4">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0 fw-bold text-primary">
                        <i class="bi bi-calendar-week me-2"></i>{{ __('Planning de la salle (') }}{{ $room->schedules->count() }})
                    </h5>
                    <a href="{{ route('schedules.create', ['center_id' => $room->center_id, 'room_id' => $room->id]) }}" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-calendar-plus me-1"></i>{{ __('Réserver') }}
                    </a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3">{{ __('Date') }}</th>
                                    <th>{{ __('Heure') }}</th>
                                    <th>{{ __('Classe') }}</th>
                                    <th>{{ __('Statut') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($room->schedules()->orderBy('date')->orderBy('start_time')->take(5)->get() as $rs)
                                    <tr>
                                        <td class="ps-3">{{ $rs->formatted_date }}</td>
                                        <td>{{ $rs->formatted_time }}</td>
                                        <td>{{ $rs->classRoom->perimeter_label }}</td>
                                        <td>
                                            @if($rs->status === 'scheduled')
                                                <span class="badge bg-primary-subtle text-primary">{{ __('Prévue') }}</span>
                                            @elseif($rs->status === 'completed')
                                                <span class="badge bg-success-subtle text-success">{{ __('Terminée') }}</span>
                                            @else
                                                <span class="badge bg-danger-subtle text-danger">{{ __('Annulée') }}</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center py-3 text-muted small">
                                            {{ __('Aucune séance programmée dans cette salle.') }}
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
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
                                <td>{{ format_date($room->created_at, 'd/m/Y H:i') }}</td>
                            </tr>
                            <tr>
                                <th class="ps-4 text-secondary small">{{ __('Dernière modification') }}</th>
                                <td>{{ format_date($room->updated_at, 'd/m/Y H:i') }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>

</div>
@endsection
