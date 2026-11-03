<?php

namespace Database\Seeders;

use App\Models\Center;
use App\Models\Formation;
use Illuminate\Database\Seeder;

/**
 * Sème les SIX formations générales officielles de chaque centre.
 *
 * Architecture cible : Centre → Formation générale → Classe.
 * Les six programmes de référence sont : Santé, Informatique, Hôtellerie,
 * Maçonnerie, Design intérieur, Design graphique (mêmes codes que les domaines).
 *
 * Propriétés :
 *  - IDEMPOTENT : appairage sur (center_id, official_code) via firstOrCreate ;
 *    une réexécution ne crée ni ne duplique rien ;
 *  - NON DESTRUCTIF : ne touche AUCUNE formation héritée existante, aucune
 *    classe, inscription, paiement, certificat ou présence ; ne reparente rien ;
 *  - les formations officielles créées sont vides (0 classe, responsable non
 *    assigné) : l'affectation reste une décision humaine ;
 *  - le prix du catalogue est laissé NULL (défaut modifiable par l'administrateur).
 *
 * Ce seeder est de la donnée de RÉFÉRENCE (configuration métier légitime) : il
 * peut s'exécuter en production, contrairement aux seeders de comptes démo.
 */
class OfficialFormationsSeeder extends Seeder
{
    /**
     * @return int Nombre de formations officielles réellement créées.
     */
    public function run(): int
    {
        // S'assure que les domaines de référence (source des 6 codes/libellés) existent.
        $this->call(DomainSeeder::class);

        $definitions = DomainSeeder::domains();
        $created = 0;

        foreach (Center::orderBy('id')->get() as $center) {
            foreach ($definitions as $definition) {
                $code = $definition['slug'];

                $formation = Formation::query()->firstOrCreate(
                    [
                        'center_id'     => $center->id,
                        'official_code' => $code,
                    ],
                    [
                        'domain'        => $code,
                        'name'          => $definition['name'],
                        'description'   => $definition['description'],
                        'code'          => $this->formationCode($center, $code),
                        'is_official'   => true,
                        'status'        => 'active',
                        'teacher_id'    => null,
                        'merged_into_id' => null,
                        'currency'      => default_currency(),
                    ]
                );

                // Compte uniquement les créations réelles (idempotence).
                if ($formation->wasRecentlyCreated) {
                    $created++;
                }
            }
        }

        $this->command?->info("Formations générales officielles : {$created} créée(s) (6 par centre, idempotent).");

        return $created;
    }

    /**
     * Code unique par centre pour la formation officielle (contrainte existante
     * unique(center_id, code)). Ex. OF-TANGER-SANTE.
     */
    private function formationCode(Center $center, string $slug): string
    {
        $normalized = strtoupper(str_replace('-', '_', $slug));

        return sprintf('OF-%s-%s', strtoupper($center->code), $normalized);
    }
}
