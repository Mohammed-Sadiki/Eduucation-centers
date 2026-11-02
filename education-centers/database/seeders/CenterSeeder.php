<?php

namespace Database\Seeders;

use App\Models\Center;
use Illuminate\Database\Seeder;

class CenterSeeder extends Seeder
{
    /**
     * Définition canonique des 4 centres de référence (source unique,
     * réutilisée par les seeders de comptes Demo).
     *
     * @return list<array<string, string>>
     */
    public static function referenceCenters(): array
    {
        return [
            [
                'name'        => 'Centre Tanger',
                'code'        => 'TANGER',
                'address'     => 'Boulevard Mohammed V',
                'city'        => 'Tanger',
                'postal_code' => '90000',
                'country'     => 'Morocco',
                'phone'       => '+212 5 39 00 00 01',
                'email'       => 'tanger@education-centers.ma',
                'status'      => 'active',
            ],
            [
                'name'        => 'Centre Essaouira',
                'code'        => 'ESSAOUIRA',
                'address'     => 'Avenue de la Médina',
                'city'        => 'Essaouira',
                'postal_code' => '44000',
                'country'     => 'Morocco',
                'phone'       => '+212 5 24 00 00 02',
                'email'       => 'essaouira@education-centers.ma',
                'status'      => 'active',
            ],
            [
                'name'        => 'Centre Agadir',
                'code'        => 'AGADIR',
                'address'     => 'Boulevard Hassan II',
                'city'        => 'Agadir',
                'postal_code' => '80000',
                'country'     => 'Morocco',
                'phone'       => '+212 5 28 00 00 03',
                'email'       => 'agadir@education-centers.ma',
                'status'      => 'active',
            ],
            [
                'name'        => 'Centre Tétouane',
                'code'        => 'TETOUANE',
                'address'     => 'Avenue Mohammed V',
                'city'        => 'Tétouane',
                'postal_code' => '93000',
                'country'     => 'Morocco',
                'phone'       => '+212 5 39 00 00 04',
                'email'       => 'tetouane@education-centers.ma',
                'status'      => 'active',
            ],
        ];
    }

    /**
     * Insère ou met à jour les 4 centres marocains.
     */
    public function run(): void
    {
        foreach (self::referenceCenters() as $centerData) {
            Center::updateOrCreate(
                ['code' => $centerData['code']],
                $centerData
            );
        }

        $this->command->info('✅ Les 4 centres marocains sont configurés : Tanger, Essaouira, Agadir, Tétouane.');
    }
}
