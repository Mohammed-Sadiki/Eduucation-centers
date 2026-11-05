<?php

namespace Database\Seeders;

use App\Models\Center;
use Database\Seeders\Demo\DemoAccountFixtures;
use Database\Seeders\Demo\DemoAdminAccountsSeeder;
use Database\Seeders\Demo\DemoSecretaryAccountsSeeder;
use Database\Seeders\Demo\DemoSynchronizedAccountsSeeder;
use Database\Seeders\Demo\DemoTeacherAccountsSeeder;
use Illuminate\Database\Seeder;

/**
 * Seeder UNIQUEMENT des comptes de connexion de démonstration.
 *
 * Point d'entrée officiel pour un développeur qui clone le projet ou effectue
 * un `git pull` : il obtient des comptes Demo fonctionnels (Super Admin,
 * Admin Centre × 4, Professeurs × 4 centres) SANS aucune donnée métier
 * (aucun étudiant, stagiaire, paiement, facture, dépense, salaire ou
 * inscription n'est créé).
 *
 * Sécurité :
 *   - Interdit en production (garde-fou DemoAccountFixtures).
 *   - Idempotent : rejouable sans doublon ; ne réinitialise jamais un mot de
 *     passe existant et ne modifie jamais un compte réel (conflit → exception).
 *   - Réutilise les centres existants ; ne crée que les 4 centres de
 *     référence absents (sans jamais écraser un centre existant).
 *   - N'utilise jamais migrate:fresh, db:wipe ni truncate.
 *
 * Les mots de passe Demo sont configurables via la variable d'environnement
 * non versionnée DEMO_DEFAULT_PASSWORD (voir .env.example).
 *
 * Usage explicite (développement / démonstration uniquement) :
 *   php artisan migrate
 *   php artisan db:seed --class=DemoAccountsSeeder
 */
class DemoAccountsSeeder extends Seeder
{
    public function run(): void
    {
        DemoAccountFixtures::assertDemoEnvironmentAllowed($this);

        // 1. Les six domaines d'enseignement (pivot nécessaire aux professeurs).
        $this->call(DomainSeeder::class);

        // 2. Les 4 centres de référence : firstOrCreate uniquement si absent
        //    (jamais update — les centres existants ne sont jamais écrasés).
        $createdCenters = 0;

        foreach (CenterSeeder::referenceCenters() as $definition) {
            $exists = Center::query()->where('code', $definition['code'])->exists();

            Center::query()->firstOrCreate(['code' => $definition['code']], $definition);

            if (! $exists) {
                $createdCenters++;
                $this->command?->info("  ✔ Centre « {$definition['code']} » créé (absent).");
            }
        }

        if ($createdCenters === 0) {
            $this->command?->line('  · Les 4 centres de référence existent déjà — aucun centre modifié.');
        }

        // 3. Comptes Demo complets : Super Admin, Admins Centres, Secrétaires, Professeurs
        //    et synchronisation intégrale de tous les comptes de référence.
        //    Chaque seeder appelé rejoue sa propre garde (bloqué en production)
        //    et reste strictement idempotent.
        $this->call(DemoAdminAccountsSeeder::class);
        $this->call(DemoSecretaryAccountsSeeder::class);
        $this->call(DemoTeacherAccountsSeeder::class);
        $this->call(DemoSynchronizedAccountsSeeder::class);

        $this->command?->newLine();
        $this->command?->info(
            '✅ Comptes Demo prêts. Mot de passe : valeur de DEMO_DEFAULT_PASSWORD (.env, non versionné).'
        );
        $this->command?->line(
            '   Aucune donnée métier n\'a été créée — remplissez l\'application depuis l\'interface.'
        );
    }
}
