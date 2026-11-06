<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreStagiaireRequest;
use App\Http\Requests\UpdateStagiaireRequest;
use App\Models\Center;
use App\Models\Stagiaire;
use App\Models\StagiaireDiploma;
use App\Models\StagiaireDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class StagiaireController extends Controller
{
    /**
     * Ancienne entrée du module : le tableau de bord statistique a été supprimé.
     * La page principale est désormais la liste « Tous les stagiaires ».
     * La route est conservée pour ne pas casser les anciens liens.
     */
    public function dashboard(): RedirectResponse
    {
        Gate::authorize('viewAny', Stagiaire::class);

        return redirect()->route('stagiaires.index');
    }

    /**
     * Liste des stagiaires avec recherche, filtres et pagination.
     */
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Stagiaire::class);

        $authUser = $request->user();

        // Périmètre : Admin Centre / Secrétaire → son centre uniquement ; Super Admin → tous les centres.
        $scopeQuery = Stagiaire::query();
        if ($authUser->isCenterAdmin() || $authUser->isSecretary()) {
            $scopeQuery->where('center_id', $authUser->center_id);
        }

        // Carte « Total stagiaires » : effectif réel du périmètre, calculé sur la base.
        // Volontairement indépendant des filtres (recherche, statut, centre) pour que
        // le compteur reste cohérent quelle que soit la liste affichée.
        $totalStagiaires = $scopeQuery->count();

        // `center` est la seule relation affichée par le tableau : pas de chargement inutile.
        $query = Stagiaire::with('center');

        if ($authUser->isCenterAdmin() || $authUser->isSecretary()) {
            $query->where('center_id', $authUser->center_id);
        }

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('cin_number', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($authUser->isSuperAdmin() && ($centerId = $request->input('center_id'))) {
            $query->where('center_id', $centerId);
        }

        $stagiaires = $query->latest('id')->paginate(12)->withQueryString();
        $centers = $authUser->isSuperAdmin() ? Center::orderBy('name')->get() : collect();

        return view('super-admin.stagiaires.index', compact(
            'stagiaires',
            'centers',
            'totalStagiaires'
        ));
    }

    /**
     * Espace centralisé de consultation et gestion des documents des stagiaires.
     */
    public function documents(Request $request): View
    {
        Gate::authorize('viewAny', Stagiaire::class);

        $authUser = $request->user();
        $typeFilter = $request->input('type');
        $search = $request->input('search');

        // Récupérer les documents administratifs
        $docsQuery = StagiaireDocument::with('stagiaire');
        if ($authUser->isCenterAdmin() || $authUser->isSecretary()) {
            $docsQuery->whereHas('stagiaire', function ($q) use ($authUser) {
                $q->where('center_id', $authUser->center_id);
            });
        }
        if ($search) {
            $docsQuery->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhereHas('stagiaire', function ($sq) use ($search) {
                        $sq->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%");
                    });
            });
        }
        if ($typeFilter && ! in_array($typeFilter, ['cin', 'diploma'])) {
            $docsQuery->where('type', $typeFilter);
        }

        // Récupérer les diplômes
        $diplomasQuery = StagiaireDiploma::with('stagiaire')->whereNotNull('file_path');
        if ($authUser->isCenterAdmin() || $authUser->isSecretary()) {
            $diplomasQuery->whereHas('stagiaire', function ($q) use ($authUser) {
                $q->where('center_id', $authUser->center_id);
            });
        }
        if ($search) {
            $diplomasQuery->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('institution', 'like', "%{$search}%")
                    ->orWhereHas('stagiaire', function ($sq) use ($search) {
                        $sq->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%");
                    });
            });
        }

        // Récupérer les stagiaires avec CIN téléversé
        $cinQuery = Stagiaire::query()->where(function ($q) {
            $q->whereNotNull('cin_recto_path')->orWhereNotNull('cin_verso_path');
        });
        if ($authUser->isCenterAdmin() || $authUser->isSecretary()) {
            $cinQuery->where('center_id', $authUser->center_id);
        }
        if ($search) {
            $cinQuery->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('cin_number', 'like', "%{$search}%");
            });
        }

        $documents = ($typeFilter && in_array($typeFilter, ['cin', 'diploma'])) ? collect() : $docsQuery->latest()->get();
        $diplomas = ($typeFilter && $typeFilter !== 'diploma') ? collect() : $diplomasQuery->latest()->get();
        $cins = ($typeFilter && $typeFilter !== 'cin') ? collect() : $cinQuery->latest()->get();

        $totalDocsCount = StagiaireDocument::count() + StagiaireDiploma::whereNotNull('file_path')->count() + Stagiaire::whereNotNull('cin_recto_path')->count();

        return view('super-admin.stagiaires.documents', compact(
            'documents',
            'diplomas',
            'cins',
            'typeFilter',
            'search',
            'totalDocsCount'
        ));
    }

    /**
     * Formulaire d'ajout d'un nouveau stagiaire.
     *
     * Les centres ne sont proposés qu'au Super Admin : un Admin Centre est
     * rattaché d'office à son propre centre (résolu côté serveur à l'enregistrement).
     */
    public function create(Request $request): View
    {
        Gate::authorize('create', Stagiaire::class);

        $centers = $request->user()->isSuperAdmin() ? Center::orderBy('name')->get() : collect();

        return view('super-admin.stagiaires.create', compact('centers'));
    }

    /**
     * Enregistrer un nouveau stagiaire.
     *
     * La validation (Formulaire de requête) est passée avant toute écriture :
     * aucune donnée obligatoire manquante n'atteint la base, et le stagiaire,
     * sa photo, ses fichiers et ses pièces existantes sont créés dans une seule
     * transaction.
     */
    public function store(StoreStagiaireRequest $request): RedirectResponse
    {
        $data = $request->safe()->except([
            'cin_recto', 'cin_verso', 'profile_photo', 'other_files', 'diplomas', 'other_documents',
        ]);

        // Le centre d'un Admin Centre ou Secrétaire est imposé par le serveur, jamais par la requête.
        if ($request->user()->isCenterAdmin() || $request->user()->isSecretary()) {
            $data['center_id'] = $request->user()->center_id;
        }

        $data['created_by'] = $request->user()->id;

        // Les informations absentes du formulaire restent gérées par leurs
        // valeurs par défaut en base : aucune écriture explicite de NULL.
        $data = $this->withoutNullValues($data);

        // Upload CIN recto et verso dans l'espace privé
        if ($request->hasFile('cin_recto')) {
            $data['cin_recto_path'] = $request->file('cin_recto')->store('stagiaires/cin', 'local');
        }

        if ($request->hasFile('cin_verso')) {
            $data['cin_verso_path'] = $request->file('cin_verso')->store('stagiaires/cin', 'local');
        }

        $stagiaire = DB::transaction(function () use ($data, $request) {
            $stagiaire = Stagiaire::create($data);

            $this->storeProfilePhoto($stagiaire, $request);
            $this->attachOtherFiles($stagiaire, $request);
            $this->attachDiplomas($stagiaire, $request);
            $this->attachDocuments($stagiaire, $request);

            return $stagiaire;
        });

        // ── Notifications internes (événement réel, après commit) ──────────
        $notifier = app(\App\Services\NotificationService::class);
        $doerId = $request->user()->id;
        $stagiaireCenterId = (int) $stagiaire->center_id;

        $notifier->notifyCenterAdmins(
            centerId: $stagiaireCenterId,
            type: 'stagiaire',
            title: __('Nouveau stagiaire'),
            message: __(':name vient d\'être enregistré comme stagiaire.', ['name' => $stagiaire->full_name]),
            link: route('stagiaires.show', $stagiaire),
            exceptUserId: $doerId,
            relatedType: \App\Models\Stagiaire::class,
            relatedId: $stagiaire->id,
        );

        // Dossier réellement incomplet : CIN (recto/verso) obligatoire absente.
        if (! $stagiaire->cin_recto_path || ! $stagiaire->cin_verso_path) {
            $notifier->notifyCenterAdmins(
                centerId: $stagiaireCenterId,
                type: 'dossier_incomplete',
                title: __('Dossier de stage incomplet'),
                message: __('Le dossier de :name est incomplet : CIN manquante.', ['name' => $stagiaire->full_name]),
                link: route('stagiaires.show', $stagiaire),
                exceptUserId: $doerId,
                relatedType: \App\Models\Stagiaire::class,
                relatedId: $stagiaire->id,
            );
        }

        return redirect()
            ->route('stagiaires.show', $stagiaire)
            ->with('success', __('Le dossier du stagiaire :full_name a été créé avec succès.', ['full_name' => $stagiaire->full_name]));
    }

    /**
     * Détail du stagiaire.
     */
    public function show(Stagiaire $stagiaire): View
    {
        Gate::authorize('view', $stagiaire);

        $stagiaire->load(['center', 'diplomas', 'documents']);

        return view('super-admin.stagiaires.show', compact('stagiaire'));
    }

    /**
     * Formulaire d'édition du stagiaire (mêmes champs et même présentation
     * que le formulaire d'ajout).
     */
    public function edit(Request $request, Stagiaire $stagiaire): View
    {
        Gate::authorize('update', $stagiaire);

        $stagiaire->load(['diplomas', 'documents']);
        $centers = $request->user()->isSuperAdmin() ? Center::orderBy('name')->get() : collect();

        return view('super-admin.stagiaires.edit', compact('stagiaire', 'centers'));
    }

    /**
     * Mettre à jour le dossier du stagiaire.
     *
     * Les pièces déjà enregistrées (scans CIN, photo de profil, documents,
     * diplômes) sont conservées telles quelles lorsqu'aucun nouveau fichier
     * n'est envoyé. Les champs absents du formulaire ne sont pas réécrits :
     * les valeurs administratives antérieures restent en base. Les écrits sont
     * regroupés dans une seule transaction.
     */
    public function update(UpdateStagiaireRequest $request, Stagiaire $stagiaire): RedirectResponse
    {
        $data = $request->safe()->except([
            'cin_recto', 'cin_verso', 'profile_photo', 'other_files', 'diplomas', 'other_documents',
        ]);

        // Le centre d'un Admin Centre ou Secrétaire est imposé par le serveur.
        if ($request->user()->isCenterAdmin() || $request->user()->isSecretary()) {
            $data['center_id'] = $request->user()->center_id;
        }

        // Aucune valeur NULL n'écrase une information déjà enregistrée.
        $data = $this->withoutNullValues($data);

        if ($request->hasFile('cin_recto')) {
            if ($stagiaire->cin_recto_path && Storage::disk('local')->exists($stagiaire->cin_recto_path)) {
                Storage::disk('local')->delete($stagiaire->cin_recto_path);
            }
            $data['cin_recto_path'] = $request->file('cin_recto')->store('stagiaires/cin', 'local');
        }

        if ($request->hasFile('cin_verso')) {
            if ($stagiaire->cin_verso_path && Storage::disk('local')->exists($stagiaire->cin_verso_path)) {
                Storage::disk('local')->delete($stagiaire->cin_verso_path);
            }
            $data['cin_verso_path'] = $request->file('cin_verso')->store('stagiaires/cin', 'local');
        }

        DB::transaction(function () use ($stagiaire, $data, $request) {
            $stagiaire->update($data);

            $this->storeProfilePhoto($stagiaire, $request);

            // Ajout des seules pièces réellement fournies ; rien n'est supprimé.
            $this->attachOtherFiles($stagiaire, $request);
            $this->attachDiplomas($stagiaire, $request);
            $this->attachDocuments($stagiaire, $request);
        });

        return redirect()
            ->route('stagiaires.show', $stagiaire)
            ->with('success', __('Le dossier de :full_name a été mis à jour avec succès.', ['full_name' => $stagiaire->full_name]));
    }

    /**
     * Conserver uniquement les valeurs réellement envoyées par le formulaire.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function withoutNullValues(array $data): array
    {
        return array_filter($data, fn ($value) => $value !== null);
    }

    /**
     * Téléverser la photo de profil dans l'espace privé du dossier du stagiaire.
     *
     * Aucun fichier existant n'est supprimé lorsqu'aucune nouvelle photo n'est
     * envoyée (règle de préservation du formulaire de modification).
     */
    private function storeProfilePhoto(Stagiaire $stagiaire, Request $request): void
    {
        $file = $request->file('profile_photo');

        if (! $file instanceof UploadedFile || ! $file->isValid()) {
            // Aucune nouvelle photo envoyée : la photo existante est conservée.
            return;
        }

        $oldPath = $stagiaire->profile_photo_path;
        $path = $file->store("stagiaires/{$stagiaire->id}/profile", 'local');

        if ($oldPath && Storage::disk('local')->exists($oldPath)) {
            Storage::disk('local')->delete($oldPath);
        }

        $stagiaire->update(['profile_photo_path' => $path]);
    }

    /**
     * Enregistrer les fichiers de la section facultative « Autres fichiers ».
     * Chaque fichier devient une pièce de type « autre » intitulée d'après son
     * nom d'origine ; les pièces déjà présentes restent intactes.
     */
    private function attachOtherFiles(Stagiaire $stagiaire, Request $request): void
    {
        foreach ((array) $request->file('other_files', []) as $file) {
            if (! $file instanceof UploadedFile || ! $file->isValid()) {
                continue;
            }

            $name = basename($file->getClientOriginalName());
            $path = $file->store("stagiaires/{$stagiaire->id}/documents", 'local');

            $stagiaire->documents()->create([
                'title' => $name,
                'type' => 'autre',
                'file_path' => $path,
                'file_name' => $name,
            ]);
        }
    }

    /**
     * Enregistre les lignes de la section facultative « Diplômes obtenus ».
     * Une ligne laissée vide par défaut est ignorée : aucun faux diplôme n'est créé.
     */
    private function attachDiplomas(Stagiaire $stagiaire, Request $request): void
    {
        foreach ((array) $request->input('diplomas', []) as $index => $dip) {
            if (! is_array($dip) || trim((string) ($dip['title'] ?? '')) === '') {
                continue;
            }

            $filePath = null;
            $fileName = null;
            $file = $request->file("diplomas.{$index}.file");

            if ($file instanceof UploadedFile && $file->isValid()) {
                $fileName = $file->getClientOriginalName();
                $filePath = $file->store("stagiaires/{$stagiaire->id}/diplomas", 'local');
            }

            $stagiaire->diplomas()->create([
                'title' => $dip['title'],
                'institution' => $dip['institution'] ?? null,
                'year' => $dip['year'] ?? null,
                'file_path' => $filePath,
                'file_name' => $fileName,
            ]);
        }
    }

    /**
     * Enregistre les lignes de la section facultative « Autres documents ».
     * Une ligne sans titre (ligne vide par défaut, type présélectionné) est ignorée.
     */
    private function attachDocuments(Stagiaire $stagiaire, Request $request): void
    {
        foreach ((array) $request->input('other_documents', []) as $index => $doc) {
            if (! is_array($doc) || trim((string) ($doc['title'] ?? '')) === '') {
                continue;
            }

            $filePath = null;
            $fileName = null;
            $file = $request->file("other_documents.{$index}.file");

            if ($file instanceof UploadedFile && $file->isValid()) {
                $fileName = $file->getClientOriginalName();
                $filePath = $file->store("stagiaires/{$stagiaire->id}/documents", 'local');
            }

            if ($filePath) {
                $stagiaire->documents()->create([
                    'title' => $doc['title'],
                    'type' => $doc['type'] ?? 'autre',
                    'file_path' => $filePath,
                    'file_name' => $fileName,
                ]);
            }
        }
    }

    /**
     * Supprimer un stagiaire et ses documents associés.
     */
    public function destroy(Stagiaire $stagiaire): RedirectResponse
    {
        Gate::authorize('delete', $stagiaire);

        // Nettoyer les fichiers CIN
        if ($stagiaire->cin_recto_path && Storage::disk('local')->exists($stagiaire->cin_recto_path)) {
            Storage::disk('local')->delete($stagiaire->cin_recto_path);
        }
        if ($stagiaire->cin_verso_path && Storage::disk('local')->exists($stagiaire->cin_verso_path)) {
            Storage::disk('local')->delete($stagiaire->cin_verso_path);
        }

        // Nettoyer le dossier des documents du stagiaire
        Storage::disk('local')->deleteDirectory("stagiaires/{$stagiaire->id}");

        $fullName = $stagiaire->full_name;
        $stagiaire->delete();

        return redirect()
            ->route('stagiaires.index')
            ->with('success', __('Le dossier du stagiaire :fullName a été supprimé.', ['fullName' => $fullName]));
    }

    /**
     * Affichage de la photo de profil (stockée sur le disque privé).
     * Réservé aux utilisateurs autorisés à voir le dossier du stagiaire.
     */
    public function showProfilePhoto(Stagiaire $stagiaire): BinaryFileResponse
    {
        Gate::authorize('view', $stagiaire);

        $path = $stagiaire->profile_photo_path;

        if (! $path || ! Storage::disk('local')->exists($path)) {
            abort(404, __('Photo introuvable.'));
        }

        return response()->file(Storage::disk('local')->path($path));
    }

    /**
     * Téléchargement sécurisé du document CIN (recto ou verso).
     */
    public function downloadCin(Stagiaire $stagiaire, string $side): BinaryFileResponse
    {
        Gate::authorize('view', $stagiaire);

        $path = $side === 'recto' ? $stagiaire->cin_recto_path : $stagiaire->cin_verso_path;

        if (! $path || ! Storage::disk('local')->exists($path)) {
            abort(404, __('Fichier introuvable.'));
        }

        return response()->download(Storage::disk('local')->path($path));
    }

    /**
     * Téléchargement sécurisé d'un diplôme.
     */
    public function downloadDiploma(Stagiaire $stagiaire, StagiaireDiploma $diploma): BinaryFileResponse
    {
        Gate::authorize('view', $stagiaire);

        if ($diploma->stagiaire_id !== $stagiaire->id || ! $diploma->file_path || ! Storage::disk('local')->exists($diploma->file_path)) {
            abort(404, __('Fichier introuvable.'));
        }

        return response()->download(
            Storage::disk('local')->path($diploma->file_path),
            $diploma->file_name ?? basename($diploma->file_path)
        );
    }

    /**
     * Téléchargement sécurisé d'un document administratif.
     */
    public function downloadDocument(Stagiaire $stagiaire, StagiaireDocument $document): BinaryFileResponse
    {
        Gate::authorize('view', $stagiaire);

        if ($document->stagiaire_id !== $stagiaire->id || ! $document->file_path || ! Storage::disk('local')->exists($document->file_path)) {
            abort(404, __('Fichier introuvable.'));
        }

        return response()->download(
            Storage::disk('local')->path($document->file_path),
            $document->file_name ?? basename($document->file_path)
        );
    }

    /**
     * Supprimer un diplôme spécifique.
     */
    public function deleteDiploma(Stagiaire $stagiaire, StagiaireDiploma $diploma): RedirectResponse
    {
        Gate::authorize('update', $stagiaire);

        if ($diploma->stagiaire_id === $stagiaire->id) {
            if ($diploma->file_path && Storage::disk('local')->exists($diploma->file_path)) {
                Storage::disk('local')->delete($diploma->file_path);
            }
            $diploma->delete();
        }

        return back()->with('success', __('Diplôme supprimé.'));
    }

    /**
     * Supprimer un document spécifique.
     */
    public function deleteDocument(Stagiaire $stagiaire, StagiaireDocument $document): RedirectResponse
    {
        Gate::authorize('update', $stagiaire);

        if ($document->stagiaire_id === $stagiaire->id) {
            if ($document->file_path && Storage::disk('local')->exists($document->file_path)) {
                Storage::disk('local')->delete($document->file_path);
            }
            $document->delete();
        }

        return back()->with('success', __('Document supprimé.'));
    }
}
