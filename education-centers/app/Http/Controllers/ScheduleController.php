<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreScheduleRequest;
use App\Http\Requests\UpdateScheduleRequest;
use App\Models\Center;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\Teacher;
use App\Services\NotificationService;
use App\Services\ScheduleConflictService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ScheduleController extends Controller
{
    /**
     * Palette de couleurs par type de séance (fond pastel + bordure colorée).
     */
    public const TYPE_COLORS = [
        'course' => '#3b82f6', // bleu
        'workshop' => '#10b981', // vert
        'exam' => '#f59e0b', // ambre
        'other' => '#ef4444', // rouge
    ];

    /**
     * Liste des séances du planning, affichée dans un calendrier interactif.
     *
     * Les séances proviennent toutes de la base de données (aucune donnée
     * factice) et sont filtrées selon le rôle, le centre, la classe, le
     * professeur, le type et le statut sélectionnés.
     */
    public function index(Request $request, ScheduleConflictService $conflictService): View
    {
        Gate::authorize('viewAny', Schedule::class);

        $authUser = $request->user();
        $perimeter = $this->perimeter($authUser, $authUser->isSuperAdmin() ? $request->input('center_id') : null);

        $query = Schedule::query()->with([
            'center',
            'classRoom.formation',
            'teacher.user',
            'room',
        ]);

        // Isolation stricte par centre
        if ($authUser->isCenterAdmin() || $authUser->isSecretary()) {
            $query->where('center_id', $authUser->center_id);
        }

        // Périmètre pédagogique unique : formations générales actives du centre
        // autorisé + domaine actif. Le calendrier suit donc exactement les filtres.
        $query = $perimeter->applyTo($query);

        // Filtre : centre (Super Admin seulement)
        if ($authUser->isSuperAdmin() && ($centerId = $request->input('center_id'))) {
            $query->where('center_id', $centerId);
        }

        // Filtre : date précise
        if ($date = $request->input('date')) {
            $query->whereDate('date', $date);
        }

        // Filtre : statut
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        // Filtre : classe
        if ($classId = $request->input('class_id')) {
            $query->where('class_id', $classId);
        }

        // Filtre : professeur
        if ($teacherId = $request->input('teacher_id')) {
            $query->where('teacher_id', $teacherId);
        }

        // Filtre : type de séance
        if ($type = $request->input('type')) {
            $query->where('type', $type);
        }

        // Filtre : salle
        if ($roomId = $request->input('room_id')) {
            $query->where('room_id', $roomId);
        }

        $filtered = $query->orderBy('date', 'asc')
            ->orderBy('start_time', 'asc')
            ->get();

        // Séances en conflit (même prof / salle / classe qui se chevauchent).
        $conflictIds = array_flip($conflictService->overlappingIds($filtered));

        $events = $filtered->map(function (Schedule $s) use ($authUser, $conflictIds): array {
            $type = array_key_exists($s->type, self::TYPE_COLORS) ? $s->type : 'other';

            $classes = ['ec-fc-event', 'ec-fc-event--'.$type];
            if ($s->isCancelled()) {
                $classes[] = 'ec-fc-event--cancelled';
            }
            if (isset($conflictIds[$s->id])) {
                $classes[] = 'ec-fc-event--conflict';
            }

            return [
                'id' => (string) $s->id,
                'title' => $s->title ?: ($s->classRoom?->perimeter_label ?: __('Séance')),
                'start' => $s->date?->format('Y-m-d').'T'.substr((string) $s->start_time, 0, 5),
                'end' => $s->date?->format('Y-m-d').'T'.substr((string) $s->end_time, 0, 5),
                'allDay' => false,
                'classNames' => $classes,
                'extendedProps' => [
                    'type' => $type,
                    'typeLabel' => $s->type_label,
                    'color' => self::TYPE_COLORS[$type],
                    'status' => $s->status,
                    'statusLabel' => match ($s->status) {
                        'completed' => __('Terminé'),
                        'cancelled' => __('Annulé'),
                        default => __('Planifié'),
                    },
                    'time' => substr((string) $s->start_time, 0, 5).' – '.substr((string) $s->end_time, 0, 5),
                    'date' => $s->formatted_date,
                    'className' => $s->classRoom?->perimeter_label,
                    'classCode' => $s->classRoom?->code,
                    'teacherName' => $s->teacher?->full_name,
                    'roomName' => $s->room?->name,
                    'roomCode' => $s->room?->code,
                    'centerName' => $s->center?->name,
                    'description' => $s->description,
                    'conflict' => isset($conflictIds[$s->id]),
                    'showUrl' => route('schedules.show', $s),
                    'editUrl' => route('schedules.edit', $s),
                    'statusUrl' => route('schedules.toggle-status', $s),
                    'canEdit' => $authUser->can('update', $s),
                    'canCancel' => $authUser->can('toggleStatus', $s) && ! $s->isCancelled(),
                    'canComplete' => $authUser->can('toggleStatus', $s) && $s->isScheduled(),
                    'canReactivate' => $authUser->can('toggleStatus', $s) && $s->isCancelled(),
                ],
            ];
        })->values();

        // Données pour les filtres
        $targetCenterId = ($authUser->isCenterAdmin() || $authUser->isSecretary()) ? $authUser->center_id : $request->input('center_id');

        // Le filtre « Classe » ne propose que des classes de formations générales
        // actives du périmètre (mêmes règles que le calendrier).
        $classes = $perimeter->classesForSelect();

        $teachersQuery = Teacher::query()->orderBy('first_name');
        $roomsQuery = Room::query()->orderBy('name');

        if ($targetCenterId) {
            $teachersQuery->where('center_id', $targetCenterId);
            $roomsQuery->where('center_id', $targetCenterId);
        }

        $teachers = $teachersQuery->get();
        $rooms = $roomsQuery->get();
        $centers = $authUser->isSuperAdmin() ? Center::orderBy('name')->get() : collect();

        $typeOptions = [
            'course' => __('Cours'),
            'workshop' => __('Atelier / TP'),
            'exam' => __('Examen'),
            'other' => __('Autre'),
        ];

        $initialDate = $request->input('date') ?: now()->format('Y-m-d');

        return view('schedules.index', compact(
            'events', 'classes', 'teachers', 'rooms', 'centers',
            'typeOptions', 'initialDate'
        ));
    }

    /**
     * Formulaire de création d'une séance.
     */
    public function create(Request $request): View
    {
        Gate::authorize('create', Schedule::class);

        $authUser = $request->user();
        $centers = $authUser->isSuperAdmin() ? Center::orderBy('name')->get() : collect();

        // Déterminer le centre de référence pour charger les listes déroulantes
        $selectedCenterId = ($authUser->isCenterAdmin() || $authUser->isSecretary())
            ? $authUser->center_id
            : ($request->input('center_id') ?? $centers->first()?->id);

        $perimeter = $this->perimeter($authUser, $authUser->isSuperAdmin() ? (int) $request->input('center_id') : null);

        // Classes des formations générales actives du périmètre, libellées
        // « Formation · Groupe X » ; les professeurs de la classe restent
        // nécessaires à l'Alpine du formulaire.
        $classes = $perimeter->classesForSelect(activeOnly: true);
        $classes->load('teachers');

        $rooms = Room::query()
            ->when($selectedCenterId, fn ($q) => $q->where('center_id', $selectedCenterId))
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        $teachers = Teacher::query()
            ->when($selectedCenterId, fn ($q) => $q->where('center_id', $selectedCenterId))
            ->where('status', 'active')
            ->with('classes')
            ->orderBy('first_name')
            ->get();

        $preselectedClassId = $request->input('class_id');

        return view('schedules.create', compact('centers', 'classes', 'rooms', 'teachers', 'selectedCenterId', 'preselectedClassId'));
    }

    /**
     * Enregistrer une nouvelle séance.
     */
    public function store(StoreScheduleRequest $request): RedirectResponse
    {
        $authUser = $request->user();

        // Détermination stricte du center_id côté serveur
        $centerId = ($authUser->isCenterAdmin() || $authUser->isSecretary()) ? $authUser->center_id : (int) $request->input('center_id');

        $data = $request->validated();
        $data['center_id'] = $centerId;

        $schedule = Schedule::create($data);

        return redirect()
            ->route('schedules.show', $schedule)
            ->with('success', __('La séance a été programmée avec succès.'));
    }

    /**
     * Fiche d'une séance.
     */
    public function show(Schedule $schedule): View
    {
        Gate::authorize('view', $schedule);

        $schedule->load(['center', 'classRoom.formation', 'teacher.user', 'room']);

        return view('schedules.show', compact('schedule'));
    }

    /**
     * Formulaire d'édition d'une séance.
     */
    public function edit(Schedule $schedule): View
    {
        Gate::authorize('update', $schedule);

        $schedule->load(['center', 'classRoom', 'teacher', 'room']);
        $authUser = request()->user();
        $centers = $authUser->isSuperAdmin() ? Center::orderBy('name')->get() : collect();

        $centerId = $schedule->center_id;

        $classes = $this->perimeter($authUser, $schedule->center_id)->classesForSelect();
        $classes->load('teachers');

        $rooms = Room::query()
            ->where('center_id', $centerId)
            ->orderBy('name')
            ->get();

        $teachers = Teacher::query()
            ->where('center_id', $centerId)
            ->with('classes')
            ->orderBy('first_name')
            ->get();

        return view('schedules.edit', compact('schedule', 'centers', 'classes', 'rooms', 'teachers'));
    }

    /**
     * Mettre à jour une séance.
     */
    public function update(UpdateScheduleRequest $request, Schedule $schedule): RedirectResponse
    {
        Gate::authorize('update', $schedule);

        $authUser = $request->user();
        $data = $request->validated();

        // Pour un Admin Centre ou Secrétaire, le center_id est immuable
        if ($authUser->isCenterAdmin() || $authUser->isSecretary()) {
            unset($data['center_id']);
        }

        $previousTeacherId = $schedule->teacher_id;
        $schedule->update($data);
        $schedule->refresh();

        // Notification emploi du temps modifié : professeurs réellement concernés
        // (nouvel affecté + ancien si réaffectation). Un seul destinataire par user.
        $notifier = app(NotificationService::class);
        $dateLabel = $schedule->date?->format('d/m/Y') ?? '';
        $affectedTeacherIds = array_filter([$schedule->teacher_id, $previousTeacherId]);
        foreach ($affectedTeacherIds as $teacherId) {
            $teacherUser = Teacher::with('user')->find($teacherId)?->user;
            if (! $teacherUser) {
                continue;
            }
            $notifier->sendUnique(
                $teacherUser,
                'schedule_change',
                __('Emploi du temps modifié'),
                __('Votre séance du :date a été modifiée (:créneau).', [
                    'date' => $dateLabel,
                    'créneau' => trim(($schedule->start_time ?? '').'-'.($schedule->end_time ?? ''), '-'),
                ]),
                route('schedules.show', $schedule),
                'bi-calendar',
                $schedule->center_id,
                Schedule::class,
                $schedule->id,
            );
        }

        return redirect()
            ->route('schedules.show', $schedule)
            ->with('success', __('La séance a été modifiée avec succès.'));
    }

    /**
     * Changer le statut d'une séance (annuler ou marquer terminée).
     */
    public function toggleStatus(Request $request, Schedule $schedule): RedirectResponse
    {
        Gate::authorize('toggleStatus', $schedule);

        $request->validate([
            'status' => ['required', 'string', 'in:scheduled,cancelled,completed'],
        ]);

        $newStatus = $request->input('status');
        $schedule->update(['status' => $newStatus]);

        $statusLabels = [
            'scheduled' => __('programmée'),
            'cancelled' => __('annulée'),
            'completed' => __('marquée comme terminée'),
        ];

        return redirect()
            ->back()
            ->with('success', __('La séance est désormais :statusLabels.', ['statusLabels' => $statusLabels[$newStatus]]));
    }

    /**
     * Supprimer une séance.
     */
    public function destroy(Schedule $schedule): RedirectResponse
    {
        Gate::authorize('delete', $schedule);

        $schedule->delete();

        return redirect()
            ->route('schedules.index')
            ->with('success', __('La séance a été supprimée avec succès.'));
    }
}
