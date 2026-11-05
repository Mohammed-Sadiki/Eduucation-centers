@extends('layouts.app')

@section('title', __('Détail de la séance'))

@section('content')
<div class="container-fluid px-4 py-4">

    {{-- En-tête --}}
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <nav aria-label="{{ __('breadcrumb') }}">
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Tableau de bord') }}</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('schedules.index') }}">{{ __('Emplois du temps') }}</a></li>
                    <li class="breadcrumb-item active" aria-current="page">{{ __('Séance du') }} {{ $schedule->formatted_date }}</li>
                </ol>
            </nav>
            <div class="d-flex align-items-center gap-3 [direction:ltr]" dir="ltr">
                <x-ui.back-button fallback="{{ route('schedules.index') }}" />
                <h1 class="h3 fw-bold mb-0 text-gray-800 {{ current_dir() === 'rtl' ? '[direction:rtl] text-end' : '' }}">
                    <i class="bi bi-calendar-event text-primary me-2"></i>{{ __('Séance :') }} {{ $schedule->classRoom->perimeter_label }}
                </h1>
                @if($schedule->status === 'scheduled')
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2">
                        <i class="bi bi-calendar-check me-1"></i>{{ __('Programmée') }}
                    </span>
                @elseif($schedule->status === 'completed')
                    <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2">
                        <i class="bi bi-check2-all me-1"></i>{{ __('Terminée') }}
                    </span>
                @else
                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-3 py-2">
                        <i class="bi bi-x-circle me-1"></i>{{ __('Annulée') }}
                    </span>
                @endif
            </div>
        </div>

        <div class="d-flex flex-wrap gap-2">
            {{-- Actions statut --}}
            @if($schedule->status === 'scheduled')
                <form action="{{ route('schedules.toggle-status', $schedule) }}" method="POST" class="d-inline">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="status" value="completed">
                    <button type="submit" class="btn btn-outline-success">
                        <i class="bi bi-check2-circle me-1"></i>{{ __('Marquer comme terminée') }}
                    </button>
                </form>

                <form action="{{ route('schedules.toggle-status', $schedule) }}" method="POST" class="d-inline">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="status" value="cancelled">
                    <button type="submit" class="btn btn-outline-warning">
                        <i class="bi bi-slash-circle me-1"></i>{{ __('Annuler la séance') }}
                    </button>
                </form>
            @elseif($schedule->status === 'cancelled')
                <form action="{{ route('schedules.toggle-status', $schedule) }}" method="POST" class="d-inline">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="status" value="scheduled">
                    <button type="submit" class="btn btn-outline-primary">
                        <i class="bi bi-arrow-counterclockwise me-1"></i>{{ __('Reprogrammer') }}
                    </button>
                </form>
            @endif

            <a href="{{ route('schedules.edit', $schedule) }}" class="btn btn-outline-secondary">
                <i class="bi bi-pencil me-1"></i>{{ __('Modifier') }}
            </a>

            <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteScheduleModal">
                <i class="bi bi-trash me-1"></i>{{ __('Supprimer') }}
            </button>
        </div>
    </div>

    {{-- Alertes --}}
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show d-flex align-items-center gap-2" role="alert">
            <i class="bi bi-check-circle-fill fs-5"></i>
            <div>{{ session('success') }}</div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="row g-4">
        {{-- Fiche principale --}}
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3 border-bottom">
                    <h5 class="fw-bold mb-0 text-dark">
                        <i class="bi bi-info-circle text-primary me-2"></i>{{ __('Détails de la séance') }}
                    </h5>
                </div>
                <div class="card-body p-4">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <span class="text-muted small text-uppercase d-block mb-1">{{ __('Date') }}</span>
                            <div class="fs-5 fw-bold text-dark">
                                <i class="bi bi-calendar3 text-primary me-2"></i>{{ $schedule->formatted_date }}
                            </div>
                        </div>

                        <div class="col-md-6">
                            <span class="text-muted small text-uppercase d-block mb-1">{{ __('Créneau horaire') }}</span>
                            <div class="fs-5 fw-bold text-dark">
                                <i class="bi bi-clock text-primary me-2"></i>{{ $schedule->formatted_time }}
                                <span class="badge bg-light text-muted border fs-6 ms-2">
                                    {{ $schedule->duration_minutes }} {{ __('min') }}
                                </span>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <span class="text-muted small text-uppercase d-block mb-1">{{ __('Type d\'activité') }}</span>
                            <div>
                                <span class="badge bg-secondary-subtle text-secondary border px-3 py-2 fs-6">
                                    {{ $schedule->type_label }}
                                </span>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <span class="text-muted small text-uppercase d-block mb-1">{{ __('Titre de la séance') }}</span>
                            <div class="fw-semibold text-dark">
                                {{ $schedule->title ?: __('Aucun titre spécifique') }}
                            </div>
                        </div>

                        @if($schedule->description)
                            <div class="col-12">
                                <span class="text-muted small text-uppercase d-block mb-1">{{ __('Description / Objectifs') }}</span>
                                <div class="p-3 bg-light rounded border">
                                    {{ $schedule->description }}
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Préparation Présences & Évaluations futures --}}
            <div class="card border-0 shadow-sm border-start border-4 border-info">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center gap-3">
                        <i class="bi bi-clipboard-check text-info fs-1"></i>
                        <div>
                            <h5 class="fw-bold mb-1">{{ __('Feuille de présence & Évaluation (Étapes futures)') }}</h5>
                            <p class="text-muted small mb-0">
                                {{ __('Le pointage des présences des élèves inscrits à cette classe ainsi que la saisie d\'évaluations seront rattachés directement à cette séance.') }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Volet latéral (Acteurs & Centre) --}}
        <div class="col-lg-4">
            {{-- Centre --}}
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-3">
                    <span class="text-muted small text-uppercase d-block mb-1">{{ __('Centre de formation') }}</span>
                    <h5 class="fw-bold text-dark mb-1">
                        <i class="bi bi-buildings text-primary me-2"></i>{{ $schedule->center->name }}
                    </h5>
                    <span class="badge bg-light text-secondary border">{{ $schedule->center->code }}</span>
                </div>
            </div>

            {{-- Classe & Formation --}}
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-3">
                    <span class="text-muted small text-uppercase d-block mb-1">{{ __('Classe rattachée') }}</span>
                    <h5 class="fw-bold text-primary mb-1">
                        <a href="{{ route('classes.show', $schedule->classRoom) }}" class="text-decoration-none">
                            {{ $schedule->classRoom->perimeter_label }}
                        </a>
                    </h5>
                    <div class="text-muted small">
                        <i class="bi bi-journal-bookmark me-1"></i>{{ __('Formation :') }} {{ $schedule->classRoom->formation->general_formation_name ?? '—' }}
                    </div>
                </div>
            </div>

            {{-- Professeur --}}
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-3">
                    <span class="text-muted small text-uppercase d-block mb-1">{{ __('Professeur assigné') }}</span>
                    <h5 class="fw-bold text-dark mb-1">
                        <a href="{{ route('teachers.show', $schedule->teacher) }}" class="text-decoration-none">
                            <i class="bi bi-person-badge text-primary me-2"></i>{{ $schedule->teacher->full_name }}
                        </a>
                    </h5>
                    <small class="text-muted d-block">{{ $schedule->teacher->teacher_number }}</small>
                </div>
            </div>

            {{-- Salle --}}
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-3">
                    <span class="text-muted small text-uppercase d-block mb-1">{{ __('Salle de cours') }}</span>
                    <h5 class="fw-bold text-dark mb-1">
                        <a href="{{ route('rooms.show', $schedule->room) }}" class="text-decoration-none">
                            <i class="bi bi-door-open text-primary me-2"></i>{{ $schedule->room->name }}
                        </a>
                    </h5>
                    <div class="d-flex align-items-center gap-2 mt-1">
                        <span class="badge bg-info-subtle text-info-emphasis border">{{ $schedule->room->code }}</span>
                        <small class="text-muted">{{ __('Capacité:') }} {{ $schedule->room->capacity }} {{ __('places') }}</small>
                    </div>
                    @if($schedule->room->equipment)
                        <small class="text-muted d-block mt-2">
                            <i class="bi bi-tools me-1"></i>{{ $schedule->room->equipment }}
                        </small>
                    @endif
                </div>
            </div>

            {{-- Métadonnées --}}
            <div class="card border-0 shadow-sm">
                <div class="card-body p-3 text-muted small">
                    <div><strong>{{ __('Créée le :') }}</strong> {{ format_date($schedule->created_at, 'd/m/Y H:i') }}</div>
                    <div><strong>{{ __('Dernière mise à jour :') }}</strong> {{ format_date($schedule->updated_at, 'd/m/Y H:i') }}</div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Modal Suppression --}}
<div class="modal fade" id="deleteScheduleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fs-6 fw-bold">{{ __('Confirmer la suppression') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                {{ __('Êtes-vous sûr de vouloir supprimer définitivement cette séance ?') }}<br>
                <div class="alert alert-warning py-2 mt-2 mb-0 small">
                    <i class="bi bi-info-circle me-1"></i>{{ __('Astuce : pour conserver l\'historique pédagogique, privilégiez') }} <strong>{{ __('l\'annulation') }}</strong> {{ __('plutôt que la suppression définitive.') }}
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">{{ __('Annuler') }}</button>
                <form action="{{ route('schedules.destroy', $schedule) }}" method="POST">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger btn-sm">{{ __('Supprimer définitivement') }}</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
