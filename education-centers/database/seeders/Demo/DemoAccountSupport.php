<?php

namespace Database\Seeders\Demo;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * Helpers communs aux seeders de comptes de démonstration.
 *
 * Fournit une création/reprise idempotente d'un compte utilisateur, avec
 * détection de conflit : si l'e-mail réservé correspond déjà à un compte qui
 * n'est pas le compte de démonstration attendu (rôle ou centre différent),
 * on s'arrête et on signale le problème au lieu d'écraser une donnée réelle.
 */
trait DemoAccountSupport
{
    /**
     * S'assure qu'un compte de démonstration existe pour l'e-mail donné.
     *
     * Ne réinitialise JAMAIS le mot de passe d'un compte existant et ne
     * modifie pas un compte réel (conflit → exception).
     *
     * @return array{user: User, created: bool}
     */
    protected function ensureDemoAccount(
        string $email,
        string $name,
        string $role,
        ?int $centerId,
        array $extraAttributes = [],
    ): array {
        $existing = User::query()->where('email', $email)->first();

        if ($existing) {
            $roleMatches = $existing->role === $role;
            $centerMatches = (int) $existing->center_id === (int) $centerId;

            if (! $roleMatches || ! $centerMatches) {
                throw new RuntimeException(sprintf(
                    "Conflit de compte de démonstration : l'e-mail « %s » existe déjà "
                    . "avec un rôle/centre inattendus (rôle=%s, centre=%s ; attendu rôle=%s, centre=%s). "
                    . "Aucune donnée réelle ne sera modifiée.",
                    $email,
                    $existing->role,
                    $existing->center_id ?? 'null',
                    $role,
                    $centerId ?? 'null',
                ));
            }

            // Compte de démonstration déjà en place : on ne touche pas au mot de passe.
            return ['user' => $existing, 'created' => false];
        }

        $user = User::query()->create(array_merge([
            'name' => $name,
            'email' => $email,
            'role' => $role,
            'center_id' => $centerId,
            'status' => User::STATUS_ACTIVE,
            'locale' => 'fr',
            'password' => Hash::make(DemoAccountFixtures::password()),
        ], $extraAttributes));

        return ['user' => $user, 'created' => true];
    }
}
