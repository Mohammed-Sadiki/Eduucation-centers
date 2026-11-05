<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRoomRequest;
use App\Http\Requests\UpdateRoomRequest;
use App\Models\Center;
use App\Models\Room;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class RoomController extends Controller
{
    /**
     * Liste des salles avec filtres et pagination.
     * Super Admin -> tous les centres | Admin Centre -> uniquement son centre.
     */
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Room::class);

        $authUser = $request->user();
        $query = Room::with('center');

        // Isolation multi-centres stricte côté serveur
        if ($authUser->isCenterAdmin() || $authUser->isSecretary()) {
            $query->where('center_id', $authUser->center_id);
        }

        // Filtre : recherche par nom
        if ($search = $request->input('search')) {
            $query->where('name', 'like', "%{$search}%");
        }

        // Filtre : code
        if ($code = $request->input('code')) {
            $query->where('code', 'like', "%{$code}%");
        }

        // Filtre : statut
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        // Filtre : centre (Super Admin seulement)
        if ($authUser->isSuperAdmin() && ($centerId = $request->input('center_id'))) {
            $query->where('center_id', $centerId);
        }

        $rooms = $query->latest('id')->paginate(15)->withQueryString();
        $centers = $authUser->isSuperAdmin() ? Center::orderBy('name')->get() : collect();

        return view('rooms.index', compact('rooms', 'centers'));
    }

    /**
     * Formulaire de création d'une salle.
     */
    public function create(Request $request): View
    {
        Gate::authorize('create', Room::class);

        $authUser = $request->user();
        $centers = $authUser->isSuperAdmin() ? Center::orderBy('name')->get() : collect();

        return view('rooms.create', compact('centers'));
    }

    /**
     * Enregistrer une nouvelle salle.
     */
    public function store(StoreRoomRequest $request): RedirectResponse
    {
        $authUser = $request->user();

        // Détermination stricte du center_id côté serveur (jamais faire confiance au client)
        $centerId = ($authUser->isCenterAdmin() || $authUser->isSecretary()) ? $authUser->center_id : (int) $request->input('center_id');

        $data = $request->validated();
        $data['center_id'] = $centerId;

        $room = Room::create($data);

        return redirect()
            ->route('rooms.show', $room)
            ->with('success', __('La salle \\":name\\" (:code) a été créée avec succès.', ['name' => $room->name, 'code' => $room->code]));
    }

    /**
     * Consulter une salle.
     */
    public function show(Room $room): View
    {
        Gate::authorize('view', $room);

        $room->load('center');

        return view('rooms.show', compact('room'));
    }

    /**
     * Formulaire d'édition d'une salle.
     */
    public function edit(Room $room): View
    {
        Gate::authorize('update', $room);

        $room->load('center');

        return view('rooms.edit', compact('room'));
    }

    /**
     * Mettre à jour une salle.
     */
    public function update(UpdateRoomRequest $request, Room $room): RedirectResponse
    {
        $room->update($request->validated());

        return redirect()
            ->route('rooms.show', $room)
            ->with('success', __('La salle \\":name\\" a été mise à jour avec succès.', ['name' => $room->name]));
    }

    /**
     * Activer ou désactiver une salle.
     */
    public function toggleStatus(Request $request, Room $room): RedirectResponse
    {
        Gate::authorize('toggleStatus', $room);

        $newStatus = $room->isActive() ? 'inactive' : 'active';
        $room->update(['status' => $newStatus]);

        $message = $newStatus === 'active'
            ? __('La salle \\":name\\" a été activée.', ['name' => $room->name])
            : __('La salle \\":name\\" a été désactivée.', ['name' => $room->name]);

        return back()->with('success', $message);
    }

    /**
     * Supprimer une salle si aucune dépendance ne l'empêche.
     */
    public function destroy(Room $room): RedirectResponse
    {
        Gate::authorize('delete', $room);

        // Protection contre les dépendances futures (schedules, reservations, etc.)
        if (method_exists($room, 'schedules') && $room->schedules()->exists()) {
            return back()->with('error', __('Impossible de supprimer la salle : des séances y sont planifiées.'));
        }

        $name = $room->name;
        $room->delete();

        return redirect()
            ->route('rooms.index')
            ->with('success', __('La salle \\":name\\" a été supprimée avec succès.', ['name' => $name]));
    }
}
