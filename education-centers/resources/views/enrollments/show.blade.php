@extends('layouts.app')

@section('title', __('Dossier d’inscription') . ' ' . $enrollment->registration_number)

@section('content')
<div class="container-fluid px-4 py-4">

    {{-- En-tête --}}
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <nav aria-label="{{ __('breadcrumb') }}">
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Tableau de bord') }}</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('enrollments.index') }}">{{ __('Inscriptions') }}</a></li>
                    <li class="breadcrumb-item active" aria-current="page">{{ $enrollment->registration_number }}</li>
                </ol>
            </nav>
            <div class="d-flex align-items-center gap-3 [direction:ltr]" dir="ltr">
                <x-ui.back-button fallback="{{ route('enrollments.index') }}" />
                <div class="d-flex align-items-center gap-3 {{ current_dir() === 'rtl' ? '[direction:rtl]' : '' }}">
                    <h1 class="h3 fw-bold mb-0 text-gray-800 font-monospace">
                        <i class="bi bi-card-checklist text-primary me-2"></i>{{ $enrollment->registration_number }}
                    </h1>
                    <span class="badge {{ $enrollment->status_badge_class }} px-3 py-2 fs-6">
                        {{ $enrollment->status_label }}
                    </span>
                </div>
            </div>
        </div>

        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('enrollments.edit', $enrollment) }}" class="btn btn-outline-secondary">
                <i class="bi bi-pencil me-1"></i>{{ __('Modifier') }}
            </a>

            <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteEnrollmentModal">
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

    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <strong>{{ __('Erreur de traitement :') }}</strong>
            <ul class="mb-0 mt-1 ps-3">
                @foreach($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- Barre de Workflow de Statut --}}
    <div class="card border-0 shadow-sm mb-4 border-start border-4 border-primary">
        <div class="card-body p-4">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                <div>
                    <h5 class="fw-bold mb-1">
                        <i class="bi bi-arrow-repeat text-primary me-2"></i>{{ __('Workflow d\'admission') }}
                    </h5>
                    <p class="text-muted small mb-0">
                        {{ __('Statut actuel :') }} <strong class="text-dark">{{ $enrollment->status_label }}</strong>{{ __('. Sélectionnez une action administrative ci-contre selon l\'état d\'instruction du dossier.') }}
                    </p>
                </div>

                {{-- Boutons d'actions contextuels selon le statut --}}
                <div class="d-flex flex-wrap gap-2">
                    @if($enrollment->isPending())
                        {{-- pending -> under_review --}}
                        <form action="{{ route('enrollments.change-status', $enrollment) }}" method="POST">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="status" value="under_review">
                            <button type="submit" class="btn btn-info text-white shadow-sm">
                                <i class="bi bi-search me-1"></i>{{ __('Mettre en examen') }}
                            </button>
                        </form>

                        {{-- pending -> reject modal --}}
                        <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#rejectModal">
                            <i class="bi bi-x-circle me-1"></i>{{ __('Refuser') }}
                        </button>

                        {{-- pending -> cancel --}}
                        <form action="{{ route('enrollments.change-status', $enrollment) }}" method="POST">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="status" value="cancelled">
                            <button type="submit" class="btn btn-outline-secondary" onclick="return confirm('{{ __('Confirmer l\'annulation de cette inscription ?') }}')">
                                <i class="bi bi-slash-circle me-1"></i>{{ __('Annuler') }}
                            </button>
                        </form>
                    @elseif($enrollment->isUnderReview())
                        {{-- under_review -> accept modal --}}
                        <button type="button" class="btn btn-success shadow-sm" data-bs-toggle="modal" data-bs-target="#acceptModal">
                            <i class="bi bi-check2-circle me-1"></i>{{ __('Accepter l\'inscription') }}
                        </button>

                        {{-- under_review -> reject modal --}}
                        <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#rejectModal">
                            <i class="bi bi-x-circle me-1"></i>{{ __('Refuser') }}
                        </button>

                        {{-- under_review -> cancel --}}
                        <form action="{{ route('enrollments.change-status', $enrollment) }}" method="POST">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="status" value="cancelled">
                            <button type="submit" class="btn btn-outline-secondary" onclick="return confirm('{{ __('Confirmer l\'annulation de cette inscription ?') }}')">
                                <i class="bi bi-slash-circle me-1"></i>{{ __('Annuler') }}
                            </button>
                        </form>
                    @elseif($enrollment->isAccepted())
                        {{-- accepted -> cancel --}}
                        <form action="{{ route('enrollments.change-status', $enrollment) }}" method="POST">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="status" value="cancelled">
                            <button type="submit" class="btn btn-outline-warning" onclick="return confirm('{{ __('Confirmer l\'annulation de cette inscription déjà acceptée ?') }}')">
                                <i class="bi bi-slash-circle me-1"></i>{{ __('Annuler l\'inscription') }}
                            </button>
                        </form>
                    @else
                        <span class="badge bg-light text-muted border py-2 px-3">
                            <i class="bi bi-lock me-1"></i>{{ __('Dossier clôturé') }} ({{ $enrollment->status_label }})
                        </span>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        {{-- Fiche principale --}}
        <div class="col-lg-8">
            {{-- Détails Formation & Pédagogie --}}
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3 border-bottom">
                    <h5 class="fw-bold mb-0 text-dark">
                        <i class="bi bi-journal-bookmark text-primary me-2"></i>Formation & Affectation
                    </h5>
                </div>
                <div class="card-body p-4">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <span class="text-muted small text-uppercase d-block mb-1">{{ __('Formation postulée') }}</span>
                            <div class="fs-5 fw-bold text-dark">
                                <a href="{{ route('formations.show', $enrollment->formation) }}" class="text-decoration-none">
                                    {{ $enrollment->formation->general_formation_name }}
                                </a>
                            </div>
                            <span class="badge bg-light text-muted border mt-1">{{ $enrollment->formation->code }}</span>
                        </div>

                        <div class="col-md-6">
                            <span class="text-muted small text-uppercase d-block mb-1">{{ __('Coût de la formation') }}</span>
                            <div class="fs-5 fw-bold text-primary">
                                {{ format_price($enrollment->formation->price, $enrollment->formation->currency) }}
                            </div>
                            <small class="text-muted">{{ $enrollment->formation->duration_hours }} {{ __('heures (') }}{{ $enrollment->formation->duration_weeks }} {{ __('semaines)') }}</small>
                        </div>

                        <div class="col-md-6">
                            <span class="text-muted small text-uppercase d-block mb-1">{{ __('Classe de rattachement') }}</span>
                            @if($enrollment->classRoom)
                                <div class="fs-5 fw-bold text-dark">
                                    <a href="{{ route('classes.show', $enrollment->classRoom) }}" class="text-decoration-none">
                                        {{ $enrollment->classRoom->perimeter_label }}
                                    </a>
                                </div>
                                <div class="small text-muted mt-1">
                                    <i class="bi bi-people me-1"></i>{{ __('Effectif :') }} 
                                    <strong>{{ $enrollment->classRoom->accepted_enrollments_count }} / {{ $enrollment->classRoom->capacity ?? __('Illimitée') }}</strong>
                                </div>
                            @else
                                <span class="badge bg-light text-secondary border fs-6">{{ __('Non affectée à une classe') }}</span>
                            @endif
                        </div>

                        <div class="col-md-6">
                            <span class="text-muted small text-uppercase d-block mb-1">{{ __('Date de dépôt de candidature') }}</span>
                            <div class="fw-semibold text-dark">
                                <i class="bi bi-calendar-event text-primary me-1"></i>{{ $enrollment->formatted_application_date }}
                            </div>
                        </div>

                        @if($enrollment->notes)
                            <div class="col-12">
                                <span class="text-muted small text-uppercase d-block mb-1">{{ __('Projet / Remarques de l\'élève') }}</span>
                                <div class="p-3 bg-light rounded border">
                                    {{ $enrollment->notes }}
                                </div>
                            </div>
                        @endif

                        @if($enrollment->decision_notes)
                            <div class="col-12">
                                <span class="text-muted small text-uppercase d-block mb-1">{{ __('Motif de décision / Notes administratives') }}</span>
                                <div class="p-3 bg-warning-subtle text-warning-emphasis rounded border border-warning-subtle">
                                    {{ $enrollment->decision_notes }}
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Préparation Paiements & Factures futures --}}
            <div class="card border-0 shadow-sm border-start border-4 border-info">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center gap-3">
                        <i class="bi bi-cash-coin text-info fs-1"></i>
                        <div>
                            <h5 class="fw-bold mb-1">{{ __('Paiements & Facturation (Étapes futures)') }}</h5>
                            <p class="text-muted small mb-0">
                                {{ __('Une fois l\'inscription validée, les échéances de paiement, reçus et factures d\'honoraires de formation seront adossés directement à ce numéro d\'inscription.') }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Volet latéral (Élève & Centre) --}}
        <div class="col-lg-4">
            {{-- Fiche Élève --}}
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3 border-bottom">
                    <h5 class="fw-bold mb-0 text-dark">
                        <i class="bi bi-person text-primary me-2"></i>{{ __('Dossier Élève') }}
                    </h5>
                </div>
                <div class="card-body p-3">
                    <h5 class="fw-bold text-dark mb-1">
                        <a href="{{ route('students.show', $enrollment->student) }}" class="text-decoration-none">
                            {{ $enrollment->student->full_name }}
                        </a>
                    </h5>
                    <div class="font-monospace small text-primary mb-2">{{ $enrollment->student->student_number }}</div>

                    <div class="small text-muted mb-1">
                        <i class="bi bi-envelope me-1"></i>{{ $enrollment->student->user?->email ?? '—' }}
                    </div>
                    @if($enrollment->student->phone)
                        <div class="small text-muted mb-1">
                            <i class="bi bi-telephone me-1"></i><bdi dir="ltr">{{ $enrollment->student->phone }}</bdi>
                        </div>
                    @endif
                    <div class="small text-muted">
                        <i class="bi bi-geo-alt me-1"></i>{{ $enrollment->student->city ?? '—' }} ({{ $enrollment->student->country ?? '—' }})
                    </div>
                </div>
            </div>

            {{-- Centre --}}
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-3">
                    <span class="text-muted small text-uppercase d-block mb-1">{{ __('Centre gestionnaire') }}</span>
                    <h5 class="fw-bold text-dark mb-1">
                        <i class="bi bi-buildings text-primary me-2"></i>{{ $enrollment->center->name }}
                    </h5>
                    <span class="badge bg-light text-secondary border">{{ $enrollment->center->code }}</span>
                </div>
            </div>

            {{-- Dates & Décision --}}
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3 border-bottom">
                    <h5 class="fw-bold mb-0 text-dark">
                        <i class="bi bi-clock-history text-primary me-2"></i>{{ __('Historique du dossier') }}
                    </h5>
                </div>
                <div class="card-body p-3 text-muted small">
                    <div class="mb-2"><strong>{{ __('Dépôt initial :') }}</strong> {{ format_date($enrollment->application_date, 'd/m/Y') }}</div>
                    @if($enrollment->decision_date)
                        <div class="mb-2"><strong>{{ __('Décision rendue le :') }}</strong> {{ format_date($enrollment->decision_date, 'd/m/Y') }}</div>
                    @endif
                    @if($enrollment->accepted_at)
                        <div class="mb-2 text-success"><strong>{{ __('Acceptée le :') }}</strong> {{ format_date($enrollment->accepted_at, 'd/m/Y H:i') }}</div>
                    @endif
                    @if($enrollment->cancelled_at)
                        <div class="mb-2 text-secondary"><strong>{{ __('Annulée le :') }}</strong> {{ format_date($enrollment->cancelled_at, 'd/m/Y H:i') }}</div>
                    @endif
                    <div class="pt-2 border-top mt-2">
                        <div>{{ __('Créée le :') }} {{ format_date($enrollment->created_at, 'd/m/Y H:i') }}</div>
                        <div>{{ __('Dernière modification :') }} {{ format_date($enrollment->updated_at, 'd/m/Y H:i') }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Modal Acceptation --}}
<div class="modal fade" id="acceptModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="{{ route('enrollments.change-status', $enrollment) }}" method="POST">
                @csrf
                @method('PATCH')
                <input type="hidden" name="status" value="accepted">

                <div class="modal-header">
                    <h5 class="modal-title fs-6 fw-bold text-success">
                        <i class="bi bi-check2-circle me-1"></i>{{ __('Accepter l\'inscription') }}
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted mb-3">
                        {{ __('L\'acceptation confirme définitivement l\'admission de l\'élève à cette formation.') }}
                    </p>

                    <div class="mb-3">
                        <label for="modal_class_id" class="form-label fw-semibold">{{ __('Affecter à une classe') }}</label>
                        <select name="class_id" id="modal_class_id" class="form-select">
                            <option value="">{{ __('-- Conserver sans classe / choisir plus tard --') }}</option>
                            @foreach($availableClasses as $ac)
                                <option value="{{ $ac->id }}" {{ $enrollment->class_id == $ac->id ? 'selected' : '' }}>
                                    {{ $ac->perimeter_label }} ({{ $ac->accepted_enrollments_count }}/{{ $ac->capacity ?? __('Illimitée') }} {{ __('places)') }}
                                </option>
                            @endforeach
                        </select>
                        <small class="text-muted">{{ __('La capacité de la classe sera vérifiée automatiquement.') }}</small>
                    </div>

                    <div class="mb-0">
                        <label for="modal_decision_notes" class="form-label fw-semibold">{{ __('Remarques d\'admission (optionnel)') }}</label>
                        <textarea name="decision_notes" id="modal_decision_notes" rows="2" class="form-control" 
                                  placeholder="{{ __('Observations pour le dossier de l\'élève...') }}"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">{{ __('Annuler') }}</button>
                    <button type="submit" class="btn btn-success btn-sm">{{ __('Valider l\'acceptation') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Modal Refus --}}
<div class="modal fade" id="rejectModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="{{ route('enrollments.change-status', $enrollment) }}" method="POST">
                @csrf
                @method('PATCH')
                <input type="hidden" name="status" value="rejected">

                <div class="modal-header">
                    <h5 class="modal-title fs-6 fw-bold text-danger">
                        <i class="bi bi-x-circle me-1"></i>{{ __('Refuser la candidature') }}
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted mb-3">
                        {{ __('Veuillez indiquer le motif administratif ou pédagogique du refus de cette candidature.') }}
                    </p>

                    <div class="mb-0">
                        <label for="reject_decision_notes" class="form-label fw-semibold">{{ __('Motif du refus') }}</label>
                        <textarea name="decision_notes" id="reject_decision_notes" rows="3" class="form-control" 
                                  placeholder="{{ __('Ex: Prérequis de niveau non atteints, dossier incomplet...') }}" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">{{ __('Annuler') }}</button>
                    <button type="submit" class="btn btn-danger btn-sm">{{ __('Confirmer le refus') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Modal Suppression --}}
<div class="modal fade" id="deleteEnrollmentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fs-6 fw-bold">{{ __('Confirmer la suppression') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                {{ __('Êtes-vous sûr de vouloir supprimer définitivement l\'inscription') }} 
                <strong>{{ $enrollment->registration_number }}</strong> ?
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">{{ __('Annuler') }}</button>
                <form action="{{ route('enrollments.destroy', $enrollment) }}" method="POST">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger btn-sm">{{ __('Supprimer définitivement') }}</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
