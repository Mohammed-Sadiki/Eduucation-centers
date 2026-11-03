<?php

namespace Database\Factories;

use App\Models\Center;
use App\Models\Formation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Formation>
 */
class FormationFactory extends Factory
{
    protected $model = Formation::class;

    /**
     * @var list<string>
     */
    private const DOMAINS = [
        'sante',
        'informatique',
        'hotellerie',
        'maconnerie',
        'design-interieur',
        'design-graphique',
    ];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $annualPrice = fake()->randomFloat(2, 8000, 18000);
        $durationWeeks = fake()->numberBetween(24, 52);

        return [
            'center_id' => Center::factory(),
            'domain' => fake()->randomElement(self::DOMAINS),
            'name' => fake()->randomElement(['Formation', 'Certification', 'Parcours']).' '.fake()->jobTitle(),
            'code' => strtoupper(fake()->unique()->bothify('FOR-####-??')),
            'description' => fake()->sentence(15),
            'duration_hours' => $durationWeeks * fake()->numberBetween(20, 30),
            'duration_weeks' => $durationWeeks,
            'price' => round($annualPrice / 10, 2),
            'annual_price' => $annualPrice,
            'currency' => default_currency(),
            'status' => 'active',
        ];
    }

    public function forCenter(Center|int $center): static
    {
        $centerId = $center instanceof Center ? $center->id : $center;

        return $this->state(fn (array $attributes) => [
            'center_id' => $centerId,
        ]);
    }

    public function forDomain(string $domain): static
    {
        return $this->state(fn (array $attributes) => [
            'domain' => $domain,
        ]);
    }

    /**
     * Formation générale officielle (nouvelle architecture « Centre →
     * Formation → Classe »). Le code officiel reprend le slug du domaine
     * pour cohérence avec `OfficialFormationsSeeder`.
     */
    public function official(?string $officialCode = null): static
    {
        return $this->state(fn (array $attributes) => [
            'is_official'   => true,
            'official_code' => $officialCode ?? $attributes['domain'],
            'teacher_id'    => null,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'inactive',
        ]);
    }
}
