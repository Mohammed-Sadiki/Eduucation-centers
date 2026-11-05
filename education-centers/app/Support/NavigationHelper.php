<?php

namespace App\Support;

class NavigationHelper
{
    /**
     * Calcule la route de repli logique et autorisée pour la page courante,
     * adaptée au module et au rôle de l'utilisateur connecté.
     */
    public static function resolveFallback(?string $customFallback = null): string
    {
        if (!empty($customFallback)) {
            return $customFallback;
        }

        $user = auth()->user();
        if (!$user) {
            return url('/');
        }

        $route = request()->route();
        $routeName = $route ? $route->getName() : '';

        // Accueil principal par rôle
        $homeUrl = ($user->isSuperAdmin() || $user->isCenterAdmin() || $user->isSecretary())
            ? route('admin.home')
            : route('dashboard');

        if (empty($routeName)) {
            return $homeUrl;
        }

        // 1. Module Gestion financière (Super Admin / Admin Centre)
        if (str_starts_with($routeName, 'super-admin.finance.')) {
            if ($routeName === 'super-admin.finance.dashboard') {
                return $homeUrl;
            }
            return route('super-admin.finance.dashboard');
        }

        // 2. Paiements & Factures
        if (str_starts_with($routeName, 'payments.')) {
            if ($routeName === 'payments.index') {
                return $user->isSuperAdmin() ? route('super-admin.finance.students') : $homeUrl;
            }
            return route('payments.index');
        }
        if (str_starts_with($routeName, 'invoices.')) {
            return $routeName === 'invoices.index' ? route('super-admin.finance.dashboard') : route('invoices.index');
        }

        // 3. Stagiaires
        if (str_starts_with($routeName, 'stagiaires.')) {
            if ($routeName === 'stagiaires.index' || $routeName === 'stagiaires.dashboard') {
                return $homeUrl;
            }
            return route('stagiaires.index');
        }

        // 4. Formations générales & Domaines
        if (
            str_starts_with($routeName, 'formations-generales.') ||
            in_array($routeName, [
                'super-admin.domaines',
                'center-admin.domaines',
                'super-admin.domain.dashboard',
                'center-admin.domain.dashboard',
                'super-admin.sante.dashboard'
            ])
        ) {
            if (in_array($routeName, ['formations-generales.index', 'super-admin.domaines', 'center-admin.domaines'])) {
                return $homeUrl;
            }
            return route('formations-generales.index');
        }

        // 5. Élèves (Students)
        if (str_starts_with($routeName, 'students.')) {
            if ($routeName === 'students.index') {
                $ctx = function_exists('user_context') ? user_context() : null;
                if ($ctx && $ctx->hasDomain() && ($user->isSuperAdmin() || $user->isCenterAdmin())) {
                    $domainRoute = $user->isCenterAdmin() ? 'center-admin.domain.dashboard' : 'super-admin.domain.dashboard';
                    return route($domainRoute, $ctx->domainSlug());
                }
                return route('formations-generales.index');
            }
            return route('students.index');
        }

        // 6. Inscriptions (Enrollments)
        if (str_starts_with($routeName, 'enrollments.')) {
            return $routeName === 'enrollments.index' ? route('students.index') : route('enrollments.index');
        }

        // 7. Classes
        if (str_starts_with($routeName, 'classes.')) {
            return $routeName === 'classes.index' ? route('formations-generales.index') : route('classes.index');
        }

        // 8. Professeurs
        if (str_starts_with($routeName, 'teachers.')) {
            return $routeName === 'teachers.index' ? $homeUrl : route('teachers.index');
        }

        // 9. Salles
        if (str_starts_with($routeName, 'rooms.')) {
            return $routeName === 'rooms.index' ? $homeUrl : route('rooms.index');
        }

        // 10. Emplois du temps / Séances
        if (str_starts_with($routeName, 'schedules.')) {
            return $routeName === 'schedules.index' ? $homeUrl : route('schedules.index');
        }

        // 11. Présences
        if (str_starts_with($routeName, 'attendances.')) {
            return $routeName === 'attendances.index' ? route('formations-generales.index') : route('attendances.index');
        }

        // 12. Évaluations
        if (str_starts_with($routeName, 'evaluations.')) {
            return $routeName === 'evaluations.index' ? route('formations-generales.index') : route('evaluations.index');
        }

        // 13. Certificats
        if (str_starts_with($routeName, 'certificates.')) {
            return $routeName === 'certificates.index' ? route('formations-generales.index') : route('certificates.index');
        }

        // 14. Utilisateurs
        if (str_starts_with($routeName, 'users.')) {
            return $routeName === 'users.index' ? $homeUrl : route('users.index');
        }

        // 15. Centres
        if (str_starts_with($routeName, 'centers.')) {
            return $routeName === 'centers.index' ? $homeUrl : route('centers.index');
        }

        // 16. Annonces
        if (str_starts_with($routeName, 'announcements.')) {
            return $routeName === 'announcements.index' ? $homeUrl : route('announcements.index');
        }

        // 17. Documents
        if (str_starts_with($routeName, 'documents.')) {
            return $routeName === 'documents.index' ? route('students.index') : route('documents.index');
        }

        // 18. Plan de classe
        if ($routeName === 'class-plan.index') {
            return route('formations-generales.index');
        }

        // 19. Messagerie / Communication
        if (in_array($routeName, ['messages.index', 'teacher.messages'])) {
            return $homeUrl;
        }

        // 20. Espace Professeur
        if (str_starts_with($routeName, 'teacher.')) {
            if ($routeName === 'teacher.class') {
                return route('teacher.classes');
            }
            return route('dashboard');
        }

        // 21. Espace Élève
        if (str_starts_with($routeName, 'student.')) {
            return route('dashboard');
        }

        // 22. Audit & Notifications
        if (in_array($routeName, ['audit.index', 'notifications.index'])) {
            return $homeUrl;
        }

        return $homeUrl;
    }
}

