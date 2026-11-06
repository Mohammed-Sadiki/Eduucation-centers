<?php

namespace App\Http\Controllers;

use App\Models\Center;
use App\Services\DomainDashboardData;
use App\Support\DomainRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SuperAdminDomainController extends Controller
{
    /**
     * Domaines canoniques de repli (source de vérité principale = table `domains`).
     * Conservé pour la compatibilité (tests, FinanceService) ; la résolution
     * effective passe par {@see DomainRegistry} qui privilégie la base.
     *
     * Clé = slug, Valeur = libellé.
     */
    public const DOMAINS = [
        'sante'            => 'Santé',
        'informatique'     => 'Informatique',
        'hotellerie'       => 'Hôtellerie',
        'maconnerie'       => 'Maçonnerie',
        'design-interieur' => 'Design intérieur',
        'design-graphique' => 'Design graphique',
    ];

    /**
     * Affiche la page de sélection des domaines du Super Admin.
     * Réinitialise le domaine actif en session pour partir d'un état neutre.
     */
    public function index(): View
    {
        session()->forget('active_domain');
        session()->forget('active_formation_id');

        $domains = DomainRegistry::visible();

        return view('super-admin.domaines', compact('domains'));
    }

    /**
     * Dashboard d'un domaine de formation, réellement filtré.
     *
     * Route paramétrée : GET /super-admin/domaines/{domain}
     * Le slug est validé contre la source de vérité (base ou repli canonique).
     *
     * KPI et graphiques partagent exactement le même périmètre : le domaine
     * actif (tous centres par défaut) + un filtre centre secondaire optionnel
     * (?center_id) réservé au Super Admin.
     */
    public function domainDashboard(Request $request, string $domain, DomainDashboardData $dashboard): View
    {
        $this->assertDomainAllowed($domain);

        session(['active_domain' => $domain]);
        session()->forget('active_formation_id');

        // Filtre centre secondaire (Super Admin uniquement).
        $centerId = $request->query('center_id') ? (int) $request->query('center_id') : null;

        $data = $dashboard->build($domain, $centerId);

        $centers = Center::orderBy('name')->get();

        return view('super-admin.domain.dashboard', array_merge($data, [
            'domain'    => $domain,
            'centers'   => $centers,
            'centerId'  => $centerId,
        ]));
    }

    /**
     * Alias conservé pour compatibilité : redirige vers le dashboard Santé.
     */
    public function santeDashboard(Request $request, DomainDashboardData $dashboard): View
    {
        return $this->domainDashboard($request, 'sante', $dashboard);
    }

    /**
     * Quitte le contexte domaine actif : la liste des domaines redevient la
     * source de tous les périmètres. Utilisé par le bandeau de sidebar.
     */
    public function clearDomain(): RedirectResponse
    {
        session()->forget('active_domain');
        session()->forget('active_formation_id');

        return redirect()->route('dashboard');
    }

    /**
     * Vérifie que le domaine demandé est valide. Le Super Admin voit tous les
     * domaines ; une sous-classe (Admin Centre) peut restreindre à son centre.
     */
    protected function assertDomainAllowed(string $domain): void
    {
        abort_unless(DomainRegistry::isValid($domain), 404);
    }
}
