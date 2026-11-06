@extends('layouts.app')

@section('title', "Classe {$class->code} — " . config('app.name'))
@section('description', "Fiche descriptive de la classe {$class->perimeter_label}")

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('classes.index') }}">{{ __('Classes') }}</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ $class->code }}</li>
@endsection

@section('content')
<div class="container-fluid px-4 py-4">

    {{-- En-tête de la fiche --}}
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div class="d-flex align-items-center gap-3 [direction:ltr]" dir="ltr">
            <x-ui.back-button :fallback="route('classes.index')" />
            <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center shadow-sm" style="width: 58px; height: 58px; font-size: 1.5rem;">
                <i class="bi bi-diagram-3-fill"></i>
            </div>
            <div>
                <div class="d-flex align-items-center gap-2">
                    <h1 class="h3 fw-bold mb-0">{{ $class->perimeter_label }}</h1>
                    @if($class->isActive())
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
                    {{ __('Code :') }} <strong class="text-primary">{{ $class->code }}</strong>
                    @if($class->formation)
                        &bull; Formation : <strong>{{ $class->formation->general_formation_name }} ({{ $class->formation->code }})</strong>
                    @endif
                    @if($class->center)
                        &bull; Centre : <strong>{{ $class->center->name }} ({{ $class->center->code }})</strong>
                    @endif
                </div>
            </div>
        </div>

        <div class="d-flex gap-2">
            @can('update', $class)
                <a href="{{ route('classes.edit', $class) }}" class="btn btn-primary" id="btn-edit-class">
                    <i class="bi bi-pencil me-1"></i>{{ __('Modifier') }}
                </a>
            @endcan
            @can('toggleStatus', $class)
                <form method="POST" action="{{ route('classes.toggle-status', $class) }}" class="d-inline">
                    @csrf
                    @method('PATCH')
                    <button type="submit"
                            class="btn btn-outline-{{ $class->isActive() ? 'warning' : 'success' }}"
                            onclick="return confirm('{{ __('Confirmer le changement de statut ?') }}')"
                            id="btn-toggle-status">
                        <i class="bi bi-toggle-{{ $class->isActive() ? 'on' : 'off' }} me-1"></i>
                        {{ $class->isActive() ? __('Désactiver') : __('Activer') }}
                    </button>
                </form>
            @endcan
            @can('delete', $class)
                <form method="POST" action="{{ route('classes.destroy', $class) }}" class="d-inline">
                    @csrf
                    @method('DELETE')
                    <button type="submit"
                            class="btn btn-outline-danger"
                            onclick="return confirm('{{ __('Confirmer la suppression de cette classe ?') }}')"
                            id="btn-delete-class">
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
                        <i class="bi bi-info-circle me-2"></i>{{ __('Informations de la classe') }}
                    </h5>
                </div>
                <div class="card-body p-0">
                    <table class="table table-striped table-hover mb-0">
                        <tbody>
                            <tr>
                                <th class="w-35 ps-4 text-secondary small">{{ __('Code de la classe') }}</th>
                                <td class="font-monospace fw-bold text-primary fs-6">{{ $class->code }}</td>
                            </tr>
                            <tr>
                                <th class="ps-4 text-secondary small">{{ __('Intitulé') }}</th>
                                <td class="fw-semibold">{{ $class->perimeter_label }}</td>
                            </tr>
                            <tr>
                                <th class="ps-4 text-secondary small">{{ __('Formation rattachée') }}</th>
                                <td>
                                    @if($class->formation)
                                        <a href="{{ route('formations.show', $class->formation) }}" class="fw-semibold text-decoration-none">
                                            {{ $class->formation->general_formation_name }}
                                        </a>
                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle ms-1">
                                            {{ $class->formation->code }}
                                        </span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <th class="ps-4 text-secondary small">{{ __('Centre de formation') }}</th>
                                <td>
                                    @if($class->center)
                                        <span class="fw-semibold">{{ $class->center->name }}</span>
                                        <span class="badge bg-light text-dark border ms-1">{{ $class->center->code }}</span>
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <th class="ps-4 text-secondary small">{{ __('Statut') }}</th>
                                <td>
                                    @if($class->isActive())
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

            {{-- Description --}}
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3 border-bottom">
                    <h5 class="card-title mb-0 fw-bold text-primary">
                        <i class="bi bi-journal-text me-2"></i>{{ __('Description & Remarques') }}
                    </h5>
                </div>
                <div class="card-body">
                    @if($class->description)
                        <p class="mb-0 text-secondary" style="white-space: pre-line;">{{ $class->description }}</p>
                    @else
                        <span class="text-muted fst-italic">{{ __('Aucune description renseignée pour cette classe.') }}</span>
                    @endif
                </div>
            </div>

        </div>

        {{-- Colonne droite : Capacité, Période & Administration --}}
        <div class="col-lg-5">

            {{-- Capacité & Période --}}
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white py-3 border-bottom">
                    <h5 class="card-title mb-0 fw-bold text-primary">
                        <i class="bi bi-calendar-event me-2"></i>{{ __('Capacité & Période') }}
                    </h5>
                </div>
                <div class="card-body p-0">
                    <table class="table table-striped table-hover mb-0">
                        <tbody>
                            <tr>
                                <th class="w-45 ps-4 text-secondary small">{{ __('Capacité d’accueil') }}</th>
                                <td class="fw-semibold">
                                    {{ $class->capacity ? $class->capacity . ' ' . __('places') : __(__('Illimitée')) }}
                                </td>
                            </tr>
                            <tr>
                                <th class="ps-4 text-secondary small">{{ __('Date de début') }}</th>
                                <td>
                                    {{ $class->start_date ? format_date($class->start_date, 'd/m/Y') : '—' }}
                                </td>
                            </tr>
                            <tr>
                                <th class="ps-4 text-secondary small">{{ __('Date de fin') }}</th>
                                <td>
                                    {{ $class->end_date ? format_date($class->end_date, 'd/m/Y') : '—' }}
                                </td>
                            </tr>
                            <tr>
                                <th class="ps-4 text-secondary small">{{ __('Période globale') }}</th>
                                <td>{{ $class->formatted_period }}</td>
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
                                <td>{{ format_date($class->created_at, 'd/m/Y H:i') }}</td>
                            </tr>
                            <tr>
                                <th class="ps-4 text-secondary small">{{ __('Dernière modification') }}</th>
                                <td>{{ format_date($class->updated_at, 'd/m/Y H:i') }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>

    {{-- Section : Professeurs affectés à la classe --}}
    <div class="row mt-4">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h5 class="card-title mb-0 fw-bold text-primary">
                        <i class="bi bi-person-workspace me-2"></i>{{ __('Professeurs affectés (') }}{{ $class->teachers->count() }})
                    </h5>

                    @can('assignTeacher', $class)
                        @if($availableTeachers->isNotEmpty())
                            <button type="button" class="btn btn-sm btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#assignTeacherModal" id="btn-open-assign-teacher">
                                <i class="bi bi-person-plus-fill me-1"></i>{{ __('Affecter un professeur') }}
                            </button>
                        @endif
                    @endcan
                </div>

                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4">{{ __('N° Professeur') }}</th>
                                    <th>{{ __('Nom & Prénom') }}</th>
                                    <th>{{ __('Spécialisation') }}</th>
                                    <th>{{ __('Email') }}</th>
                                    <th>{{ __('Téléphone') }}</th>
                                    <th>{{ __('Statut') }}</th>
                                    @can('unassignTeacher', $class)
                                        <th class="text-end pe-4">{{ __('Actions') }}</th>
                                    @endcan
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($class->teachers as $teacher)
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
                                            {{ $teacher->user?->email ?? '—' }}
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
                                        @can('unassignTeacher', $class)
                                            <td class="text-end pe-4">
                                                <form method="POST" action="{{ route('classes.teachers.destroy', [$class, $teacher]) }}" class="d-inline">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit"
                                                            class="btn btn-sm btn-outline-danger"
                                                            title="{{ __('Retirer ce professeur de la classe') }}"
                                                            onclick="return confirm('{{ __('Confirmer le retrait du professeur :name de cette classe ? (Le profil du professeur ne sera pas supprimé)', ['name' => $teacher->full_name]) }}')"
                                                            id="btn-unassign-teacher-{{ $teacher->id }}">
                                                        <i class="bi bi-person-dash me-1"></i>{{ __('Retirer') }}
                                                    </button>
                                                </form>
                                            </td>
                                        @endcan
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="{{ auth()->user()->can('unassignTeacher', $class) ? '7' : '6' }}" class="text-center py-4 text-muted">
                                            <i class="bi bi-person-x fs-2 d-block mb-2 text-secondary"></i>
                                            {{ __('Aucun professeur n\'est actuellement affecté à cette classe.') }}
                                            @can('assignTeacher', $class)
                                                @if($availableTeachers->isNotEmpty())
                                                    <div class="mt-2">
                                                        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#assignTeacherModal">
                                                            {{ __('Affecter le premier professeur') }}
                                                        </button>
                                                    </div>
                                                @else
                                                    <div class="small text-muted mt-1">
                                                        {{ __('(Aucun professeur actif disponible dans le centre') }} {{ $class->center?->name }})
                                                    </div>
                                                @endif
                                            @endcan
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
    {{-- Section : Emploi du temps de la classe --}}
    <div class="row mt-4">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h5 class="card-title mb-0 fw-bold text-primary">
                        <i class="bi bi-calendar-week me-2"></i>{{ __('Emploi du temps & Prochaines séances (') }}{{ $class->schedules->count() }})
                    </h5>

                    <a href="{{ route('schedules.create', ['center_id' => $class->center_id, 'class_id' => $class->id]) }}" class="btn btn-sm btn-primary shadow-sm">
                        <i class="bi bi-calendar-plus me-1"></i>{{ __('Ajouter une séance') }}
                    </a>
                </div>

                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4">{{ __('Date & Horaire') }}</th>
                                    <th>{{ __('Professeur') }}</th>
                                    <th>{{ __('Salle') }}</th>
                                    <th>{{ __('Type') }}</th>
                                    <th>{{ __('Statut') }}</th>
                                    <th class="text-end pe-4">{{ __('Action') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($class->schedules()->orderBy('date')->orderBy('start_time')->get() as $sch)
                                    <tr>
                                        <td class="ps-4">
                                            <div class="fw-bold">{{ $sch->formatted_date }}</div>
                                            <small class="text-muted"><i class="bi bi-clock me-1 text-primary"></i>{{ $sch->formatted_time }}</small>
                                        </td>
                                        <td>
                                            <i class="bi bi-person-badge text-muted me-1"></i>{{ $sch->teacher->full_name }}
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark border"><i class="bi bi-door-closed me-1"></i>{{ $sch->room->name }} ({{ $sch->room->code }})</span>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary-subtle text-secondary">{{ $sch->type_label }}</span>
                                        </td>
                                        <td>
                                            @if($sch->status === 'scheduled')
                                                <span class="badge bg-primary-subtle text-primary border">{{ __('Programmée') }}</span>
                                            @elseif($sch->status === 'completed')
                                                <span class="badge bg-success-subtle text-success border">{{ __('Terminée') }}</span>
                                            @else
                                                <span class="badge bg-danger-subtle text-danger border">{{ __('Annulée') }}</span>
                                            @endif
                                        </td>
                                        <td class="text-end pe-4">
                                            <a href="{{ route('schedules.show', $sch) }}" class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center py-4 text-muted">
                                            <i class="bi bi-calendar-x fs-2 d-block mb-2 text-secondary"></i>
                                            {{ __('Aucune séance programmée pour cette classe.') }}
                                            <div class="mt-2">
                                                <a href="{{ route('schedules.create', ['center_id' => $class->center_id, 'class_id' => $class->id]) }}" class="btn btn-sm btn-outline-primary">
                                                    {{ __('Planifier une première séance') }}
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

{{-- Modal d'affectation d'un professeur --}}
@can('assignTeacher', $class)
    @if($availableTeachers->isNotEmpty())
        <div class="modal fade" id="assignTeacherModal" tabindex="-1" aria-labelledby="assignTeacherModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="POST" action="{{ route('classes.teachers.store', $class) }}" id="form-assign-teacher">
                        @csrf
                        <div class="modal-header">
                            <h5 class="modal-title fw-bold" id="assignTeacherModalLabel">
                                <i class="bi bi-person-plus-fill text-primary me-2"></i>{{ __('Affecter un professeur') }}
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
                        </div>
                        <div class="modal-body">
                            <p class="small text-muted mb-3">
                                {{ __('Sélectionnez un professeur actif du centre') }} <strong>{{ $class->center?->name }}</strong> {{ __('à associer à la classe') }} <strong>{{ $class->code }} ({{ $class->perimeter_label }})</strong>.
                            </p>

                            <div class="mb-3">
                                <label for="teacher_id" class="form-label fw-semibold">
                                    {{ __('Professeur à affecter') }} <span class="text-danger">*</span>
                                </label>
                                <select id="teacher_id" name="teacher_id" class="form-select @error('teacher_id') is-invalid @enderror" required>
                                    <option value="">{{ __('-- Choisir un professeur --') }}</option>
                                    @foreach($availableTeachers as $availTeacher)
                                        <option value="{{ $availTeacher->id }}">
                                            {{ $availTeacher->full_name_with_formation }} ({{ $availTeacher->teacher_number }})
                                            @if($availTeacher->specialization)
                                                — {{ $availTeacher->specialization }}
                                            @endif
                                        </option>
                                    @endforeach
                                </select>
                                @error('teacher_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('Annuler') }}</button>
                            <button type="submit" class="btn btn-primary" id="btn-submit-assign-teacher">
                                <i class="bi bi-check-circle-fill me-1"></i>{{ __('Valider l\'affectation') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
@endcan
@endsection

