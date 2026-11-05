<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\Center;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AnnouncementController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Announcement::class);
        $user = $request->user();
        $query = Announcement::query()->with('author');
        if ($user->isCenterAdmin() || $user->isSecretary()) {
            $query->where(fn ($q) => $q->where('center_id', $user->center_id)->orWhereNull('center_id'));
        }
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }
        $announcements = $query->latest()->paginate(15)->withQueryString();
        $centers = $user->isSuperAdmin() ? Center::orderBy('name')->get() : collect();

        return view('announcements.index', compact('announcements', 'centers'));
    }

    public function create(): View
    {
        Gate::authorize('create', Announcement::class);
        $centers = auth()->user()->isSuperAdmin() ? Center::orderBy('name')->get() : collect();

        return view('announcements.create', compact('centers'));
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Announcement::class);
        $user = $request->user();
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'is_public' => ['boolean'],
            'status' => ['required', 'in:draft,published,archived'],
            'center_id' => ['nullable', 'integer', 'exists:centers,id'],
        ]);
        if ($user->isCenterAdmin() || $user->isSecretary()) {
            $data['center_id'] = $user->center_id;
        }
        $data['author_id'] = $user->id;
        if ($data['status'] === 'published' && empty($data['published_at'])) {
            $data['published_at'] = now();
        }
        $a = Announcement::create($data);

        if ($a->status === 'published') {
            $this->notifyAudience($a, $user->id);
        }

        return redirect()->route('announcements.show', $a)->with('success', __('Annonce \\":title\\" créée.', ['title' => $a->title]));
    }

    public function show(Announcement $announcement): View
    {
        Gate::authorize('view', $announcement);

        return view('announcements.show', compact('announcement'));
    }

    public function edit(Announcement $announcement): View
    {
        Gate::authorize('update', $announcement);
        $centers = auth()->user()->isSuperAdmin() ? Center::orderBy('name')->get() : collect();

        return view('announcements.edit', compact('announcement', 'centers'));
    }

    public function update(Request $request, Announcement $announcement): RedirectResponse
    {
        Gate::authorize('update', $announcement);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'is_public' => ['boolean'],
            'status' => ['required', 'in:draft,published,archived'],
        ]);
        if ($data['status'] === 'published' && ! $announcement->published_at) {
            $data['published_at'] = now();
        }
        $announcement->update($data);

        if ($announcement->fresh()->status === 'published') {
            $this->notifyAudience($announcement, $request->user()->id);
        }

        return redirect()->route('announcements.show', $announcement)->with('success', __('Annonce mise à jour.'));
    }

    /**
     * Notifie UNIQUEMENT les destinataires visés par l'annonce :
     * - annonce rattachée à un centre → admins + professeurs de CE centre ;
     * - annonce globale (center_id null) → tous les admins de centre + professeurs.
     * L'auteur n'est jamais notifié. Dédupliqué (pas de doublon à la re-publication).
     */
    private function notifyAudience(Announcement $announcement, int $authorId): void
    {
        $query = \App\Models\User::query()
            ->whereIn('role', [\App\Models\User::ROLE_CENTER_ADMIN, \App\Models\User::ROLE_TEACHER])
            ->whereKeyNot($authorId);

        if ($announcement->center_id) {
            $query->where('center_id', $announcement->center_id);
        }

        $notifier = app(\App\Services\NotificationService::class);
        $link = route('announcements.show', $announcement);

        foreach ($query->get() as $recipient) {
            $notifier->sendUnique(
                $recipient,
                'announcement',
                __('Nouvelle annonce'),
                $announcement->title,
                $link,
                'bi-megaphone',
                $recipient->center_id,
                Announcement::class,
                $announcement->id,
            );
        }
    }

    public function destroy(Announcement $announcement): RedirectResponse
    {
        Gate::authorize('delete', $announcement);
        $announcement->delete();

        return redirect()->route('announcements.index')->with('success', __('Annonce supprimée.'));
    }
}
