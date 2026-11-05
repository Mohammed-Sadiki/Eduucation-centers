<?php

namespace Database\Seeders\Demo;

use App\Models\Center;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Comptes de démonstration — SECRÉTAIRES.
 *
 * Crée (ou retrouve) les comptes secrétaire de démonstration pour chacun des
 * 4 centres (Tanger, Agadir, Essaouira, Tétouane).
 *
 * Idempotent : rejouable sans doublon. Ne touche jamais aux comptes réels et
 * ne réinitialise aucun mot de passe existant.
 *
 * Usage :
 *   php artisan db:seed --class=Database\Seeders\Demo\DemoSecretaryAccountsSeeder
 */
class DemoSecretaryAccountsSeeder extends Seeder
{
    use DemoAccountSupport;

    public function run(): void
    {
        DemoAccountFixtures::assertDemoEnvironmentAllowed($this);

        $created = 0;
        $existing = 0;

        foreach (DemoAccountFixtures::secretaries() as $sec) {
            $model = Center::query()->where('code', $sec['code'])->first();

            if (! $model) {
                $this->command?->warn(
                    "  ⚠ Centre « {$sec['code']} » introuvable — secrétaire ignorée."
                );

                continue;
            }

            $result = $this->ensureDemoAccount(
                $sec['email'],
                $sec['name'],
                User::ROLE_SECRETARY,
                (int) $model->id,
            );

            $result['created'] ? $created++ : $existing++;
            $this->command?->info(
                ($result['created'] ? '  ✔ créé   ' : '  · existant ')
                . $result['user']->email . '  [' . User::ROLE_SECRETARY . ' · ' . $sec['code'] . ']'
            );
        }

        $this->command?->info(
            "✅ Comptes secrétaire démo : {$created} créé(s), {$existing} déjà présent(s)."
        );
    }
}

