<?php

namespace App\Http\Middleware;

use App\Models\Formation;
use App\Support\DomainRegistry;
use App\Support\UserContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Résout et sécurise le contexte applicatif (rôle + centre + domaine + formation).
 *
 * Invité : aucun effet sur les pages publiques et la connexion.
 *
 * Responsabilités :
 *  - valider le domaine actif en session côté serveur contre la source de vérité
 *    (`domains` en base, repli canonique) : un domaine inconnu est retiré et
 *    l'utilisateur est renvoyé vers le portail de sélection ;
 *  - valider la formation active en session si présente ;
 *  - forcer le centre pour un Admin Centre (jamais celui fourni par l'URL) ;
 *  - exposer le contexte via le container et le partage de vue `context`.
 */
class ResolveDomainContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Invités (site public, login) : on ne touche à rien.
        if (! $user) {
            return $next($request);
        }

        // ── Formation active en session ──
        $activeFormationId = session('active_formation_id') ? (int) session('active_formation_id') : null;
        if ($activeFormationId) {
            $formation = Formation::query()->find($activeFormationId);
            if (! $formation || $formation->status !== 'active') {
                session()->forget('active_formation_id');
                $activeFormationId = null;
            } elseif ($user->isCenterAdmin() || $user->isSecretary() || $user->isTeacher()) {
                // Contrôle d'accès centre : la formation doit appartenir au centre de l'utilisateur ou être globale (center_id NULL)
                if ($formation->center_id !== null && (int) $formation->center_id !== (int) $user->center_id) {
                    session()->forget('active_formation_id');
                    $activeFormationId = null;
                }
            }
        }

        // ── Domaine actif : validation serveur contre la source de vérité ──
        $activeDomain = session('active_domain');
        if ($activeDomain && $activeDomain !== 'all' && ! DomainRegistry::isValid($activeDomain)) {
            session()->forget('active_domain');

            return redirect()
                ->route($this->pickerRoute($user))
                ->with('error', __('Formation générale non reconnue. Veuillez la reselecter.'));
        }

        $activeDomain = ($activeDomain && $activeDomain !== 'all') ? $activeDomain : null;

        // ── Centre : forcé pour l'Admin Centre, optionnel pour le Super Admin ──
        if ($user->isCenterAdmin() || $user->isSecretary() || $user->isTeacher() || $user->isStudent()) {
            $centerId = $user->center_id;
        } else {
            // Super Admin : si une formation active est choisie et rattachée à un centre précis, on peut s'y aligner
            $centerId = $request->query('center_id') ? (int) $request->query('center_id') : null;
        }

        $context = new UserContext($user, $activeDomain, $centerId, $activeFormationId);

        $this->expose($context);

        return $next($request);
    }

    /**
     * Publie le contexte dans le container et le rend disponible aux vues.
     */
    protected function expose(UserContext $context): void
    {
        app()->instance(UserContext::class, $context);

        view()->share('context', $context);
        view()->share('activeDomain', $context->domainSlug());
        view()->share('activeFormationId', $context->formationId());
        view()->share('activeFormation', $context->formation());
    }

    /**
     * Route de repli quand le domaine actif est invalide.
     */
    protected function pickerRoute($user): string
    {
        return $user->isSuperAdmin() ? 'super-admin.domaines' : 'dashboard';
    }
}
