<?php

namespace Database\Factories;

use App\Models\Persona;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Persona> */
class PersonaFactory extends Factory
{
    protected $model = Persona::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['nombre' => fake()->firstName(), 'ap' => fake()->lastName(), 'am' => null, 'correo' => fake()->safeEmail()];
    }
}
