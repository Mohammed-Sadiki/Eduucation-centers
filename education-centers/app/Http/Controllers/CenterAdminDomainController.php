<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\DomainDashboardData;
use App\Support\DomainRegistry;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Espace Admin Centre piloté par domaine — réutilise intégralement la logique
 * du Super Admin (DomainDashboardData + vue partagée) en la restreignant au
 * centre courant de l'utilisateur.
 *
 * Catalogue commun + isolation des données :
 *  - les six domaines sont disponibles pour chaque centre (aucun domaine absent
 *    faute de données) ; un domaine sans formation affiche un état vide ;
 *  - le périmètre est TOUJOURS limité au centre de l'Admin Centre (jamais celui
 *    fourni par l'URL) : un Admin Centre ne voit jamais les données d'un autre
 *    centre, même sur un domaine commun.
 */
class CenterAdminDomainController extends SuperAdminDomainController
{
    /**
     * Portail de sélection des domaines : catalogue commun aux deux centres.
     * (Nom distinct de l'index() du parent, sans conflit de signature.)
     */
    public function domainPicker(Request $request): View
    {
        session()->forget('active_domain');
        session()->forget('active_formation_id');

        $domains = DomainRegistry::visibleForCenter($this->centerId($request));

        return view('center-admin.domaines', compact('domains'));
    }

    /**
     * Tableau de bord d'un domaine, restreint au centre de l'Admin Centre.
     */
    public function domainDashboard(Request $request, string $domain, DomainDashboardData $dashboard): View
    {
        $this->assertDomainAllowed($domain);

        session(['active_domain' => $domain]);
        session()->forget('active_formation_id');

        // Périmètre forcé : le centre de l'Admin Centre, jamais un ?center_id externe.
        $centerId = $this->centerId($request);

        $data = $dashboard->build($domain, $centerId);

        return view('super-admin.domain.dashboard', array_merge($data, [
            'domain'   => $domain,
            'centers'  => collect(),
            'centerId' => $centerId,
        ]));
    }

    /**
     * Un domaine n'est accessible que s'il est valide (catalogue commun).
     * Les données restent filtrées par le centre forcé du contexte.
     */
    protected function assertDomainAllowed(string $domain): void
    {
        abort_unless(
            DomainRegistry::isValid($domain)
            && DomainRegistry::existsForCenter($domain, (int) $this->centerId(request())),
            404
        );
    }

    /**
     * Centre courant de l'Admin Centre connecté.
     */
    private function centerId(Request $request): int
    {
        /** @var User $user */
        $user = $request->user();

        return (int) $user->center_id;
    }
}
