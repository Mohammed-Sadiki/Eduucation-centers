@extends('layouts.app')

@section('title', __('Modifier la séance'))

@section('content')
<div class="container-fluid px-4 py-4">

    {{-- En-tête --}}
    <div class="mb-4">
        <nav aria-label="{{ __('breadcrumb') }}">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Tableau de bord') }}</a></li>
                <li class="breadcrumb-item"><a href="{{ route('schedules.index') }}">{{ __('Emplois du temps') }}</a></li>
                <li class="breadcrumb-item"><a href="{{ route('schedules.show', $schedule) }}">{{ __('Détail séance') }}</a></li>
                <li class="breadcrumb-item active" aria-current="page">{{ __('Modifier') }}</li>
            </ol>
        </nav>
        <div class="d-flex align-items-center gap-3 [direction:ltr]" dir="ltr">
            <x-ui.back-button :fallback="route('schedules.show', $schedule)" />
            <h1 class="h3 fw-bold mb-0 text-gray-800 {{ current_dir() === 'rtl' ? '[direction:rtl] text-end' : '' }}">
                <i class="bi bi-pencil-square text-primary me-2"></i>{{ __('Modifier la séance de formation') }}
            </h1>
        </div>
    </div>

    {{-- Alertes erreurs globales --}}
    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <div class="d-flex align-items-center gap-2 mb-2">
                <i class="bi bi-exclamation-triangle-fill fs-5"></i>
                <h6 class="mb-0 fw-bold">{{ __('Impossible de modifier la séance :') }}</h6>
            </div>
            <ul class="mb-0 ps-3">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="row justify-content-center">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <form action="{{ route('schedules.update', $schedule) }}" method="POST">
                        @csrf
                        @method('PUT')

                        {{-- Section Centre --}}
                        <div class="row mb-4 pb-3 border-bottom">
                            <div class="col-12">
                                <h5 class="fw-bold text-dark mb-3">
                                    <i class="bi bi-geo-alt text-primary me-2"></i>{{ __('Centre de formation') }}
                                </h5>

                                <div class="p-3 bg-light rounded border d-flex align-items-center gap-2">
                                    <i class="bi bi-building text-primary fs-5"></i>
                                    <div>
                                        <strong>{{ $schedule->center->name }}</strong>
                                        <span class="badge bg-secondary ms-2">{{ $schedule->center->code }}</span>
                                        <small class="text-muted d-block mt-1">{{ __('Le centre est verrouillé pour garantir l\'intégrité de la séance.') }}</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Section Acteurs & Lieu --}}
                        <div class="row mb-4 pb-3 border-bottom g-3">
                            <div class="col-12">
                                <h5 class="fw-bold text-dark mb-1">
                                    <i class="bi bi-people text-primary me-2"></i>{{ __('Classe, Professeur et Salle') }}
                                </h5>
                                <p class="text-muted small mb-3">{{ __('Le professeur doit impérativement être affecté à la classe choisie.') }}</p>
                            </div>

                            {{-- Classe --}}
                            <div class="col-md-4">
                                <label for="class_id" class="form-label fw-semibold">{{ __('Classe') }} <span class="text-danger">*</span></label>
                                <select name="class_id" id="class_id" class="form-select @error('class_id') is-invalid @enderror" required>
                                    @foreach($classes as $cl)
                                        <option value="{{ $cl->id }}" 
                                            {{ old('class_id', $schedule->class_id) == $cl->id ? 'selected' : '' }}
                                            data-teachers="{{ $cl->teachers->pluck('id')->join(',') }}">
                                            {{ $cl->perimeter_label }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('class_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            {{-- Professeur --}}
                            <div class="col-md-4">
                                <label for="teacher_id" class="form-label fw-semibold">{{ __('Professeur') }} <span class="text-danger">*</span></label>
                                <select name="teacher_id" id="teacher_id" class="form-select @error('teacher_id') is-invalid @enderror" required>
                                    @foreach($teachers as $t)
                                        <option value="{{ $t->id }}" 
                                            {{ old('teacher_id', $schedule->teacher_id) == $t->id ? 'selected' : '' }}
                                            data-classes="{{ $t->classes->pluck('id')->join(',') }}">
                                            {{ $t->full_name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('teacher_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            {{-- Salle --}}
                            <div class="col-md-4">
                                <label for="room_id" class="form-label fw-semibold">{{ __('Salle de cours') }} <span class="text-danger">*</span></label>
                                <select name="room_id" id="room_id" class="form-select @error('room_id') is-invalid @enderror" required>
                                    @foreach($rooms as $r)
                                        <option value="{{ $r->id }}" {{ old('room_id', $schedule->room_id) == $r->id ? 'selected' : '' }}>
                                            {{ $r->name }} ({{ $r->code }} {{ __('— Capacité:') }} {{ $r->capacity }})
                                        </option>
                                    @endforeach
                                </select>
                                @error('room_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        {{-- Section Date & Horaires --}}
                        <div class="row mb-4 pb-3 border-bottom g-3">
                            <div class="col-12">
                                <h5 class="fw-bold text-dark mb-1">
                                    <i class="bi bi-clock text-primary me-2"></i>{{ __('Date et Créneau horaire') }}
                                </h5>
                                <p class="text-muted small mb-3">{{ __('L\'heure de fin doit être strictement supérieure à l\'heure de début.') }}</p>
                            </div>

                            <div class="col-md-4">
                                <label for="date" class="form-label fw-semibold">{{ __('Date de la séance') }} <span class="text-danger">*</span></label>
                                <input type="date" name="date" id="date" class="form-control @error('date') is-invalid @enderror" 
                                       value="{{ old('date', $schedule->date->format('Y-m-d')) }}" required>
                                @error('date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-4">
                                <label for="start_time" class="form-label fw-semibold">{{ __('Heure de début') }} <span class="text-danger">*</span></label>
                                <input type="time" name="start_time" id="start_time" class="form-control @error('start_time') is-invalid @enderror" 
                                       value="{{ old('start_time', substr($schedule->start_time, 0, 5)) }}" required>
                                @error('start_time')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-4">
                                <label for="end_time" class="form-label fw-semibold">{{ __('Heure de fin') }} <span class="text-danger">*</span></label>
                                <input type="time" name="end_time" id="end_time" class="form-control @error('end_time') is-invalid @enderror" 
                                       value="{{ old('end_time', substr($schedule->end_time, 0, 5)) }}" required>
                                @error('end_time')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        {{-- Section Type, Statut & Détails --}}
                        <div class="row mb-4 g-3">
                            <div class="col-12">
                                <h5 class="fw-bold text-dark mb-1">
                                    <i class="bi bi-card-text text-primary me-2"></i>{{ __('Informations complémentaires') }}
                                </h5>
                            </div>

                            <div class="col-md-4">
                                <label for="type" class="form-label fw-semibold">{{ __('Type de séance') }} <span class="text-danger">*</span></label>
                                <select name="type" id="type" class="form-select @error('type') is-invalid @enderror" required>
                                    <option value="course" {{ old('type', $schedule->type) === 'course' ? 'selected' : '' }}>Cours</option>
                                    <option value="exam" {{ old('type', $schedule->type) === 'exam' ? 'selected' : '' }}>Examen</option>
                                    <option value="workshop" {{ old('type', $schedule->type) === 'workshop' ? 'selected' : '' }}>Atelier / TP</option>
                                    <option value="other" {{ old('type', $schedule->type) === 'other' ? 'selected' : '' }}>Autre</option>
                                </select>
                                @error('type')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-4">
                                <label for="status" class="form-label fw-semibold">{{ __('Statut') }} <span class="text-danger">*</span></label>
                                <select name="status" id="status" class="form-select @error('status') is-invalid @enderror" required>
                                    <option value="scheduled" {{ old('status', $schedule->status) === 'scheduled' ? 'selected' : '' }}>{{ __('Programmée') }}</option>
                                    <option value="completed" {{ old('status', $schedule->status) === 'completed' ? 'selected' : '' }}>{{ __('Terminée') }}</option>
                                    <option value="cancelled" {{ old('status', $schedule->status) === 'cancelled' ? 'selected' : '' }}>{{ __('Annulée') }}</option>
                                </select>
                                @error('status')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-4">
                                <label for="title" class="form-label fw-semibold">{{ __('Titre / Sujet de la séance') }}</label>
                                <input type="text" name="title" id="title" class="form-control @error('title') is-invalid @enderror" 
                                       value="{{ old('title', $schedule->title) }}" placeholder="{{ __('Ex: Révisions grammaire') }}">
                                @error('title')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-12">
                                <label for="description" class="form-label fw-semibold">{{ __('Description / Remarques') }}</label>
                                <textarea name="description" id="description" rows="3" class="form-control @error('description') is-invalid @enderror">{{ old('description', $schedule->description) }}</textarea>
                                @error('description')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        {{-- Boutons d'action --}}
                        <div class="d-flex justify-content-end gap-3 pt-3 border-top">
                            <a href="{{ route('schedules.show', $schedule) }}" class="btn btn-outline-secondary px-4">
                                {{ __('Annuler') }}
                            </a>
                            <button type="submit" class="btn btn-primary px-4">
                                <i class="bi bi-check-lg me-1"></i>{{ __('Enregistrer les modifications') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const classSelect = document.getElementById('class_id');
    const teacherSelect = document.getElementById('teacher_id');

    if (classSelect && teacherSelect) {
        function filterTeachers() {
            const selectedOption = classSelect.options[classSelect.selectedIndex];
            const teacherIds = selectedOption ? (selectedOption.getAttribute('data-teachers') || '').split(',') : [];

            Array.from(teacherSelect.options).forEach(opt => {
                if (!opt.value) return;
                if (teacherIds.includes(opt.value)) {
                    opt.style.display = '';
                } else {
                    opt.style.display = 'none';
                }
            });
        }

        classSelect.addEventListener('change', filterTeachers);
    }
});
</script>
@endpush
@endsection
